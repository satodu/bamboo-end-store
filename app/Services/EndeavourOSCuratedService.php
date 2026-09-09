<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class EndeavourOSCuratedService
{
    const QUICKSTART_URL = 'https://raw.githubusercontent.com/endeavouros-team/eos-quickstart/main/eos-quickstart.conf';

    /**
     * Puxa e faz parse das categorias curadas pelo EndeavourOS
     */
    public function getCuratedPackages()
    {
        return Cache::remember('eos_quickstart_packages_v3', now()->addHours(24), function () {
            $formattedCategories = [];

            try {
                $response = Http::get(self::QUICKSTART_URL);
                if ($response->successful()) {
                    $iniData = parse_ini_string($response->body(), true, INI_SCANNER_RAW);
                    if (is_array($iniData)) {
                        foreach ($iniData as $categoryName => $items) {
                            if ($categoryName === 'General') continue;
                            
                            $packages = array_keys($items);
                            // Filter out empty keys or comments if any
                            $packages = array_filter($packages, function($pkg) {
                                return !empty($pkg) && !str_starts_with($pkg, '#');
                            });
                            
                            if (count($packages) > 0) {
                                $descKey = 'cat_desc_' . strtolower(str_replace([' ', '&'], ['_', 'and'], $categoryName));
                                
                                $formattedCategories[] = [
                                    'name' => $categoryName,
                                    'description' => $descKey,
                                    'packages' => array_values($packages)
                                ];
                            }
                        }
                    }
                }
            } catch (\Exception $e) {
                // Silently ignore or log
            }

            if (empty($formattedCategories)) {
                $formattedCategories = $this->getDefaultCategories();
            }

            // Ordena pelo nome da categoria
            usort($formattedCategories, fn($a, $b) => strcmp($a['name'], $b['name']));

            return $formattedCategories;
        });
    }

    private function getDefaultCategories(): array
    {
        return [
            [
                'name' => 'Browsers',
                'description' => 'cat_desc_browsers',
                'packages' => ['firefox', 'vivaldi', 'torbrowser-launcher', 'chromium', 'falkon', 'konqueror']
            ],
            [
                'name' => 'Development Tools',
                'description' => 'cat_desc_development_tools',
                'packages' => ['code', 'geany', 'gnome-builder', 'intellij-idea-community-edition', 'kdevelop', 'netbeans', 'pycharm-community-edition', 'qtcreator']
            ],
            [
                'name' => 'Communications',
                'description' => 'cat_desc_communications',
                'packages' => ['hexchat', 'konversation', 'neochat', 'pidgin', 'polari', 'telegram-desktop']
            ],
            [
                'name' => 'Audio Players',
                'description' => 'cat_desc_audio_players',
                'packages' => ['audacious', 'elisa', 'lollypop', 'rhythmbox', 'strawberry']
            ],
            [
                'name' => 'Video Players',
                'description' => 'cat_desc_video_players',
                'packages' => ['celluloid', 'dragon', 'haruna', 'smplayer', 'totem', 'vlc']
            ],
            [
                'name' => 'Office',
                'description' => 'cat_desc_office',
                'packages' => ['calligra', 'libreoffice-fresh', 'libreoffice-still', 'onlyoffice-bin']
            ],
            [
                'name' => 'Email',
                'description' => 'cat_desc_email',
                'packages' => ['evolution', 'geary', 'kmail', 'sylpheed', 'thunderbird']
            ]
        ];
    }
}
