<?php

namespace App\Services\PackageManagers;

use App\Contracts\PackageManagerInterface;

class PackageManagerFactory
{
    public static function make(bool $isAur = false, bool $isFlatpak = false): PackageManagerInterface
    {
        if ($isFlatpak) {
            return new FlatpakManager();
        }

        if ($isAur) {
            return new AurManager();
        }

        return new PacmanManager();
    }
}
