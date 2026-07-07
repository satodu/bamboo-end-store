<?php

namespace App\Enums;

enum PackageSource: string
{
    case PACMAN  = 'pacman';
    case AUR     = 'aur';
    case FLATPAK = 'flatpak';

    /**
     * Resolve from legacy boolean flags for backwards compatibility.
     */
    public static function fromFlags(bool $isAur, bool $isFlatpak): self
    {
        if ($isFlatpak) return self::FLATPAK;
        if ($isAur)     return self::AUR;
        return self::PACMAN;
    }
}
