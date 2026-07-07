<?php

namespace App\Console\Commands;

use App\Services\NotificationService;
use App\Services\UserStateMachine;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class PackageFinishedCommand extends Command
{
    protected $signature = 'package:finished {name} {action=installed}';
    protected $description = 'Notifies the app that a package operation has finished';

    public function handle(NotificationService $notifications, UserStateMachine $stateMachine): void
    {
        $name = $this->argument('name');
        $action = $this->argument('action');

        $stateMachine->reset();

        if ($name === 'System Update') {
            Cache::forget('pkg_installed_list');
            $notifications->systemUpdated();
            $this->info('System update notification sent.');
            return;
        }

        $this->clearCache($name);

        match ($action) {
            'removed'   => $notifications->packageRemoved($name),
            default     => $notifications->packageInstalled($name),
        };

        $this->info("Notification sent for {$name}");
    }

    private function clearCache(string $name): void
    {
        Cache::forget("pkg_search_" . md5($name));
        Cache::forget("pkg_details_{$name}");
        Cache::forget("pkg_installed_list");
        Cache::forget("installing_{$name}");
    }
}
