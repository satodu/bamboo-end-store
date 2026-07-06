<?php

namespace App\Events;

use Illuminate\Queue\SerializesModels;
use Illuminate\Foundation\Events\Dispatchable;

class UpdateSystemEvent
{
    use Dispatchable, SerializesModels;

    public function __construct(public $item = null, public $combo = null) {}
}
