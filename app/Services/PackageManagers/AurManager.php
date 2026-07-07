<?php

namespace App\Services\PackageManagers;

use Illuminate\Support\Facades\Process;

class AurManager extends BaseManager
{
    public function search(string $query): array
    {
        if (empty($query)) {
            return [];
        }

        $helper = $this->getHelper();
        $packages = [];

        if ($helper !== 'pacman') {
            $result = Process::run("LC_ALL=C $helper -Ss " . escapeshellarg($query));
            if ($result->successful()) {
                $lines = explode("\n", trim($result->output()));
                $installedNames = $this->getInstalledNames();

                for ($i = 0; $i < count($lines); $i++) {
                    $line = trim($lines[$i]);
                    if (empty($line)) continue;

                    if (preg_match('/^aur\/([^\s]+)\s+([^\s]+)/i', $line, $matches)) {
                        $name = $matches[1];
                        $version = $matches[2];

                        $packages[] = [
                            'repo'        => 'aur',
                            'name'        => $name,
                            'version'     => $version,
                            'is_aur'      => true,
                            'is_flatpak'  => false,
                            'installed'   => in_array($name, $installedNames),
                            'description' => isset($lines[$i+1]) ? trim($lines[$i+1]) : '',
                            'icon_url'    => $this->getIcon($name, false),
                            'screenshots' => $this->getScreenshots($name, false),
                        ];
                        $i++;
                    }
                }
                return $packages;
            }
        }

        // Fallback: busca via AUR API RPC v5
        $res = Process::run("curl -s --max-time 10 \"https://aur.archlinux.org/rpc/?v=5&type=search&arg=" . urlencode($query) . "\"");
        if ($res->successful()) {
            $json = json_decode($res->output(), true);
            if (!empty($json['results'])) {
                $installedNames = $this->getInstalledNames();
                foreach (array_slice($json['results'], 0, 50) as $item) {
                    $name = $item['Name'];
                    $packages[] = [
                        'repo'        => 'aur',
                        'name'        => $name,
                        'version'     => $item['Version'] ?? 'Unknown',
                        'is_aur'      => true,
                        'is_flatpak'  => false,
                        'installed'   => in_array($name, $installedNames),
                        'description' => $item['Description'] ?? '',
                        'icon_url'    => $this->getIcon($name, false),
                        'screenshots' => $this->getScreenshots($name, false),
                    ];
                }
            }
        }

        return $packages;
    }

    public function install(string $packageName): int|false
    {
        $helper = $this->getHelper();
        $artisan = base_path('artisan');
        $php = PHP_BINARY;
        $callback = "{$php} {$artisan} package:finished " . escapeshellarg($packageName) . " installed";

        $command = ($helper === 'paru')
            ? "paru -S " . escapeshellarg($packageName)
            : "yay -S " . escapeshellarg($packageName);

        return $this->spawnTerminal($command, $callback);
    }

    public function remove(string $packageName): int|false
    {
        $artisan = base_path('artisan');
        $php = PHP_BINARY;
        $callback = "{$php} {$artisan} package:finished " . escapeshellarg($packageName) . " removed";

        $command = "sudo pacman -Rns --noconfirm " . escapeshellarg($packageName);
        return $this->spawnTerminal($command, $callback);
    }

