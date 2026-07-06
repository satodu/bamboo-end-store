<?php

namespace App\Services\PackageManagers;

use Illuminate\Support\Facades\Process;

class PacmanManager extends BaseManager
{
    public function search(string $query): array
    {
        if (empty($query)) {
            return [];
        }

        $result = Process::run("LC_ALL=C pacman -Ss " . escapeshellarg($query));
        if ($result->failed()) {
            return [];
        }

        $output = $result->output();
        $lines = explode("\n", trim($output));
        $data = [];

        $installedNames = $this->getInstalledNames();

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
                    'is_aur'      => false,
                    'is_flatpak'  => false,
                    'installed'   => in_array($name, $installedNames),
                    'description' => isset($lines[$i+1]) ? trim($lines[$i+1]) : '',
                    'icon_url'    => $this->getIcon($name, false),
                    'screenshots' => $this->getScreenshots($name, false),
                ];
                $i++; 
            }
        }

        return $data;
    }

    public function install(string $packageName): int|false
    {
        $artisan = base_path('artisan');
        $php = PHP_BINARY;
        $callback = "{$php} {$artisan} package:finished " . escapeshellarg($packageName) . " installed";
        
        $command = "sudo pacman -S --noconfirm " . escapeshellarg($packageName);
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
        $result = Process::run("LC_ALL=C pacman -Si " . escapeshellarg($packageName));
        if ($result->failed()) {
            $result = Process::run("LC_ALL=C pacman -Qi " . escapeshellarg($packageName));
            if ($result->failed()) {
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

        $details['icon_url'] = $this->getIcon($packageName, false);
        $details['screenshots'] = $this->getScreenshots($packageName, false);
        $details['is_installed'] = $this->isInstalled($packageName);

        return $details ?: null;
    }

    public function isInstalled(string $packageName): bool
    {
        $checkInstalled = Process::run("pacman -Qq " . escapeshellarg($packageName));
        return $checkInstalled->successful();
    }

    public function runInBackground(string $packageName, string $logFile): int
    {
        $authFailed = __('console_auth_failed');
        $cmd = "pacman -S --noconfirm " . escapeshellarg($packageName);
        $inner = $cmd . " >> " . escapeshellarg($logFile) . " 2>&1";
        $fullCommand = "( pkexec sh -c " . escapeshellarg($inner)
            . " || echo " . escapeshellarg($authFailed) . " >> " . escapeshellarg($logFile)
            . " ; echo '__PROCESS_DONE__' >> " . escapeshellarg($logFile)
            . " ) & echo $!";

        // Detach from PHP lifecycle: shell_exec with & lets process survive past HTTP request
        $pid = (int) shell_exec($fullCommand);
        return $pid ?: 1;
    }

    private function getInstalledNames(): array
    {
        $installedResult = Process::run("pacman -Qq");
        return $installedResult->successful() ? explode("\n", trim($installedResult->output())) : [];
    }
}
