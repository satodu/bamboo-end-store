<?php

namespace App\Providers;

use Native\Laravel\Facades\Window;
use Native\Laravel\Facades\MenuBar;
use Native\Laravel\Facades\Menu;
use Native\Laravel\Contracts\ProvidesPhpIni;
use App\Events\UpdateSystemEvent;
use App\Events\OpenStoreEvent;
use App\Services\PackageService;
use Illuminate\Support\Facades\Event;
use Native\Laravel\Events\MenuBar\MenuBarClicked;

class NativeAppServiceProvider implements ProvidesPhpIni
{
    /**
     * Executed once the native application has been booted.
     * Use this method to open windows, register global shortcuts, etc.
     */
    public function boot(): void
    {
        if (app()->runningInConsole()) {
            return;
        }

        // Open main store window
        Window::open('main')
            ->width(1200)
            ->height(800)
            ->minWidth(800)
            ->minHeight(600)
            ->title('Bamboo End Store - v' . config('nativephp.version'))
            ->rememberState();

        \Illuminate\Support\Facades\Log::info("NativeAppServiceProvider booting. Locale: " . app()->getLocale() . ", Open Store: " . __('Open Store'));

        // Create System Tray / Menu Bar Icon
        $menuBar = MenuBar::create();
        if ($menuBar) {
            $menuBar->showDockIcon()
                ->icon(public_path('icon.png'))
                ->onlyShowContextMenu()
                ->withContextMenu(
                    Menu::make(
                        Menu::label(__('Open Store'))->event(OpenStoreEvent::class),
                        Menu::separator(),
                        Menu::label(__('Update System'))->event(UpdateSystemEvent::class),
                        Menu::separator(),
                        Menu::quit()
                    )
                );
        }
    }

    /**
     * Return an array of php.ini directives to be set.
     */
    public function phpIni(): array
    {
        return [
        ];
    }
}
