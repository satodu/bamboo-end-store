<?php

namespace App\Listeners;

use App\Events\PackageOperationFinishedEvent;
use App\Services\NotificationService;

class SendOperationNotificationListener
{
    public function __construct(protected NotificationService $notifications) {}

    public function handle(PackageOperationFinishedEvent $event): void
    {
        if ($event->name === 'System Update') {
            $this->notifications->systemUpdated();
            return;
        }

        // Notificações de instalação/remoção de pacotes desativadas no sistema (mantidas apenas dentro do app)
        /*
        match ($event->action) {
            'removed' => $this->notifications->packageRemoved($event->name),
            default   => $this->notifications->packageInstalled($event->name),
        };
        */
    }
}
