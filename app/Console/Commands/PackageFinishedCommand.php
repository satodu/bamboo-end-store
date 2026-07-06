<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Native\Laravel\Facades\Notification;
use Illuminate\Support\Facades\Cache;

class PackageFinishedCommand extends Command
{
    protected $signature = 'package:finished {name} {action=installed}';
    protected $description = 'Avisa o app que a operação de um pacote terminou';

    public function clearCache()
    {
        // Não limpamos tudo para não travar a Home
        Cache::forget("pkg_installed_list");
    }

    public function handle()
    {
        $name = $this->argument('name');
        $action = $this->argument('action');
        
        // Limpa apenas o cache necessário em vez de explodir tudo
        Cache::forget("pkg_search_" . md5($name));
        Cache::forget("pkg_details_" . $name);
        Cache::forget("pkg_installed_list");
        
        // Remove a flag de "instalando"
        Cache::forget("installing_{$name}");

        $title = $action === 'installed' ? 'Operação Concluída' : 'Remoção Concluída';
        $msg = $action === 'installed' 
            ? "O pacote {$name} foi instalado com sucesso!" 
            : "O pacote {$name} foi removido do sistema.";

        // Dispara a notificação oficial do sistema
        Notification::new()
            ->title($title)
            ->message($msg)
            ->show();

        $this->info("Notificação enviada para {$name}");
    }
}
