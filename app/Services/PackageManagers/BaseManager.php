<?php

namespace App\Services\PackageManagers;

use App\Contracts\PackageManagerInterface;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Cache;

abstract class BaseManager implements PackageManagerInterface
{
    protected ?string $helper = null;

    public function getHelper(): string
    {
        if ($this->helper) return $this->helper;

        $checkParu = Process::run("command -v paru");
        if ($checkParu->successful()) {
            return $this->helper = 'paru';
        }

        $checkYay = Process::run("command -v yay");
        if ($checkYay->successful()) {
            return $this->helper = 'yay';
        }

        return $this->helper = 'pacman';
    }

    public function commandExists(string $command): bool
    {
        return Process::run("command -v $command")->successful();
    }

    protected function getSettings(): array
    {
        return app(\App\Services\SettingsService::class)->all();
    }

    /**
     * Spawns an external terminal in a mockable way.
     */
    public function spawnTerminal(string $command, string $callbackCmd): int|false
    {
        $terminals = ['konsole', 'alacritty', 'kitty', 'foot', 'gnome-terminal', 'xterm'];
        $foundTerminal = null;

        foreach ($terminals as $term) {
            if ($this->commandExists($term)) {
                $foundTerminal = $term;
                break;
            }
        }

        if (!$foundTerminal) {
            return false;
        }

        $settings = $this->getSettings();
        $autoClose = $settings['auto_close_terminal'] ?? true;
        $delay = $settings['terminal_close_delay'] ?? 10;

        if ($autoClose) {
            $postScript = "echo ''; echo 'Closing terminal in {$delay} seconds...'; sleep {$delay}";
        } else {
            $postScript = "echo ''; read -p 'Press Enter to close...'";
        }

        // Pass NativePHP env vars to the callback command so it can write to user local storage
        $envVars = [
            'NATIVEPHP_RUNNING' => env('NATIVEPHP_RUNNING') ? 'true' : 'false',
            'NATIVEPHP_STORAGE_PATH' => env('NATIVEPHP_STORAGE_PATH'),
            'NATIVEPHP_DATABASE_PATH' => env('NATIVEPHP_DATABASE_PATH'),
            'NATIVEPHP_SECRET' => env('NATIVEPHP_SECRET'),
            'NATIVEPHP_API_URL' => env('NATIVEPHP_API_URL'),
        ];
        $envPrefix = '';
        foreach ($envVars as $key => $val) {
            if ($val !== null && $val !== '' && $val !== 'false') {
                $envPrefix .= "{$key}=" . escapeshellarg($val) . " ";
            }
        }
        if (!empty($envPrefix) && str_contains($callbackCmd, 'artisan')) {
            $callbackCmd = $envPrefix . $callbackCmd;
        }

        $terminalCmd = match ($foundTerminal) {
            'konsole' => "konsole -e bash -c " . escapeshellarg("$command; $callbackCmd; $postScript"),
            'gnome-terminal' => "gnome-terminal -- bash -c " . escapeshellarg("$command; $callbackCmd; $postScript"),
            default => "$foundTerminal -e bash -c " . escapeshellarg("$command; $callbackCmd; $postScript")
        };

        // Detach from PHP's lifecycle with & and capture the real PID via `echo $!`
        // Symfony\Process would kill the child process on __destruct when the request ends
        $pid = (int) shell_exec($terminalCmd . " > /dev/null 2>&1 & echo $!");
        
        return $pid ?: 9999;
    }

    public function getIcon(string $name, bool $isFlatpak = false, bool $allowRemote = false): string
    {
        if ($isFlatpak) {
            $nameLower = strtolower($name);
            $isRuntime = str_contains($nameLower, 'platform') 
                || str_contains($nameLower, 'sdk') 
                || str_contains($nameLower, 'theme') 
                || str_contains($nameLower, 'extension') 
                || str_contains($nameLower, 'plugin')
                || str_contains($nameLower, 'driver')
                || str_contains($nameLower, 'codecs')
                || str_contains($nameLower, 'baseapp');

            if ($isRuntime) {
                return '';
            }

            return "https://dl.flathub.org/repo/appstream/x86_64/icons/128x128/{$name}.png";
        }

        $id = $this->getAppId($name, $allowRemote);
        if ($id) {
            return "https://dl.flathub.org/repo/appstream/x86_64/icons/128x128/{$id}.png";
        }

        return '';
    }

