<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PackageOperationFinishedEvent
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public string $name,
        public string $action
    ) {}
}
