<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Native\Laravel\Facades\Notification;
use Illuminate\Support\Facades\Cache;

class PackageFinishedCommand extends Command
{
    protected $signature = 'package:finished {name} {action=installed}';
    protected $description = 'Notifies the app that a package operation has finished';

    public function clearCache()
    {
        // Only clear specific keys to avoid wiping the home page cache
        Cache::forget("pkg_installed_list");
    }

    public function handle()
    {
        $name = $this->argument('name');
        $action = $this->argument('action');

        // Reset user state machine
        app(\App\Services\UserStateMachine::class)->reset();

        if ($name === 'System Update') {
            Cache::forget("pkg_installed_list");
            Notification::new()
                ->title(__('Sistema Atualizado'))
                ->message(__('A atualização do sistema foi concluída com sucesso!'))
                ->show();

            $this->info("System update notification sent.");
            return;
        }
        
        // Clear only the necessary cache instead of flushing everything
        Cache::forget("pkg_search_" . md5($name));
        Cache::forget("pkg_details_" . $name);
        Cache::forget("pkg_installed_list");
        
        // Remove the "installing" flag from cache
        Cache::forget("installing_{$name}");

        $title = $action === 'installed' ? __('Operação Concluída') : __('Remoção Concluída');
        $msg = $action === 'installed' 
            ? sprintf(__('O pacote %s foi instalado com sucesso!'), $name) 
            : sprintf(__('O pacote %s foi removido do sistema.'), $name);

        // Fire the system notification
        Notification::new()
            ->title($title)
            ->message($msg)
            ->show();

        $this->info("Notification sent for {$name}");
    }
}
