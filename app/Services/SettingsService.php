<?php

namespace App\Services;

class SettingsService
{
    protected array $settings = [];

    public function __construct()
    {
        $this->load();
    }

    public function load(): void
    {
        $path = storage_path('app/private/settings.json');
        if (!file_exists($path)) {
            $path = storage_path('app/settings.json');
        }

        // Fallback for CLI commands running outside the Electron context
        if (!file_exists($path)) {
            $home = getenv('HOME');
            if ($home) {
                $paths = [
                    "{$home}/.config/bamboo-end-store-dev/storage/app/private/settings.json",
                    "{$home}/.config/bamboo-end-store/storage/app/private/settings.json",
                    "{$home}/.config/bamboo-end-store-dev/storage/app/settings.json",
                    "{$home}/.config/bamboo-end-store/storage/app/settings.json",
                ];
                foreach ($paths as $p) {
                    if (file_exists($p)) {
                        $path = $p;
                        break;
                    }
                }
            }
        }

        if (file_exists($path)) {
            $this->settings = json_decode(file_get_contents($path), true) ?? [];
        } else {
            $this->settings = [];
        }
    }

    public function get(string $key, $default = null)
    {
        return $this->settings[$key] ?? $default;
    }

    public function all(): array
    {
        return $this->settings;
    }
}