    public function getDetails(string $packageName): ?array
    {
        $helper = $this->getHelper();
        $result = Process::run("LC_ALL=C $helper -Si " . escapeshellarg($packageName));

        if ($result->failed()) {
            $result = Process::run("LC_ALL=C pacman -Qi " . escapeshellarg($packageName));
            if ($result->failed()) {
                // Fallback: busca via AUR API RPC v5 info
                $res = Process::run("curl -s --max-time 10 \"https://aur.archlinux.org/rpc/?v=5&type=info&arg[]=" . urlencode($packageName) . "\"");
                if ($res->successful()) {
                    $json = json_decode($res->output(), true);
                    if (!empty($json['results'][0])) {
                        $item = $json['results'][0];
                        return [
                            'Repository'   => 'aur',
                            'Name'         => $item['Name'],
                            'Version'      => $item['Version'] ?? 'Unknown',
                            'Description'  => $item['Description'] ?? '',
                            'URL'          => $item['URL'] ?? '',
                            'Licenses'     => implode(', ', $item['License'] ?? ['GPL']),
                            'Build Date'   => isset($item['LastModified'])
                                ? date('Y-m-d H:i', $item['LastModified']) . ' UTC'
                                : '---',
                            'Maintainer'   => $item['Maintainer'] ?? 'Unknown',
                            'is_installed' => $this->isInstalled($packageName),
                            'icon_url'     => $this->getIcon($packageName, false),
                            'screenshots'  => $this->getScreenshots($packageName, false),
                        ];
                    }
                }
                return null;
            }
        }

        $output = $result->output();
        $lines = explode("\n", $output);
        $details = [];

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

        // Map local language keys (like Portuguese) to standard English template keys
        $keyMap = [
            'Repositório'        => 'Repository',
            'Nome'               => 'Name',
            'Versão'             => 'Version',
            'Descrição'          => 'Description',
            'Grupos'             => 'Groups',
            'Licenças'           => 'Licenses',
            'Mantenedor'         => 'Maintainer',
            'Última modificação' => 'Build Date',
            'Last Modified'      => 'Build Date',
        ];

        foreach ($keyMap as $localKey => $standardKey) {
            if (isset($details[$localKey]) && !isset($details[$standardKey])) {
                $details[$standardKey] = $details[$localKey];
            }
        }

        $details['icon_url']    = $this->getIcon($packageName, false);
        $details['screenshots'] = $this->getScreenshots($packageName, false);
        $details['is_installed'] = $this->isInstalled($packageName);

        // Still empty? Supplement from AUR API LastModified timestamp
        if (empty($details['Build Date'])) {
            $res = Process::run("curl -s --max-time 10 \"https://aur.archlinux.org/rpc/?v=5&type=info&arg[]=" . urlencode($packageName) . "\"");
            if ($res->successful()) {
                $json = json_decode($res->output(), true);
                $lastModified = $json['results'][0]['LastModified'] ?? null;
                if ($lastModified) {
                    $details['Build Date'] = date('Y-m-d H:i', $lastModified) . ' UTC';
                }
            }
        }

        return $details ?: null;
    }

    public function isInstalled(string $packageName): bool
    {
        $checkInstalled = Process::run("pacman -Qq " . escapeshellarg($packageName));
        return $checkInstalled->successful();
    }

    public function runInBackground(string $packageName, string $logFile): int
    {
        $installFailed = __('console_install_failed');
        $helper = $this->getHelper();
        if ($helper === 'paru') {
            $confPath = $this->getParuConfPath();
            $cmd = "echo \"1\" | PARU_CONF=" . escapeshellarg($confPath) . " paru --noconfirm -S " . escapeshellarg($packageName);
        } else {
            $cmd = "echo \"1\" | yay --sudo pkexec --noedit --noconfirm -S " . escapeshellarg($packageName);
        }
        $fullCommand = "( $cmd >> " . escapeshellarg($logFile) . " 2>&1"
            . " || echo " . escapeshellarg($installFailed) . " >> " . escapeshellarg($logFile)
            . " ; echo '__PROCESS_DONE__' >> " . escapeshellarg($logFile)
            . " ) &";

        shell_exec($fullCommand);
        return 1;
    }

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

    private function getInstalledNames(): array
    {
        $installedResult = Process::run("pacman -Qq");
        return $installedResult->successful() ? explode("\n", trim($installedResult->output())) : [];
    }

    public function getComments(string $packageName, int $limit = 50): array
    {
        return \Illuminate\Support\Facades\Cache::remember("aur_comments_{$packageName}", 1800, function () use ($packageName, $limit) {
            $res = Process::run("curl -s --max-time 15 'https://aur.archlinux.org/packages/" . urlencode($packageName) . "'");
            if ($res->failed()) return [];

            $html = $res->output();
            $comments = [];

            // Match comment headers: extract id, author, date
            preg_match_all(
                '/<h4\s+id="comment-(\d+)"\s+class="comment-header">\s*(.*?)\s+commented on\s+<a[^>]+class="date">([^<]+)<\/a>/s',
                $html,
                $headers,
                PREG_SET_ORDER
            );

            foreach (array_slice($headers, 0, $limit) as $header) {
                $id     = $header[1];
                $author = trim(strip_tags($header[2]));
                $date   = trim($header[3]);

                // Get matching content div for this comment id
                preg_match(
                    '/<div\s+id="comment-' . $id . '-content"[^>]*>(.*?)<\/div>\s*<\/div>/s',
                    $html,
                    $contentMatch
                );

                $content = '';
                if (!empty($contentMatch[1])) {
                    $content = trim(strip_tags($contentMatch[1]));
                    $content = preg_replace('/\s+/', ' ', $content);
                    $content = mb_substr($content, 0, 500);
                }

                if ($author && $content) {
                    $comments[] = [
                        'id'      => $id,
                        'author'  => $author,
                        'date'    => $date,
                        'content' => $content,
                        'url'     => "https://aur.archlinux.org/packages/{$packageName}#comment-{$id}",
                    ];
                }
            }

            return $comments;
        });
    }
}
