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

    public function getIcon(string $name, bool $isFlatpak = false): string
    {
        if ($isFlatpak) {
            return "https://dl.flathub.org/repo/appstream/x86_64/icons/128x128/{$name}.png";
        }

        $mapping = [
            'discord' => 'com.discordapp.Discord',
            'spotify' => 'com.spotify.Client',
            'steam' => 'com.valvesoftware.Steam',
            'visual-studio-code-bin' => 'com.visualstudio.code',
            'vscode' => 'com.visualstudio.code',
            'brave-bin' => 'com.brave.Browser',
            'brave' => 'com.brave.Browser',
            'vlc' => 'org.videolan.VLC',
            'obs-studio' => 'com.obsproject.Studio',
            'telegram-desktop' => 'org.telegram.desktop',
            'firefox' => 'org.mozilla.firefox',
            'discord-canary' => 'com.discordapp.DiscordCanary',
            'gimp' => 'org.gimp.GIMP',
            'inkscape' => 'org.inkscape.Inkscape',
            'blender' => 'org.blender.Blender',
        ];

        $cleanName = strtolower($name);
        if (isset($mapping[$cleanName])) {
            return "https://dl.flathub.org/repo/appstream/x86_64/icons/128x128/{$mapping[$cleanName]}.png";
        }

        return '';
    }

    public function getScreenshots(string $name, bool $isFlatpak = false): array
    {
        $id = $isFlatpak ? $name : null;
        
        if (!$id) {
            $mapping = [
                'discord'                  => 'com.discordapp.Discord',
                'discord-canary'           => 'com.discordapp.DiscordCanary',
                'telegram-desktop'         => 'org.telegram.desktop',
                'spotify'                  => 'com.spotify.Client',
                'vlc'                      => 'org.videolan.VLC',
                'obs-studio'               => 'com.obsproject.Studio',
                'kdenlive'                 => 'org.kde.kdenlive',
                'mpv'                      => 'io.mpv.Mpv',
                'steam'                    => 'com.valvesoftware.Steam',
                'heroic'                   => 'com.heroicgameslauncher.hgl',
                'lutris'                   => 'net.lutris.Lutris',
                'brave-bin'                => 'com.brave.Browser',
                'brave'                    => 'com.brave.Browser',
                'firefox'                  => 'org.mozilla.firefox',
                'chromium'                 => 'org.chromium.Chromium',
                'google-chrome'            => 'com.google.Chrome',
                'visual-studio-code-bin'   => 'com.visualstudio.code',
                'vscode'                   => 'com.visualstudio.code',
                'code'                     => 'com.visualstudio.code',
                'jetbrains-toolbox'        => 'com.jetbrains.Toolbox',
                'gimp'                     => 'org.gimp.GIMP',
                'inkscape'                 => 'org.inkscape.Inkscape',
                'blender'                  => 'org.blender.Blender',
                'krita'                    => 'org.kde.krita',
                'libreoffice-fresh'        => 'org.libreoffice.LibreOffice',
                'libreoffice-still'        => 'org.libreoffice.LibreOffice',
                'thunderbird'              => 'org.mozilla.Thunderbird',
            ];
            $id = $mapping[strtolower($name)] ?? null;
        }

        if (!$id) return [];

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

    protected function humanReadableName(string $appId): string
    {
        $parts = explode('.', $appId);
        $last = end($parts);
        return trim(preg_replace('/([A-Z])/', ' $1', $last));
    }
}