    public function getScreenshots(string $name, bool $isFlatpak = false, bool $allowRemote = true): array
    {
        $id = $isFlatpak ? $name : $this->getAppId($name, $allowRemote);
        if (!$id) return [];

        if (!$allowRemote) {
            return \Illuminate\Support\Facades\Cache::get("pkg_screenshots_" . $id, []);
        }

        return Cache::remember("pkg_screenshots_" . $id, 86400, function () use ($id) {
            try {
                $res = Process::run("curl -s --max-time 10 https://flathub.org/api/v2/appstream/" . escapeshellarg($id));
                if ($res->failed()) return [];

                $data = json_decode($res->output(), true);
                if (empty($data['screenshots'])) return [];

                $screens = [];
                foreach (array_slice($data['screenshots'], 0, 3) as $s) {
                    $src = null;
                    if (!empty($s['sizes'])) {
                        foreach ($s['sizes'] as $size) {
                            if (!empty($size['src'])) { $src = $size['src']; break; }
                        }
                    }
                    if (!$src && !empty($s['thumbnails'])) {
                        foreach ($s['thumbnails'] as $thumb) {
                            if (!empty($thumb['url'])) { $src = $thumb['url']; break; }
                        }
                    }
                    if (!$src && !empty($s['url'])) {
                        $src = $s['url'];
                    }
                    if ($src) $screens[] = $src;
                }
                return $screens;
            } catch (\Exception $e) {
                return [];
            }
        });
    }

    public function getAppId(string $name, bool $allowRemote = false): ?string
    {
        $cleanName = strtolower(trim($name));
        $mapping = $this->getAppMapping();

        if (isset($mapping[$cleanName])) {
            return $mapping[$cleanName];
        }

        // Se busca remota não for explicitamente permitida (ex: buscas ou listagens massivas),
        // retorna o app_id do cache se já tiver sido consultado antes, evitando travar a thread com curl
        if (!$allowRemote) {
            return \Illuminate\Support\Facades\Cache::get("pkg_flathub_appid_" . $cleanName, null);
        }

        // Dynamic Flathub lookup cached for 7 days
        return Cache::remember("pkg_flathub_appid_" . $cleanName, 604800, function () use ($cleanName) {
            try {
                $searchQuery = preg_replace('/(-bin|-git|-gtk|-qt|-launcher|-community-edition)$/i', '', $cleanName);
                $res = Process::run("curl -s --max-time 4 -X POST -H 'Content-Type: application/json' -d " . escapeshellarg(json_encode(['query' => $searchQuery])) . " https://flathub.org/api/v2/search");
                if ($res->successful()) {
                    $data = json_decode($res->output(), true);
                    if (!empty($data['hits'][0]['id'])) {
                        return str_replace('_', '.', $data['hits'][0]['id']);
                    }
                }
            } catch (\Exception $e) {
                // Silently fallback
            }
            return null;
        });
    }

