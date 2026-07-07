<?php

namespace App\Console\Commands;

use App\Events\PackageOperationFinishedEvent;
use Illuminate\Console\Command;

class PackageFinishedCommand extends Command
{
    protected $signature = 'package:finished {name} {action=installed}';
    protected $description = 'Notifies the app that a package operation has finished';

    public function handle(): void
    {
        $name = $this->argument('name');
        $action = $this->argument('action');

        event(new PackageOperationFinishedEvent($name, $action));

        $this->info("Dispatched PackageOperationFinishedEvent for {$name} ({$action})");
    }
}
