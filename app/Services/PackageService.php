<?php

namespace App\Services;

use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Services\PackageManagers\PackageManagerFactory;

class PackageService
{
    /**
     * Detects which AUR helper is available (paru or yay)
     */
    public function getHelper(): string
    {
        return PackageManagerFactory::make(true, false)->getHelper();
    }

    /**
     * Checks if a command exists on the system
     */
    public function commandExists(string $command): bool
    {
        return Process::run("command -v $command")->successful();
    }

    /**
     * Runs a full system update (eos-update or yay/paru)
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

            // Build the terminal update script
            // Resolve translatable console messages in PHP before embedding in shell
            $msgEosUpdate   = __('console_starting_eos_update');
            $msgAurUpdate   = str_replace('{helper}', $helper, __('console_updating_aur'));
            $msgSysUpdate   = str_replace('{helper}', $helper, __('console_starting_update'));
            $msgFlatpak     = __('console_updating_flatpak');
            $msgOrphans     = __('console_removing_orphans');
            $msgNoOrphans   = __('console_no_orphans');

            $script = "if command -v eos-update > /dev/null; then " .
                      "  echo " . escapeshellarg($msgEosUpdate) . "; eos-update; " .
                      "  if [ \"{$helper}\" != \"pacman\" ]; then " .
                      "    echo " . escapeshellarg($msgAurUpdate) . "; " .
                      "    {$helper} -Sua; " .
                      "  fi; " .
                      "else " .
                      "  echo " . escapeshellarg($msgSysUpdate) . "; " .
                      "  {$helper} -Syu; " .
                      "fi; " .
                      "if command -v flatpak > /dev/null; then " .
                      "  echo " . escapeshellarg($msgFlatpak) . "; " .
                      "  flatpak update; " .
                      "fi; " .
                      "orphans=\$(pacman -Qtdq); " .
                      "if [ -n \"\$orphans\" ]; then " .
                      "  echo " . escapeshellarg($msgOrphans) . "; " .
                      "  sudo pacman -Rns \$orphans; " .
                      "else " .
                      "  echo " . escapeshellarg($msgNoOrphans) . "; " .
                      "fi; " .
                      "$callback";

            $settings = $this->getSettings();
            $autoClose = $settings['auto_close_terminal'] ?? true;
            $delay = $settings['terminal_close_delay'] ?? 10;

            if ($autoClose) {
                $closingMsg = str_replace('{delay}', $delay, __('console_closing_terminal'));
                $postScript = "echo ''; echo " . escapeshellarg($closingMsg) . "; sleep {$delay}";
            } else {
                $pressEnter = __('console_press_enter');
                $postScript = "echo ''; read -p " . escapeshellarg($pressEnter);
            }

            $terminalCmd = match ($foundTerminal) {
                'konsole' => "konsole -e bash -c " . escapeshellarg("$script; $postScript"),
                'gnome-terminal' => "gnome-terminal -- bash -c " . escapeshellarg("$script; $postScript"),
                default => "$foundTerminal -e bash -c " . escapeshellarg("$script; $postScript")
            };

            shell_exec($terminalCmd . " > /dev/null 2>&1 &");
            app(\App\Services\UserStateMachine::class)->transitionTo(\App\Enums\UserState::UPDATING, 'System Update');
            return true;
        }

        return false;
    }

    /**
     * Searches packages using the best available helper and optionally Flatpak
     */
    public function search(string $query, bool $includeFlatpak = false): array
    {
        if (empty($query)) {
            return [];
        }

        return Cache::remember("pkg_search_" . md5($query . ($includeFlatpak ? '_flat' : '')), 300, function () use ($query, $includeFlatpak) {
            $pacman = PackageManagerFactory::make(false, false);
            $aur = PackageManagerFactory::make(true, false);

            $packages = array_merge(
                $pacman->search($query),
                $aur->search($query)
            );

            if ($includeFlatpak && $this->commandExists('flatpak')) {
                $flatpak = PackageManagerFactory::make(false, true);
                $packages = array_merge($packages, $flatpak->search($query));
            }

            return $packages;
        });
    }

    /**
     * Searches packages on Flathub
     */
    public function searchFlatpak(string $query): array
    {
        return PackageManagerFactory::make(false, true)->search($query);
    }

    /**
     * Lists all installed packages
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

            // Append installed Flatpaks
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
        return (new PackageManagers\FlatpakManager())->getInstalledFlatpaks();
    }

    /**
     * Instala um pacote
     */
    public function install(string $packageName, bool $isAur = false, bool $isFlatpak = false): int|false
    {
        Cache::put("installing_{$packageName}", true, 1800);
        app(\App\Services\UserStateMachine::class)->transitionTo(\App\Enums\UserState::INSTALLING, $packageName);
        return PackageManagerFactory::make($isAur, $isFlatpak)->install($packageName);
    }

    /**
     * Remove um pacote
     */
    public function remove(string $packageName, bool $isFlatpak = false): int|false
    {
        Cache::put("installing_{$packageName}", true, 600);
        app(\App\Services\UserStateMachine::class)->transitionTo(\App\Enums\UserState::UNINSTALLING, $packageName);
        
        $manager = PackageManagerFactory::make(false, $isFlatpak);
        $pid = $manager->remove($packageName);
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
        Cache::forget("pkg_installed_list");
    }

    /**
     * Fetches the icon URL for a package
     */
    public function getIcon(string $name, bool $isFlatpak = false): string
    {
        return PackageManagerFactory::make(false, $isFlatpak)->getIcon($name, $isFlatpak);
    }

    /**
     * Busca screenshots
     */
    public function getScreenshots(string $name, bool $isFlatpak = false): array
    {
        return PackageManagerFactory::make(false, $isFlatpak)->getScreenshots($name, $isFlatpak);
    }

    /**
     * Busca flatpaks no Flathub via API HTTP (suporta display names como "Jellyfin Desktop")
     */
    public function searchFlathubApi(string $query): array
    {
        return (new PackageManagers\FlatpakManager())->searchFlathubApi($query);
    }

    /**
     * Executa um comando em background com sentinela no log
     */
    public function runInBackground(string $name, bool $isAur = false, bool $isFlatpak = false, string $logFile): int
    {
        app(\App\Services\UserStateMachine::class)->transitionTo(\App\Enums\UserState::INSTALLING, $name);
        return PackageManagerFactory::make($isAur, $isFlatpak)->runInBackground($name, $logFile);
    }

    /**
     * Detalhes
     */
    public function getPackageDetails(string $packageName, bool $isAur = false, bool $isFlatpak = false): ?array
    {
        $cacheKey = "pkg_details_{$packageName}";

        if (Cache::has($cacheKey)) {
            $cached = Cache::get($cacheKey);
            if ($cached !== null) return $cached;
            Cache::forget($cacheKey);
        }

        $details = PackageManagerFactory::make($isAur, $isFlatpak)->getDetails($packageName);

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
        return $this->runBambooUpdate() ? 1 : false;
    }

    private function getSettings(): array
    {
        return app(\App\Services\SettingsService::class)->all();
    }
}
