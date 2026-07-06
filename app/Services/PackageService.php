<?php

namespace App\Services;

use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class PackageService
{
    protected $helper = null;

    /**
     * Detecta qual AUR helper está disponível (paru ou yay)
     */
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

    /**
     * Verifica se um comando existe no sistema
     */
    public function commandExists(string $command): bool
    {
        return Process::run("command -v $command")->successful();
    }

    /**
     * Roda a atualização do sistema (eos-update ou yay/paru)
     */
    public function runBambooUpdate(): bool
    {
        $terminals = ['konsole', 'alacritty', 'kitty', 'foot', 'gnome-terminal', 'xterm'];
        $foundTerminal = null;

        foreach ($terminals as $term) {
            if ($this->commandExists($term)) {
                $foundTerminal = $term;
                break;
            }
        }

        if ($foundTerminal) {
            $helper = $this->getHelper();
            $artisan = base_path('artisan');
            $php = PHP_BINARY;
            $callback = "{$php} {$artisan} package:finished 'System Update'";

            // Script inteligente para o terminal
            $script = "if command -v eos-update > /dev/null; then " .
                      "  echo 'Starting EndeavourOS System Update...'; eos-update; " .
                      "  if [ \"{$helper}\" != \"pacman\" ]; then " .
                      "    echo 'Updating AUR packages via {$helper}...'; " .
                      "    {$helper} -Sua; " .
                      "  fi; " .
                      "else " .
                      "  echo 'Starting System Update via {$helper}...'; " .
                      "  {$helper} -Syu; " .
                      "fi; " .
                      "if command -v flatpak > /dev/null; then " .
                      "  echo 'Updating Flatpak applications...'; " .
                      "  flatpak update; " .
                      "fi; " .
                      "orphans=\$(pacman -Qtdq); " .
                      "if [ -n \"\$orphans\" ]; then " .
                      "  echo 'Removing orphan packages...'; " .
                      "  sudo pacman -Rns \$orphans; " .
                      "else " .
                      "  echo 'No orphan packages to remove.'; " .
                      "fi; " .
                      "$callback";

            $terminalCmd = match ($foundTerminal) {
                'konsole' => "konsole -e bash -c " . escapeshellarg("$script; sleep 5"),
                'gnome-terminal' => "gnome-terminal -- bash -c " . escapeshellarg("$script; sleep 5"),
                default => "$foundTerminal -e bash -c " . escapeshellarg("$script; sleep 5")
            };

            shell_exec($terminalCmd . " > /dev/null 2>&1 &");
            return true;
        }

        return false;
    }

    /**
     * Busca pacotes usando o melhor helper disponível e opcionalmente flatpak
     */
    public function search(string $query, bool $includeFlatpak = false): array
    {
        if (empty($query)) {
            return [];
        }

        $packages = Cache::remember("pkg_search_" . md5($query), 300, function () use ($query) {
            $helper = $this->getHelper();
            $result = Process::run("LC_ALL=C $helper -Ss " . escapeshellarg($query));
            
            if ($result->failed()) {
                return [];
            }

            $output = $result->output();
            $lines = explode("\n", trim($output));
            $data = [];

            $installedResult = Process::run("pacman -Qq");
            $installedNames = $installedResult->successful() ? explode("\n", trim($installedResult->output())) : [];

            for ($i = 0; $i < count($lines); $i++) {
                $line = trim($lines[$i]);
                if (empty($line)) continue;

                if (preg_match('/^([^\s\/]+)\/([^\s]+)\s+([^\s]+)/', $line, $matches)) {
                    $repo = $matches[1];
                    $name = $matches[2];
                    $version = $matches[3];
                    
                    $data[] = [
                        'repo'        => $repo,
                        'name'        => $name,
                        'version'     => $version,
                        'is_aur'      => strtolower($repo) === 'aur',
                        'is_flatpak'  => false,
                        'installed'   => in_array($name, $installedNames),
                        'description' => isset($lines[$i+1]) ? trim($lines[$i+1]) : '',
                        'icon_url'    => $this->getIcon($name, false),
                        'screenshots' => $this->getScreenshots($name, false), // fast: local mapping only, no API call
                    ];
                    $i++; 
                }
            }

            return $data;
        });

        if ($includeFlatpak && $this->commandExists('flatpak')) {
            $flatpaks = $this->searchFlatpak($query);
            $packages = array_merge($packages, $flatpaks);
        }

        return $packages;
    }

    /**
     * Busca pacotes no Flathub
     */
    public function searchFlatpak(string $query): array
    {
        $cliResults = Cache::remember("flatpak_search_" . md5($query), 300, function () use ($query) {
            $result = Process::run("flatpak search --columns=name,application,version,description " . escapeshellarg($query));

            if ($result->failed()) return [];

            $lines = explode("\n", trim($result->output()));
            $packages = [];

            $installed = $this->getInstalledFlatpaks();
            $installedIds = array_column($installed, 'name');

            foreach ($lines as $line) {
                $parts = explode("\t", $line);
                if (count($parts) >= 4) {
                    $appId = trim($parts[1]);
                    $packages[] = [
                        'repo'         => 'flathub',
                        'name'         => $appId,
                        'display_name' => trim($parts[0]),
                        'version'      => trim($parts[2]),
                        'is_aur'       => false,
                        'is_flatpak'   => true,
                        'installed'    => in_array($appId, $installedIds),
                        'description'  => trim($parts[3]),
                        'icon_url'     => $this->getIcon($appId, true),
                    ];
                }
            }
            return $packages;
        });

        // Mescla com resultados da API Flathub (suporta busca por display name)
        $apiResults = $this->searchFlathubApi($query);

        // Deduplica por app ID
        $merged = $cliResults;
        $existingIds = array_column($cliResults, 'name');
        foreach ($apiResults as $pkg) {
            if (!in_array($pkg['name'], $existingIds)) {
                $merged[] = $pkg;
            }
        }

        return $merged;
    }

    /**
     * Lista todos os pacotes instalados
     */
    public function getInstalledPackages(): array
    {
        return Cache::remember("pkg_installed_list", 60, function () {
            $result = Process::run("LC_ALL=C pacman -Q");
            if ($result->failed()) return [];

            $foreignResult = Process::run("LC_ALL=C pacman -Qm");
            $foreignNames = [];
            if ($foreignResult->successful()) {
                foreach (explode("\n", trim($foreignResult->output())) as $line) {
                    $parts = explode(' ', trim($line));
                    if (!empty($parts[0])) $foreignNames[] = $parts[0];
                }
            }

            $lines = explode("\n", trim($result->output()));
            $installed = [];

            foreach ($lines as $line) {
                $parts = explode(' ', trim($line));
                if (count($parts) >= 2) {
                    $name = $parts[0];
                    $installed[] = [
                        'name' => $name,
                        'version' => $parts[1],
                        'is_aur' => in_array($name, $foreignNames),
                        'is_flatpak' => false,
                    ];
                }
            }

            // Adiciona Flatpaks instalados
            if ($this->commandExists('flatpak')) {
                $installed = array_merge($installed, $this->getInstalledFlatpaks());
            }

            return $installed;
        });
    }

    /**
     * Lista flatpaks instalados
     */
    public function getInstalledFlatpaks(): array
    {
        $result = Process::run("flatpak list --columns=name,application,version,description");
        if ($result->failed()) return [];

        $lines = explode("\n", trim($result->output()));
        $installed = [];

        foreach ($lines as $line) {
            $parts = explode("\t", $line);
            if (count($parts) >= 3) {
                $installed[] = [
                    'repo' => 'flathub',
                    'name' => trim($parts[1]),
                    'display_name' => trim($parts[0]),
                    'version' => trim($parts[2]),
                    'is_aur' => false,
                    'is_flatpak' => true,
                    'installed' => true,
                    'description' => $parts[3] ?? '',
                ];
            }
        }

        return $installed;
    }

    /**
     * Helper para abrir terminal e retornar o PID do processo
     */
    private function openTerminal(string $command, string $callbackCmd): int|false
    {
        $terminals = ['konsole', 'alacritty', 'kitty', 'foot', 'gnome-terminal', 'xterm', 'footclient'];
        $foundTerminal = null;

        foreach ($terminals as $term) {
            if ($this->commandExists($term)) {
                $foundTerminal = $term;
                break;
            }
        }

        if (!$foundTerminal) return false;

        $terminalCmd = match ($foundTerminal) {
            'konsole' => "konsole -e bash -c \"$command; $callbackCmd; sleep 5\"",
            'gnome-terminal' => "gnome-terminal -- bash -c \"$command; $callbackCmd; sleep 5\"",
            default => "$foundTerminal -e bash -c \"$command; $callbackCmd; sleep 5\""
        };

        // Executa em background e captura o PID do terminal
        $pid = shell_exec($terminalCmd . " > /dev/null 2>&1 & echo $!");
        
        return $pid ? (int)trim($pid) : false;
    }

    /**
     * Retorna o caminho de um arquivo temporário de configuração do paru configurado para usar o pkexec.
     */
    private function getParuConfPath(): string
    {
        $path = storage_path('app/paru-pkexec.conf');
        if (!file_exists($path)) {
            $dir = dirname($path);
            if (!file_exists($dir)) {
                mkdir($dir, 0777, true);
            }
            file_put_contents($path, "[options]\nSkipReview\n[bin]\nSudo = pkexec\n");
        }
        return $path;
    }

    /**
     * Instala um pacote
     */
    public function install(string $packageName, bool $isAur = false, bool $isFlatpak = false): int|false
    {
        $helper = $this->getHelper();
        
        Cache::put("installing_{$packageName}", true, 1800);

        $artisan = base_path('artisan');
        $php = PHP_BINARY;
        $callbackCmd = "{$php} {$artisan} package:finished " . escapeshellarg($packageName);

        if ($isFlatpak) {
            $innerCommand = "flatpak install -y flathub " . escapeshellarg($packageName);
            return $this->openTerminal($innerCommand, $callbackCmd);
        }

        if ($isAur || $helper !== 'pacman') {
            $innerCommand = ($helper === 'paru') 
                ? "echo \"1\" | paru --skipreview --noconfirm -S " . escapeshellarg($packageName)
                : "echo \"1\" | yay --noedit --noconfirm -S " . escapeshellarg($packageName);
            
            return $this->openTerminal($innerCommand, $callbackCmd);
        }

        $innerCommand = "sudo pacman -S --noconfirm " . escapeshellarg($packageName);
        return $this->openTerminal($innerCommand, $callbackCmd);
    }

    /**
     * Remove um pacote
     */
    public function remove(string $packageName, bool $isFlatpak = false): int|false
    {
        $artisan = base_path('artisan');
        $php = PHP_BINARY;
        $callbackCmd = "{$php} {$artisan} package:finished " . escapeshellarg($packageName) . " uninstalled";

        Cache::put("installing_{$packageName}", true, 600);
        
        if ($isFlatpak) {
            $innerCommand = "flatpak uninstall -y " . escapeshellarg($packageName);
        } else {
            $innerCommand = "sudo pacman -Rns --noconfirm " . escapeshellarg($packageName);
        }

        $pid = $this->openTerminal($innerCommand, $callbackCmd);
        if ($pid) {
            $this->clearCache();
        }
        return $pid;
    }

    /**
     * Limpa caches
     */
    public function clearCache()
    {
        // Não limpamos tudo para não travar a Home
        \Illuminate\Support\Facades\Cache::forget("pkg_installed_list");
    }

    /**
     * Detalhes
     */
    /**
     * Retorna a URL do ícone para o pacote
     */
    public function getIcon(string $name, bool $isFlatpak = false): string
    {
        if ($isFlatpak) {
            return "https://dl.flathub.org/repo/appstream/x86_64/icons/128x128/{$name}.png";
        }

        // Mapeamento de apps populares para ícones do Flathub (melhor qualidade)
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

        return ''; // Fallback para emoji no Blade
    }

    /**
     * Busca screenshots do Flathub
     */
    public function getScreenshots(string $name, bool $isFlatpak = false): array
    {
        $id = $isFlatpak ? $name : null;
        
        if (!$id) {
            $mapping = [
                // Comunicação
                'discord'                  => 'com.discordapp.Discord',
                'discord-canary'           => 'com.discordapp.DiscordCanary',
                'telegram-desktop'         => 'org.telegram.desktop',
                // Música & Vídeo
                'spotify'                  => 'com.spotify.Client',
                'vlc'                      => 'org.videolan.VLC',
                'obs-studio'               => 'com.obsproject.Studio',
                'kdenlive'                 => 'org.kde.kdenlive',
                'mpv'                      => 'io.mpv.Mpv',
                // Gaming
                'steam'                    => 'com.valvesoftware.Steam',
                'heroic'                   => 'com.heroicgameslauncher.hgl',
                'lutris'                   => 'net.lutris.Lutris',
                // Browsers
                'brave-bin'                => 'com.brave.Browser',
                'brave'                    => 'com.brave.Browser',
                'firefox'                  => 'org.mozilla.firefox',
                'chromium'                 => 'org.chromium.Chromium',
                'google-chrome'            => 'com.google.Chrome',
                // Dev
                'visual-studio-code-bin'   => 'com.visualstudio.code',
                'vscode'                   => 'com.visualstudio.code',
                'code'                     => 'com.visualstudio.code',
                'jetbrains-toolbox'        => 'com.jetbrains.Toolbox',
                // Criação
                'gimp'                     => 'org.gimp.GIMP',
                'inkscape'                 => 'org.inkscape.Inkscape',
                'blender'                  => 'org.blender.Blender',
                'krita'                    => 'org.kde.krita',
                // Produtividade
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

                    // Formato 1: sizes[].src  (mais comum)
                    if (!empty($s['sizes'])) {
                        foreach ($s['sizes'] as $size) {
                            if (!empty($size['src'])) { $src = $size['src']; break; }
                        }
                    }

                    // Formato 2: thumbnails[].url
                    if (!$src && !empty($s['thumbnails'])) {
                        foreach ($s['thumbnails'] as $thumb) {
                            if (!empty($thumb['url'])) { $src = $thumb['url']; break; }
                        }
                    }

                    // Formato 3: url direta no objeto
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

    /**
     * Extrai um nome legível de um App ID (ex: page.codeberg.M23Snezhok.Vinyl → Vinyl)
     */
    private function humanReadableName(string $appId): string
    {
        $parts = explode('.', $appId);
        $last = end($parts);
        // CamelCase → palavras separadas (ex: JellyfinPlayer → Jellyfin Player)
        return trim(preg_replace('/([A-Z])/', ' $1', $last));
    }

    /**
     * Busca detalhes de um flatpak via Flathub API (funciona para qualquer app, instalado ou não)
     */
    private function getFlathubApiDetails(string $appId): ?array
    {
        $cacheKey = "flathub_api_{$appId}";
        return Cache::remember($cacheKey, 86400, function () use ($appId) {
            try {
                $res = Process::run("curl -s --max-time 10 https://flathub.org/api/v2/appstream/" . escapeshellarg($appId));
                if ($res->failed()) return null;

                $data = json_decode($res->output(), true);
                if (empty($data) || empty($data['id'])) return null;

                // Extrai screenshots com parsing resiliente
                $screenshots = [];
                foreach (array_slice($data['screenshots'] ?? [], 0, 3) as $s) {
                    $src = null;
                    if (!empty($s['sizes'])) {
                        foreach ($s['sizes'] as $size) {
                            if (!empty($size['src'])) { $src = $size['src']; break; }
                        }
                    }
                    if (!$src && !empty($s['thumbnails'])) {
                        foreach ($s['thumbnails'] as $t) {
                            if (!empty($t['url'])) { $src = $t['url']; break; }
                        }
                    }
                    if (!$src && !empty($s['url'])) $src = $s['url'];
                    if ($src) $screenshots[] = $src;
                }

                // Versão do release mais recente
                $version = 'Unknown';
                if (!empty($data['releases'][0]['version'])) {
                    $version = $data['releases'][0]['version'];
                }

                return [
                    'Name'          => $data['id'],
                    'display_name'  => $data['name'] ?? $this->humanReadableName($appId),
                    'Description'   => strip_tags($data['description'] ?? $data['summary'] ?? ''),
                    'Version'       => $version,
                    'Licenses'      => $data['project_license'] ?? 'Unknown',
                    'Architecture'  => 'x86_64',
                    'Repository'    => 'Flathub',
                    'Maintainer'    => $data['developer_name'] ?? $data['project_group'] ?? 'Unknown',
                    'screenshots'   => $screenshots,
                    'is_flatpak'    => true,
                    'is_installed'  => false,
                ];
            } catch (\Exception $e) {
                return null;
            }
        });
    }

    /**
     * Busca flatpaks no Flathub via API HTTP (suporta display names como "Jellyfin Desktop")
     */
    public function searchFlathubApi(string $query): array
    {
        return Cache::remember("flathub_api_search_" . md5($query), 300, function () use ($query) {
            try {
                $res = Process::run("curl -s --max-time 10 'https://flathub.org/api/v2/search?query=" . urlencode($query) . "'");
                if ($res->failed()) return [];

                $data = json_decode($res->output(), true);
                $hits = $data['hits'] ?? [];

                $installed = $this->getInstalledFlatpaks();
                $installedIds = array_column($installed, 'name');

                $packages = [];
                foreach (array_slice($hits, 0, 20) as $hit) {
                    $appId = $hit['id'] ?? $hit['app_id'] ?? null;
                    if (!$appId) continue;
                    $packages[] = [
                        'repo'         => 'flathub',
                        'name'         => $appId,
                        'display_name' => $hit['name'] ?? $this->humanReadableName($appId),
                        'version'      => $hit['version'] ?? 'Unknown',
                        'is_aur'       => false,
                        'is_flatpak'   => true,
                        'installed'    => in_array($appId, $installedIds),
                        'description'  => $hit['summary'] ?? '',
                        'icon_url'     => $this->getIcon($appId, true),
                    ];
                }
                return $packages;
            } catch (\Exception $e) {
                return [];
            }
        });
    }

    /**
     * Executa um comando em background com sentinela no log
     */
    public function runInBackground(string $name, bool $isAur = false, bool $isFlatpak = false, string $logFile): int
    {
        $helper = $this->getHelper();

        if ($isFlatpak) {
            $cmd = "flatpak install -y flathub " . escapeshellarg($name);
            $inner = $cmd . " >> " . escapeshellarg($logFile) . " 2>&1";
            $fullCommand = "( pkexec sh -c " . escapeshellarg($inner)
                . " || echo 'Autenticação cancelada ou falhou.' >> " . escapeshellarg($logFile)
                . " ; echo '__PROCESS_DONE__' >> " . escapeshellarg($logFile)
                . " ) &";
        } elseif ($isAur) {
            if ($helper === 'paru') {
                $confPath = $this->getParuConfPath();
                $cmd = "echo \"1\" | PARU_CONF=" . escapeshellarg($confPath) . " paru --noconfirm -S " . escapeshellarg($name);
            } else {
                $cmd = "echo \"1\" | yay --sudo pkexec --noedit --noconfirm -S " . escapeshellarg($name);
            }
            $fullCommand = "( $cmd >> " . escapeshellarg($logFile) . " 2>&1"
                . " || echo 'Instalação falhou ou foi cancelada.' >> " . escapeshellarg($logFile)
                . " ; echo '__PROCESS_DONE__' >> " . escapeshellarg($logFile)
                . " ) &";
        } else {
            $cmd = "pacman -S --noconfirm " . escapeshellarg($name);
            $inner = $cmd . " >> " . escapeshellarg($logFile) . " 2>&1";
            $fullCommand = "( pkexec sh -c " . escapeshellarg($inner)
                . " || echo 'Autenticação cancelada ou falhou.' >> " . escapeshellarg($logFile)
                . " ; echo '__PROCESS_DONE__' >> " . escapeshellarg($logFile)
                . " ) &";
        }

        shell_exec($fullCommand);
        return 1;
    }

    /**
     * Detalhes
     */
    public function getPackageDetails(string $packageName, bool $isAur = false, bool $isFlatpak = false): ?array
    {
        $cacheKey = "pkg_details_{$packageName}";

        // Não usar cache se estiver vazio (null cacheado causaria overlay sem abrir)
        if (Cache::has($cacheKey)) {
            $cached = Cache::get($cacheKey);
            if ($cached !== null) return $cached;
            Cache::forget($cacheKey);
        }

        $details = [];

        if ($isFlatpak) {
            // Fonte 1: Flathub API (funciona para qualquer app, instalado ou não)
            $apiDetails = $this->getFlathubApiDetails($packageName);

            // Fonte 2: flatpak CLI (complementa com info de instalação local)
            $cliInstalled = false;
            $installedSize = null;
            $cliResult = Process::run("LC_ALL=C flatpak info " . escapeshellarg($packageName));
            if ($cliResult->successful()) {
                $cliInstalled = true;
                foreach (explode("\n", $cliResult->output()) as $line) {
                    $trimmed = trim($line);
                    if (str_starts_with($trimmed, 'Installed Size:')) {
                        $installedSize = trim(str_replace('Installed Size:', '', $trimmed));
                    } elseif (str_starts_with($trimmed, 'Installed:') && !str_contains($trimmed, 'Installation:')) {
                        $installedSize = trim(str_replace('Installed:', '', $trimmed));
                    } elseif (str_starts_with($trimmed, 'Download Size:') || str_starts_with($trimmed, 'Download:')) {
                        $downloadSize = trim(preg_replace('/^Download( Size)?:/', '', $trimmed));
                    }
                }
            }

            if (!$apiDetails) {
                // Último fallback: flatpak remote-info
                $remoteResult = Process::run("LC_ALL=C flatpak remote-info flathub " . escapeshellarg($packageName));
                if ($remoteResult->failed()) return null;

                $lines = explode("\n", trim($remoteResult->output()));
                $details = ['Name' => $packageName, 'is_flatpak' => true];
                if (isset($lines[0]) && str_contains($lines[0], ' - ')) {
                    $parts = explode(' - ', $lines[0], 2);
                    $details['display_name'] = trim($parts[0]);
                    $details['Description'] = trim($parts[1]);
                }
                foreach ($lines as $line) {
                    if (str_contains($line, ':')) {
                        $parts = explode(':', $line, 2);
                        $key = trim($parts[0]);
                        $val = trim($parts[1]);
                        if ($key === 'Version') $details['Version'] = $val;
                        if ($key === 'License') $details['Licenses'] = $val;
                        if ($key === 'Installed') $details['Installed Size'] = $val;
                        if ($key === 'Arch') $details['Architecture'] = $val;
                        $details[$key] = $val;
                    }
                }
                $details['screenshots'] = $this->getScreenshots($packageName, true);
            } else {
                $details = $apiDetails;
                if ($installedSize) $details['Installed Size'] = $installedSize;
                if (isset($downloadSize)) $details['Download Size'] = $downloadSize;
            }

            $details['is_installed'] = $cliInstalled;
        } else {
            $helper = $this->getHelper();
            $result = Process::run("LC_ALL=C $helper -Si " . escapeshellarg($packageName));

            if ($result->failed()) {
                $result = Process::run("LC_ALL=C pacman -Qi " . escapeshellarg($packageName));
                if ($result->failed()) return null;
            }

            $output = $result->output();
            $lines = explode("\n", $output);

            foreach ($lines as $line) {
                if (str_contains($line, ' : ')) {
                    $parts = explode(' : ', $line, 2);
                    $key = trim($parts[0]);
                    $value = trim($parts[1]);
                    if (!empty($key)) {
                        $details[$key] = $value;
                    }
                }
            }
        }

        // Adiciona mídia (Ícone e Screenshots)
        $details['icon_url'] = $this->getIcon($packageName, $isFlatpak);
        $details['screenshots'] = $this->getScreenshots($packageName, $isFlatpak);

        if ($isFlatpak) {
            $checkInstalled = Process::run("flatpak list --columns=application | grep -q " . escapeshellarg($packageName));
            $details['is_installed'] = $checkInstalled->successful();
        } else {
            $checkInstalled = Process::run("pacman -Qq " . escapeshellarg($packageName));
            $details['is_installed'] = $checkInstalled->successful();
        }

        // Só cacheia se tiver dados válidos
        if (!empty($details)) {
            Cache::put($cacheKey, $details, 600);
        }

        return $details ?: null;
    }

    /**
     * Updates the system packages.
     */
    public function updateSystem(): int|false
    {
        $helper = $this->getHelper();
        $command = match ($helper) {
            'paru' => 'paru -Syu',
            'yay' => 'yay -Syu',
            default => 'sudo pacman -Syu'
        };

        $artisan = base_path('artisan');
        $php = PHP_BINARY;
        $callbackCmd = "{$php} {$artisan} package:finished 'System Update'";

        return $this->openTerminal($command, $callbackCmd);
    }
}
