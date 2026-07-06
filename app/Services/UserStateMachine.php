<?php

namespace App\Services;

use App\Enums\UserState;
use Illuminate\Support\Facades\Cache;

class UserStateMachine
{
    protected string $cacheKey = 'user_current_state';

    public function getState(): UserState
    {
        $raw = Cache::get($this->cacheKey, 'idle');
        return UserState::tryFrom($raw) ?? UserState::IDLE;
    }

    public function getActivePackage(): ?string
    {
        return Cache::get($this->cacheKey . '_package', null);
    }

    public function transitionTo(UserState $state, ?string $packageName = null): void
    {
        Cache::put($this->cacheKey, $state->value, 1800);
        Cache::put($this->cacheKey . '_package', $packageName, 1800);
    }

    public function reset(): void
    {
        Cache::forget($this->cacheKey);
        Cache::forget($this->cacheKey . '_package');
    }

    public function isBusy(): bool
    {
        return $this->getState() !== UserState::IDLE;
    }
}
