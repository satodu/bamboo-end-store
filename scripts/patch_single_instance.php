<?php

$mainIndexPath = __DIR__ . '/../vendor/nativephp/electron/resources/js/src/main/index.js';
$pluginDistPath = __DIR__ . '/../vendor/nativephp/electron/resources/js/electron-plugin/dist/index.js';
$phpJsPath = __DIR__ . '/../vendor/nativephp/electron/resources/js/php.js';

if (file_exists($mainIndexPath)) {
    $content = file_get_contents($mainIndexPath);
    
    // Check if single instance lock is already patched
    if (!str_contains($content, 'requestSingleInstanceLock')) {
        $patched = <<<'JS'
import {app, BrowserWindow} from 'electron'
import NativePHP from '#plugin'
import path from 'path'
import defaultIcon from '../../resources/icon.png?asset&asarUnpack'
import certificate from '../../resources/cacert.pem?asset&asarUnpack'

const gotTheLock = app.requestSingleInstanceLock();

if (!gotTheLock) {
    app.quit();
} else {
    app.on('second-instance', (event, commandLine, workingDirectory) => {
        const windows = BrowserWindow.getAllWindows();
        if (windows.length > 0) {
            const win = windows[0];
            if (win.isMinimized()) win.restore();
            if (!win.isVisible()) win.show();
            win.focus();
        }
    });

    let phpBinary = process.platform === 'win32' ? 'php.exe' : 'php';
    phpBinary = path.join(import.meta.dirname, '../../resources/php', phpBinary).replace("app.asar", "app.asar.unpacked");

    /**
     * Turn on the lights for the NativePHP app.
     */
    NativePHP.bootstrap(
        app,
        defaultIcon,
        phpBinary,
        certificate
    );
}
JS;
        file_put_contents($mainIndexPath, $patched);
        echo "Patched vendor electron src/main/index.js with SingleInstanceLock\n";
    }
}

if (file_exists($pluginDistPath)) {
    $content = file_get_contents($pluginDistPath);
    // Ensure second-instance focuses window properly even if this.mainWindow is null
    if (!str_contains($content, 'state.windows')) {
        $target = <<<'JS'
                    app.on("second-instance", (event, commandLine, workingDirectory) => {
                        if (this.mainWindow) {
                            if (this.mainWindow.isMinimized())
                                this.mainWindow.restore();
                            this.mainWindow.focus();
                        }
JS;
        $replacement = <<<'JS'
                    app.on("second-instance", (event, commandLine, workingDirectory) => {
                        const win = this.mainWindow || (state.windows && state.windows['main']) || (state.windows && Object.values(state.windows)[0]);
                        if (win) {
                            if (win.isMinimized()) win.restore();
                            if (!win.isVisible()) win.show();
                            win.focus();
                        }
JS;
        if (str_contains($content, $target)) {
            $content = str_replace($target, $replacement, $content);
            file_put_contents($pluginDistPath, $content);
            echo "Patched electron-plugin/dist/index.js window focus logic\n";
        }
    }
}

// Sync PHP binary extract: upstream yauzl path exits before unzip finishes on Linux,
// leaving a truncated binary (Electron runs with no window).
if (file_exists($phpJsPath)) {
    $phpJs = file_get_contents($phpJsPath);

    if (str_contains($phpJs, 'import unzip from "yauzl"') || ! str_contains($phpJs, 'execFileSync')) {
        $fixedPhpJs = <<<'JS'
import fs from "fs";
import fs_extra from 'fs-extra';
const { removeSync, ensureDirSync } = fs_extra;
import { join } from "path";
import { execFileSync } from "child_process";


const isBuilding = Boolean(process.env.NATIVEPHP_BUILDING);
const phpBinaryPath = process.env.NATIVEPHP_PHP_BINARY_PATH;
const phpVersion = process.env.NATIVEPHP_PHP_BINARY_VERSION;

// Differentiates for Serving and Building
const isArm64 = isBuilding ? process.argv.includes('--arm64') : process.arch.includes('arm64');
const isWindows = isBuilding ?  process.argv.includes('--win') : process.platform.includes('win32');
const isLinux = isBuilding ?  process.argv.includes('--linux') : process.platform.includes('linux');
const isDarwin = isBuilding ?  process.argv.includes('--mac') : process.platform.includes('darwin');

// false because string mapping is done in is{OS} checks
const platform = {
    os: false,
    arch: false,
    phpBinary: 'php'
};

if (isWindows) {
    platform.os = 'win';
    platform.phpBinary += '.exe';
    platform.arch = 'x64';
}

if (isLinux) {
    platform.os = 'linux';
    platform.arch = 'x64';
}

if (isDarwin) {
    platform.os = 'mac';
    platform.arch = 'x86';
}

if (isArm64) {
    platform.arch = 'arm64';
}

// isBuilding overwrites platform to the desired architecture
if (isBuilding) {
    // Only one will be used by the configured build commands in package.json
    platform.arch = process.argv.includes('--x64') ? 'x64' : platform.arch;
    platform.arch = process.argv.includes('--x86') ? 'x86' : platform.arch;
    platform.arch = process.argv.includes('--arm64') ? 'arm64' : platform.arch;
}

const phpVersionZip = 'php-' + phpVersion + '.zip';
const binarySrcDir = join(phpBinaryPath, platform.os, platform.arch, phpVersionZip);
const binaryDestDir = join(import.meta.dirname, 'resources/php');
const binaryPath = join(binaryDestDir, platform.phpBinary);

console.log('Binary Source: ', binarySrcDir);
console.log('Binary Filename: ', platform.phpBinary);
console.log('PHP version: ' + phpVersion);

if (platform.phpBinary) {
    try {
        console.log('Unzipping PHP binary from ' + binarySrcDir + ' to ' + binaryDestDir);
        removeSync(binaryDestDir);
        ensureDirSync(binaryDestDir);

        // Sync extract: the old yauzl path was async and node exited mid-write,
        // leaving a truncated/corrupt PHP binary (no window on Linux/Wayland).
        if (isWindows) {
            execFileSync('powershell.exe', [
                '-NoProfile',
                '-Command',
                `Expand-Archive -LiteralPath '${binarySrcDir.replace(/'/g, "''")}' -DestinationPath '${binaryDestDir.replace(/'/g, "''")}' -Force`,
            ], { stdio: 'inherit' });
        } else {
            execFileSync('unzip', ['-o', binarySrcDir, '-d', binaryDestDir], { stdio: 'inherit' });
        }

        fs.chmodSync(binaryPath, 0o755);
        console.log('Copied PHP binary to ', binaryPath);
    } catch (e) {
        console.error('Error copying PHP binary', e);
        process.exit(1);
    }
}
JS;
        file_put_contents($phpJsPath, $fixedPhpJs);
        echo "Patched vendor electron php.js with sync unzip extraction\n";
    }
}
