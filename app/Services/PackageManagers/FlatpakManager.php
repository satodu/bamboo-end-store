<?php

namespace App\Services\PackageManagers;

use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Cache;

class FlatpakManager extends BaseManager
{
    public function search(string $query): array
    {
        if (empty($query) || !$this->commandExists('flatpak')) {
            return [];
        }

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

        $apiResults = $this->searchFlathubApi($query);

        $merged = $cliResults;
        $existingIds = array_column($cliResults, 'name');
        foreach ($apiResults as $pkg) {
            if (!in_array($pkg['name'], $existingIds)) {
                $merged[] = $pkg;
            }
        }

        return $merged;
    }

    public function install(string $packageName): int|false
    {
        $artisan = base_path('artisan');
        $php = PHP_BINARY;
        $callback = "{$php} {$artisan} package:finished " . escapeshellarg($packageName) . " installed";

        $command = "flatpak install -y flathub " . escapeshellarg($packageName);
        return $this->spawnTerminal($command, $callback);
    }

    public function remove(string $packageName): int|false
    {
        $artisan = base_path('artisan');
        $php = PHP_BINARY;
        $callback = "{$php} {$artisan} package:finished " . escapeshellarg($packageName) . " removed";

        $command = "flatpak uninstall -y " . escapeshellarg($packageName);
        return $this->spawnTerminal($command, $callback);
    }

    public function getDetails(string $packageName): ?array
    {
        $apiDetails = $this->getFlathubApiDetails($packageName);

        $cliInstalled = false;
        $installedSize = null;
        $downloadSize = null;
        
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

        $details['icon_url'] = $this->getIcon($packageName, true);
        $details['screenshots'] = $this->getScreenshots($packageName, true);
        $details['is_installed'] = $this->isInstalled($packageName);

        return $details ?: null;
    }

    public function isInstalled(string $packageName): bool
    {
        $checkInstalled = Process::run("flatpak list --columns=application | grep -q " . escapeshellarg($packageName));
        return $checkInstalled->successful();
    }

    public function getInstalledFlatpaks(): array
    {
        if (!$this->commandExists('flatpak')) {
            return [];
        }

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

    public function getFlathubApiDetails(string $appId): ?array
    {
        $cacheKey = "flathub_api_{$appId}";
        return Cache::remember($cacheKey, 86400, function () use ($appId) {
            try {
                $res = Process::run("curl -s --max-time 10 https://flathub.org/api/v2/appstream/" . escapeshellarg($appId));
                if ($res->failed()) return null;

                $data = json_decode($res->output(), true);
                if (empty($data) || empty($data['id'])) return null;

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

    public function runInBackground(string $packageName, string $logFile): int
    {
        $authFailed = __('console_auth_failed');
        $cmd = "flatpak install -y flathub " . escapeshellarg($packageName);
        $inner = $cmd . " >> " . escapeshellarg($logFile) . " 2>&1";
        $fullCommand = "( pkexec sh -c " . escapeshellarg($inner)
            . " || echo " . escapeshellarg($authFailed) . " >> " . escapeshellarg($logFile)
            . " ; echo '__PROCESS_DONE__' >> " . escapeshellarg($logFile)
            . " ) & echo $!";

        // Detach from PHP lifecycle: shell_exec with & lets process survive past HTTP request
        $pid = (int) shell_exec($fullCommand);
        return $pid ?: 1;
    }
}
