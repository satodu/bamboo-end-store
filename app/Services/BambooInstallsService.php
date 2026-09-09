<?php

namespace App\Services;

class BambooInstallsService
{
    /**
     * Lista de pacotes complexos (com configurações de instalação customizada)
     */
    public function getComplexPackages()
    {
        $res = \Illuminate\Support\Facades\Process::run("pacman -Qq");
        $installed = $res->successful() ? explode("\n", trim($res->output())) : [];

        $isSteamInstalled = in_array('steam', $installed);
        $isWinboatInstalled = in_array('winboat', $installed) || in_array('winboat-bin', $installed);
        $isLinuxtoysInstalled = in_array('linuxtoys', $installed) || in_array('linuxtoys-bin', $installed);
        $isDavinciInstalled = in_array('davinci-resolve', $installed) || in_array('davinci-resolve-studio', $installed) || file_exists('/opt/resolve/bin/resolve');

        return [
            [
                'repo' => 'official',
                'name' => 'steam',
                'display_name' => 'Steam',
                'version' => 'latest',
                'is_aur' => false,
                'is_flatpak' => false,
                'is_custom_install' => true,
                'installed' => $isSteamInstalled,
                'description' => __('The ultimate entertainment platform. (Bamboo custom install applies multilib, steam-devices and optimized graphics drivers automatically).'),
                'icon_url' => 'https://upload.wikimedia.org/wikipedia/commons/8/83/Steam_icon_logo.svg',
                'screenshots' => []
            ],
            [
                'repo' => 'aur',
                'name' => 'winboat',
                'display_name' => 'WinBoat',
                'version' => 'latest',
                'is_aur' => true,
                'is_flatpak' => false,
                'is_custom_install' => true,
                'installed' => $isWinboatInstalled,
                'description' => __('Run Windows apps on Linux with seamless desktop integration. Powered by Docker and KVM.'),
                'icon_url' => '/images/winboat.svg',
                'screenshots' => []
            ],
            [
                'repo' => 'aur',
                'name' => 'linuxtoys',
                'display_name' => 'Linux Toys',
                'version' => 'latest',
                'is_aur' => true,
                'is_flatpak' => false,
                'is_custom_install' => true,
                'installed' => $isLinuxtoysInstalled,
                'description' => __('A collection of tools for Linux in a user-friendly way. Essential tweaks, optimizations, kernel tools, and software for gaming and productivity.'),
                'icon_url' => '/images/linuxtoys.svg',
                'screenshots' => []
            ],
            [
                'repo' => 'aur',
                'name' => 'davinci-resolve',
                'display_name' => 'DaVinci Resolve',
                'version' => 'latest',
                'is_aur' => true,
                'is_flatpak' => false,
                'is_custom_install' => true,
                'installed' => $isDavinciInstalled,
                'description' => __('Professional Hollywood-grade video editing, color correction, visual effects, and audio post-production.'),
                'icon_url' => '/images/davinci-resolve.svg',
                'screenshots' => []
            ]
        ];
    }
}

