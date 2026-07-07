<?php

namespace App\Services;

use Native\Laravel\Client\Client;

class NotificationService
{
    /**
     * Sends a desktop notification with the app icon.
     * NativePHP's PHP class doesn't expose icon(), but the Electron side accepts it.
     * We call the Client directly to include the icon field in the payload.
     */
    public function send(string $title, string $body): void
    {
        (new Client)->post('notification', [
            'title' => $title,
            'body'  => $body,
            'icon'  => public_path('icon.png'),
        ]);
    }

    public function packageInstalled(string $packageName): void
    {
        $this->send(
            __('Operação Concluída'),
            sprintf(__('O pacote %s foi instalado com sucesso!'), $packageName)
        );
    }

    public function packageRemoved(string $packageName): void
    {
        $this->send(
            __('Remoção Concluída'),
            sprintf(__('O pacote %s foi removido do sistema.'), $packageName)
        );
    }

    public function systemUpdated(): void
    {
        $this->send(
            __('Sistema Atualizado'),
            __('A atualização do sistema foi concluída com sucesso!')
        );
    }
}