    protected function getAppMapping(): array
    {
        return [
            // Popular & Gaming
            'steam'                           => 'com.valvesoftware.Steam',
            'discord'                         => 'com.discordapp.Discord',
            'discord-canary'                  => 'com.discordapp.DiscordCanary',
            'spotify'                         => 'com.spotify.Client',
            'heroic'                          => 'com.heroicgameslauncher.hgl',
            'lutris'                          => 'net.lutris.Lutris',
            'obs-studio'                      => 'com.obsproject.Studio',
            'blender'                         => 'org.blender.Blender',

            // Browsers
            'firefox'                         => 'org.mozilla.firefox',
            'brave'                           => 'com.brave.Browser',
            'brave-bin'                       => 'com.brave.Browser',
            'chromium'                        => 'org.chromium.Chromium',
            'google-chrome'                   => 'com.google.Chrome',
            'vivaldi'                         => 'com.vivaldi.Vivaldi',
            'falkon'                          => 'org.kde.falkon',
            'torbrowser-launcher'             => 'com.github.micahflee.torbrowser-launcher',

            // Communications & Email
            'telegram-desktop'                => 'org.telegram.desktop',
            'neochat'                         => 'org.kde.neochat',
            'pidgin'                          => 'im.pidgin.Pidgin',
            'hexchat'                         => 'io.github.Hexchat',
            'konversation'                    => 'org.kde.konversation',
            'polari'                          => 'org.gnome.Polari',
            'thunderbird'                     => 'org.mozilla.Thunderbird',
            'evolution'                       => 'org.gnome.Evolution',
            'geary'                           => 'org.gnome.Geary',
            'kmail'                           => 'org.kde.kontact',

            // Media Players (Audio & Video)
            'vlc'                             => 'org.videolan.VLC',
            'mpv'                             => 'io.mpv.Mpv',
            'celluloid'                       => 'io.github.celluloid_player.Celluloid',
            'haruna'                          => 'org.kde.haruna',
            'smplayer'                        => 'info.smplayer.SMPlayer',
            'totem'                           => 'org.gnome.Totem',
            'kaffeine'                        => 'org.kde.kaffeine',
            'showtime'                        => 'org.gnome.Showtime',
            'kdenlive'                        => 'org.kde.kdenlive',
            'audacious'                       => 'org.atheme.audacious',
            'rhythmbox'                       => 'org.gnome.Rhythmbox3',
            'strawberry'                      => 'org.strawberrymusicplayer.strawberry',
            'elisa'                           => 'org.kde.elisa',
            'lollypop'                        => 'org.gnome.Lollypop',

            // Graphics & Photo
            'gimp'                            => 'org.gimp.GIMP',
            'inkscape'                        => 'org.inkscape.Inkscape',
            'krita'                           => 'org.kde.krita',
            'darktable'                       => 'org.darktable.Darktable',
            'rawtherapee'                     => 'com.rawtherapee.RawTherapee',
            'pinta'                           => 'com.github.PintaProject.Pinta',
            'kolourpaint'                     => 'org.kde.kolourpaint',
            'photoflare'                      => 'io.photoflare.photoflare',
            'digikam'                         => 'org.kde.digikam',
            'gwenview'                        => 'org.kde.gwenview',
            'shotwell'                        => 'org.gnome.Shotwell',
            'ristretto'                       => 'org.xfce.ristretto',
            'geeqie'                          => 'org.geeqie.Geeqie',
            'gthumb'                          => 'org.gnome.gThumb',
            'loupe'                           => 'org.gnome.Loupe',
            'eog'                             => 'org.gnome.eog',

            // Office & Productivity
            'libreoffice-fresh'               => 'org.libreoffice.LibreOffice',
            'libreoffice-still'               => 'org.libreoffice.LibreOffice',
            'calligra'                        => 'org.kde.calligra',
            'gnumeric'                        => 'org.gnumeric.Gnumeric',
            'evince'                          => 'org.gnome.Evince',
            'okular'                          => 'org.kde.okular',
            'gnucash'                         => 'org.gnucash.GnuCash',
            'homebank'                        => 'fr.free.Homebank',
            'kmymoney'                        => 'org.kde.kmymoney',
            'skrooge'                         => 'org.kde.skrooge',

            // Development & Text Editors
            'code'                            => 'com.visualstudio.code',
            'vscode'                          => 'com.visualstudio.code',
            'visual-studio-code-bin'          => 'com.visualstudio.code',
            'geany'                           => 'org.geany.Geany',
            'gedit'                           => 'org.gnome.gedit',
            'kate'                            => 'org.kde.kwrite',
            'mousepad'                        => 'org.xfce.mousepad',
            'kdevelop'                        => 'org.kde.kdevelop',
            'qtcreator'                       => 'io.qt.QtCreator',
            'gnome-builder'                   => 'org.gnome.Builder',
            'jetbrains-toolbox'               => 'com.jetbrains.Toolbox',
            'intellij-idea-community-edition'  => 'com.jetbrains.IntelliJ-IDEA-Community',
            'pycharm-community-edition'        => 'com.jetbrains.PyCharm-Community',
            'netbeans'                        => 'org.apache.netbeans',

            // Torrent & Downloads
            'qbittorrent'                     => 'org.qbittorrent.qBittorrent',
            'deluge-gtk'                      => 'org.deluge_torrent.deluge',
            'deluge'                          => 'org.deluge_torrent.deluge',
            'transmission-gtk'                => 'com.transmissionbt.Transmission',
            'transmission-qt'                 => 'com.transmissionbt.Transmission',
            'ktorrent'                        => 'org.kde.ktorrent',

            // Security & System Tools
            'bitwarden'                       => 'com.bitwarden.desktop',
            'keepassxc'                       => 'org.keepassxc.KeePassXC',
            'seahorse'                        => 'org.gnome.seahorse.Application',
            'gnome-boxes'                     => 'org.gnome.Boxes',
            'baobab'                          => 'org.gnome.baobab',
            'filelight'                       => 'org.kde.filelight',
            'krename'                         => 'org.kde.krename',

            // Math
            'gnome-calculator'                => 'org.gnome.Calculator',
            'kcalc'                           => 'org.kde.kcalc',
            'kalgebra'                        => 'org.kde.kalgebra',
            'octave'                          => 'org.octave.Octave',
            'geogebra'                        => 'org.geogebra.GeoGebra',
        ];
    }

    protected function humanReadableName(string $appId): string
    {
        $parts = explode('.', $appId);
        $last = end($parts);
        return trim(preg_replace('/([A-Z])/', ' $1', $last));
    }
}
