<?php

namespace App\Contracts;

interface PackageManagerInterface
{
    /**
     * Search packages.
     */
    public function search(string $query): array;

    /**
     * Install a package.
     */
    public function install(string $packageName): int|false;

    /**
     * Uninstall/remove a package.
     */
    public function remove(string $packageName): int|false;

    /**
     * Get package details.
     */
    public function getDetails(string $packageName): ?array;

    /**
     * Check if a package is installed.
     */
    public function isInstalled(string $packageName): bool;

    /**
     * Run package installation in the background writing to a log file.
     */
    public function runInBackground(string $packageName, string $logFile): int;
}
