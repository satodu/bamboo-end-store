<?php

namespace App\Listeners;

use App\Events\PackageOperationFinishedEvent;
use Illuminate\Support\Facades\Cache;

class ClearPackageCacheListener
{
    public function handle(PackageOperationFinishedEvent $event): void
    {
        Cache::forget("pkg_installed_list");

        if ($event->name !== 'System Update') {
            Cache::forget("pkg_search_" . md5($event->name));
            Cache::forget("pkg_details_{$event->name}");
            Cache::forget("installing_{$event->name}");
        }
    }
}
