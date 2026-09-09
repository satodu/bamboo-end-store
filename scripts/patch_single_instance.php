<?php

$mainIndexPath = __DIR__ . '/../vendor/nativephp/electron/resources/js/src/main/index.js';
$pluginDistPath = __DIR__ . '/../vendor/nativephp/electron/resources/js/electron-plugin/dist/index.js';

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
