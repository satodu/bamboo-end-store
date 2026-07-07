<?php

namespace App\Listeners;

use App\Events\PackageOperationFinishedEvent;
use App\Services\UserStateMachine;

class ResetUserStateMachineListener
{
    public function __construct(protected UserStateMachine $stateMachine) {}

    public function handle(PackageOperationFinishedEvent $event): void
    {
        $this->stateMachine->reset();
    }
}
