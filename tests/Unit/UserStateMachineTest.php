<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\UserStateMachine;
use App\Enums\UserState;
use Illuminate\Support\Facades\Cache;

class UserStateMachineTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Clear state before each test
        app(UserStateMachine::class)->reset();
    }

    /**
     * Test initial state is IDLE and not busy.
     */
    public function test_initial_state_is_idle(): void
    {
        $stateMachine = app(UserStateMachine::class);

        $this->assertEquals(UserState::IDLE, $stateMachine->getState());
        $this->assertNull($stateMachine->getActivePackage());
        $this->assertFalse($stateMachine->isBusy());
    }

    /**
     * Test state transitions and busy assertions.
     */
    public function test_state_transitions_work_correctly(): void
    {
        $stateMachine = app(UserStateMachine::class);

        // Transition to Installing
        $stateMachine->transitionTo(UserState::INSTALLING, 'discord');

        $this->assertEquals(UserState::INSTALLING, $stateMachine->getState());
        $this->assertEquals('discord', $stateMachine->getActivePackage());
        $this->assertTrue($stateMachine->isBusy());

        // Transition to Uninstalling
        $stateMachine->transitionTo(UserState::UNINSTALLING, 'spotify');

        $this->assertEquals(UserState::UNINSTALLING, $stateMachine->getState());
        $this->assertEquals('spotify', $stateMachine->getActivePackage());
        $this->assertTrue($stateMachine->isBusy());
    }

    /**
     * Test resetting state machine.
     */
    public function test_reset_restores_idle_state(): void
    {
        $stateMachine = app(UserStateMachine::class);

        $stateMachine->transitionTo(UserState::INSTALLING, 'discord');
        $this->assertTrue($stateMachine->isBusy());

        $stateMachine->reset();

        $this->assertEquals(UserState::IDLE, $stateMachine->getState());
        $this->assertNull($stateMachine->getActivePackage());
        $this->assertFalse($stateMachine->isBusy());
    }
}
