<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OpenStoreEvent
{
    use Dispatchable, SerializesModels;

    public function __construct(public $item = null, public $combo = null) {}
}
