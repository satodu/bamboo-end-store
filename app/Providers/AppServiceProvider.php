<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Set app locale based on settings
        $locale = 'system';
        $settingsPath = storage_path('app/private/settings.json');
        if (!file_exists($settingsPath)) {
            $settingsPath = storage_path('app/settings.json');
        }

        // Fallback para CLI Artisan/Background commands que rodam fora do context do Electron:
        if (!file_exists($settingsPath)) {
            $home = getenv('HOME');
            if ($home) {
                // Tenta caminhos de desenvolvimento e produção
                $paths = [
                    "{$home}/.config/bamboo-end-store-dev/storage/app/private/settings.json",
                    "{$home}/.config/bamboo-end-store/storage/app/private/settings.json",
                    "{$home}/.config/bamboo-end-store-dev/storage/app/settings.json",
                    "{$home}/.config/bamboo-end-store/storage/app/settings.json",
                ];
                foreach ($paths as $p) {
                    if (file_exists($p)) {
                        $settingsPath = $p;
                        break;
                    }
                }
            }
        }
        
        if (file_exists($settingsPath)) {
            $settings = json_decode(file_get_contents($settingsPath), true);
            $locale = $settings['locale'] ?? 'system';
        }

        if ($locale === 'system') {
            $lang = getenv('LANG') ?: getenv('LANGUAGE') ?: (isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) ? $_SERVER['HTTP_ACCEPT_LANGUAGE'] : 'en');
            $locale = 'en';
            if (str_starts_with($lang, 'pt')) {
                $locale = 'pt';
            } elseif (str_starts_with($lang, 'es')) {
                $locale = 'es';
            } elseif (str_starts_with($lang, 'ru')) {
                $locale = 'ru';
            }
        }

        app()->setLocale($locale);

        \Livewire\Volt\Volt::mount([
            resource_path('views/livewire'),
        ]);

        // Register NativePHP Event Listeners (Must run on all requests)
        \Illuminate\Support\Facades\Event::listen(\Native\Laravel\Events\MenuBar\MenuBarClicked::class, function () {
            \Illuminate\Support\Facades\Log::info("MenuBarClicked event received, attempting to open window 'main'");
            \Native\Laravel\Facades\Window::open('main')
                ->width(1200)
                ->height(800)
                ->minWidth(800)
                ->minHeight(600)
                ->title('Bamboo End Store - v' . config('nativephp.version'))
                ->rememberState();
        });

        \Illuminate\Support\Facades\Event::listen(\App\Events\OpenStoreEvent::class, function () {
            \Illuminate\Support\Facades\Log::info("OpenStoreEvent received, attempting to open window 'main'");
            \Native\Laravel\Facades\Window::open('main')
                ->width(1200)
                ->height(800)
                ->minWidth(800)
                ->minHeight(600)
                ->title('Bamboo End Store - v' . config('nativephp.version'))
                ->rememberState();
        });

        \Illuminate\Support\Facades\Event::listen(\App\Events\UpdateSystemEvent::class, function () {
            \Illuminate\Support\Facades\Log::info("UpdateSystemEvent received, triggering updateSystem()");
            app(\App\Services\PackageService::class)->updateSystem();
        });
    }
}
