<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Log;

class AppImageService
{
    /**
     * Get the default AppImage directory if not set.
     */
    public function getDefaultDirectory(): string
    {
        return getenv('HOME') . '/Applications';
    }

    /**
     * List all AppImages in the given directory and their .desktop status.
     */
    public function listAppImages(string $directory): array
    {
        try {
            if (empty($directory)) return [];

            if (!File::exists($directory)) {
                File::makeDirectory($directory, 0755, true);
            }

            $files = File::files($directory);
            $appImages = [];

            foreach ($files as $file) {
                $extension = $file->getExtension();
                if (in_array(strtolower($extension), ['appimage'])) {
                    $name = $file->getFilename();
                    $path = $file->getRealPath();
                    
                    if (!$path) continue;
                    
                    $appImages[] = [
                        'name' => $name,
                        'path' => $path,
                        'size' => $this->formatBytes($file->getSize()),
                        'has_desktop' => $this->hasDesktopEntry($name),
                        'icon_url' => $this->getIconUrl($name),
                    ];
                }
            }

            return $appImages;
        } catch (\Exception $e) {
            Log::error("Failed to list AppImages: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get a browser-accessible URL or path for the icon.
     */
    public function getIconUrl(string $fileName): ?string
    {
        $id = md5($fileName);
        $iconDir = getenv('HOME') . '/.local/share/icons';
        
        $extensions = ['png', 'svg', 'jpg', 'jpeg'];
        foreach ($extensions as $ext) {
            $path = $iconDir . '/appimage-' . $id . '.' . $ext;
            if (File::exists($path)) {
                return url("/appimage/icon/{$id}");
            }
        }
        return null;
    }

    /**
     * Check if a .desktop entry exists for the AppImage.
     */
    public function hasDesktopEntry(string $fileName): bool
    {
        if (empty($fileName)) return false;
        $home = getenv('HOME');
        if (!$home) return false;
        
        $desktopPath = $home . '/.local/share/applications/appimage-' . md5($fileName) . '.desktop';
        return File::exists($desktopPath);
    }

    /**
     * Register an AppImage: move to target dir, chmod +x, and create .desktop file.
     */
    public function registerAppImage(string $sourcePath, string $targetDir): bool
    {
        if (!File::exists($sourcePath)) return false;
        
        if (!File::exists($targetDir)) {
            File::makeDirectory($targetDir, 0755, true);
        }

        $fileName = basename($sourcePath);
        $targetPath = $targetDir . '/' . $fileName;

        // Move file if it's not already there
        if ($sourcePath !== $targetPath) {
            File::copy($sourcePath, $targetPath);
        }

        // Make executable
        chmod($targetPath, 0755);

        // Create .desktop entry
        return $this->createDesktopEntry($fileName, $targetPath);
    }

    /**
     * Create a .desktop entry for the AppImage.
     */
    public function createDesktopEntry(string $fileName, string $execPath): bool
    {
        $name = str_replace(['.AppImage', '.appimage'], '', $fileName);
        $id = md5($fileName);
        $desktopPath = getenv('HOME') . '/.local/share/applications/appimage-' . $id . '.desktop';

        $iconPath = $this->extractIcon($execPath, $id) ?: 'system-run';

        $content = "[Desktop Entry]\n";
        $content .= "Type=Application\n";
        $content .= "Name=" . $name . " (AppImage)\n";
        $content .= "Exec=\"" . $execPath . "\"\n";
        $content .= "Icon=" . $iconPath . "\n";
        $content .= "Categories=Utility;\n";
        $content .= "Terminal=false\n";
        $content .= "Comment=Installed via Bamboo End Store\n";

        return File::put($desktopPath, $content) !== false;
    }

    /**
     * Try to extract the icon from the AppImage.
     */
    protected function extractIcon(string $execPath, string $id): ?string
    {
        $iconDir = getenv('HOME') . '/.local/share/icons';
        if (!File::exists($iconDir)) {
            File::makeDirectory($iconDir, 0755, true);
        }

        // If already exists (any format), return it
        $extensions = ['png', 'svg', 'jpg', 'jpeg'];
        foreach ($extensions as $ext) {
            $path = $iconDir . '/appimage-' . $id . '.' . $ext;
            if (File::exists($path)) return $path;
        }

        $tempDir = storage_path('app/temp_icon_' . $id);
        if (empty($tempDir)) return null;
        if (File::exists($tempDir)) File::deleteDirectory($tempDir);
        File::makeDirectory($tempDir, 0755, true);
        
        try {
            // Ensure executable
            chmod($execPath, 0755);
            
            // Try to extract .DirIcon
            $command = "cd " . escapeshellarg($tempDir) . " && " . escapeshellarg($execPath) . " --appimage-extract .DirIcon > /dev/null 2>&1";
            shell_exec($command);

            $extracted = $tempDir . '/squashfs-root/.DirIcon';
            if (File::exists($extracted)) {
                // Determine actual type of .DirIcon (could be a symlink to anything)
                $realExt = 'png'; // default
                $targetPath = $iconDir . '/appimage-' . $id . '.' . $realExt;
                
                shell_exec("cp -L " . escapeshellarg($extracted) . " " . escapeshellarg($targetPath));
                
                if (File::exists($targetPath)) {
                    File::deleteDirectory($tempDir);
                    return $targetPath;
                }
            }

            // Fallback: try to extract any PNG in the root
            $command = "cd " . escapeshellarg($tempDir) . " && " . escapeshellarg($execPath) . " --appimage-extract \"*.png\" > /dev/null 2>&1";
            shell_exec($command);
            
            if (File::exists($tempDir . '/squashfs-root')) {
                $files = File::files($tempDir . '/squashfs-root');
                foreach ($files as $file) {
                    $ext = strtolower($file->getExtension());
                    if (in_array($ext, ['png', 'svg', 'jpg', 'jpeg'])) {
                        $realPath = $file->getRealPath();
                        if ($realPath) {
                            $targetPath = $iconDir . '/appimage-' . $id . '.' . $ext;
                            File::copy($realPath, $targetPath);
                            File::deleteDirectory($tempDir);
                            return $targetPath;
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            Log::error("Failed to extract icon: " . $e->getMessage());
        }

        if (File::exists($tempDir)) File::deleteDirectory($tempDir);
        return null;
    }

    public function removeAppImage(string $path): bool
    {
        try {
            if (!empty($path) && File::exists($path)) {
                $fileName = basename($path);
                $id = md5($fileName);
                $home = getenv('HOME');
                
                if ($home) {
                    $desktopPath = $home . '/.local/share/applications/appimage-' . $id . '.desktop';
                    $iconPath = $home . '/.local/share/icons/appimage-' . $id . '.png';
                    
                    if (File::exists($desktopPath)) {
                        File::delete($desktopPath);
                    }

                    $extensions = ['png', 'svg', 'jpg', 'jpeg'];
                    foreach ($extensions as $ext) {
                        $iconPath = $home . '/.local/share/icons/appimage-' . $id . '.' . $ext;
                        if (File::exists($iconPath)) {
                            File::delete($iconPath);
                        }
                    }
                }

                return File::delete($path);
            }
        } catch (\Exception $e) {
            Log::error("Failed to remove AppImage: " . $e->getMessage());
        }
        return false;
    }

    /**
     * Launch an AppImage in the background.
     */
    public function launch(string $path): void
    {
        if (File::exists($path)) {
            chmod($path, 0755);
            shell_exec(escapeshellarg($path) . " > /dev/null 2>&1 &");
        }
    }

    private function formatBytes($bytes, $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        return round($bytes, $precision) . ' ' . $units[$pow];
    }
}
