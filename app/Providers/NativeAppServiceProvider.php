<?php

namespace App\Providers;

use Native\Laravel\Facades\Window;
use Native\Laravel\Contracts\ProvidesPhpIni;

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

        Window::open()
            ->width(1200)
            ->height(800)
            ->minWidth(800)
            ->minHeight(600)
            ->title('Bamboo End Store - v' . config('nativephp.version'))
            ->rememberState();
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
