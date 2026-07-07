<?php

namespace App\Services\PackageManagers;

use App\Contracts\PackageManagerInterface;
use App\Enums\PackageSource;

class PackageManagerFactory
{
    public static function make(PackageSource $source): PackageManagerInterface
    {
        return match ($source) {
            PackageSource::AUR     => new AurManager(),
            PackageSource::FLATPAK => new FlatpakManager(),
            PackageSource::PACMAN  => new PacmanManager(),
        };
    }
}
