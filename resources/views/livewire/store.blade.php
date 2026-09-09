<?php

use Livewire\Volt\Component;
use App\Services\PackageService;
use App\Services\AppImageService;
use Native\Laravel\Facades\Notification;
use App\Services\NotificationService;
use Native\Laravel\Facades\Alert;
use Native\Laravel\Dialog;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Computed;

new class extends Component
{
    public $search = '';
    public $packages = [];
    public $systemPackages = []; 
    public $appImages = [];
    public $selectedPackage = null;
    public $tab = 'explore'; 
    public $filterRepo = 'all'; 
    public $sortBy = 'relevance'; 
    public $toast = ['show' => false, 'message' => '', 'type' => 'success'];
    public $pendingInstallations = []; 
    public $confirmingAppImageDeletion = null; 
    public $confirmingPackageRemoval = null; 
    public $viewMode = 'grid';
    public $appImageViewMode = 'grid';
    
    // Bamboo Installs
    public $selectedCategory = null;
    
    // User State Machine
    public string $userState = 'idle';
    public ?string $activePackage = null;
    
    // Paginação
    public $page = 1;
    public $totalResults = 0;
    
    public $settings = [
        'enable_aur' => true,
        'enable_flatpak' => false,
        'search_limit' => 50,
        'appimage_path' => '',
        'locale' => 'system',
        'auto_close_terminal' => true,
        'terminal_close_delay' => 10,
        'view_mode' => 'grid',
        'appimages_view_mode' => 'grid',
        'theme' => 'theme-bamboo'
    ];

    public $sysInfo = [
        'kernel' => '',
        'hostname' => '',
        'os' => 'BambooOS'
    ];

    public function syncUserState()
    {
        $stateMachine = app(\App\Services\UserStateMachine::class);
        $this->userState = $stateMachine->getState()->value;
        $this->activePackage = $stateMachine->getActivePackage();
    }

    public function mount()
    {
        $this->sysInfo['kernel'] = php_uname('r');
        $this->sysInfo['hostname'] = gethostname();
        $this->loadSettings();
        
        if (empty($this->settings['appimage_path'])) {
            $this->settings['appimage_path'] = (new AppImageService())->getDefaultDirectory();
        }

        $this->loadData();
        
        // Reset state machine on boot to prevent stale locks from previous crashes/restarts
        app(\App\Services\UserStateMachine::class)->reset();
        
        $this->checkPendingInstallations();
        $this->syncUserState();
    }

    protected function t(string $key): string
    {
        return __($key);
    }

    public function runSystemUpdate()
    {
        $service = new PackageService();
        $service->runBambooUpdate();
        $this->syncUserState();
        $this->showNotification($this->t('refreshed'), $this->t('update_started'));
    }

    public function checkPendingInstallations()
    {
        $this->syncUserState();
        foreach ($this->pendingInstallations as $name => $data) {
            // Se o cache sumiu, o comando terminou via callback oficial
            if (!\Illuminate\Support\Facades\Cache::has("installing_{$name}")) {
                $this->finalizeOperation($name);
                continue;
            }

            // Se temos o PID, verificamos se o processo ainda existe no sistema
            $pid = $data['pid'] ?? null;
            if ($pid && !file_exists("/proc/{$pid}")) {
                // O terminal foi fechado ou o processo morreu
                $this->checkIfFinishedManually($name, $data);
            }
        }
    }

    protected function checkIfFinishedManually($name, $data)
    {
        $type = $data['type'] ?? 'install';
        $isFlatpak = $data['is_flatpak'] ?? false;

        if ($isFlatpak) {
            $res = \Illuminate\Support\Facades\Process::run("flatpak info " . escapeshellarg($name));
        } else {
            $pkgCheck = $name;
            if ($name === 'winboat' && !\Illuminate\Support\Facades\Process::run("pacman -Qq winboat")->successful()) {
                $pkgCheck = 'winboat-bin';
            } elseif ($name === 'linuxtoys' && !\Illuminate\Support\Facades\Process::run("pacman -Qq linuxtoys")->successful()) {
                $pkgCheck = 'linuxtoys-bin';
            } elseif ($name === 'davinci-resolve' && !\Illuminate\Support\Facades\Process::run("pacman -Qq davinci-resolve")->successful()) {
                if (\Illuminate\Support\Facades\Process::run("pacman -Qq davinci-resolve-studio")->successful()) {
                    $pkgCheck = 'davinci-resolve-studio';
                }
            }
            $res = \Illuminate\Support\Facades\Process::run("pacman -Qq " . escapeshellarg($pkgCheck));
            $isCurrentlyInstalled = $res->successful() || ($name === 'davinci-resolve' && file_exists('/opt/resolve/bin/resolve'));
        }
        $success = ($type === 'remove') ? !$isCurrentlyInstalled : $isCurrentlyInstalled;
        
        if ($success) {
            $msg = ($type === 'remove')
                ? sprintf($this->t('remove_done'), $name)
                : sprintf($this->t('install_done'), $name);
            $this->finalizeOperation($name, $this->t('done'), $msg);
        } else {
            // Retry once
            sleep(1);
            $retry = $isFlatpak
                ? \Illuminate\Support\Facades\Process::run("flatpak info " . escapeshellarg($name))
                : \Illuminate\Support\Facades\Process::run("pacman -Qq " . escapeshellarg($name));
            $isInstalledNow = $retry->successful();
            $successRetry = ($type === 'remove') ? !$isInstalledNow : $isInstalledNow;

            if ($successRetry) {
                $msg = ($type === 'remove')
                    ? sprintf($this->t('remove_done'), $name)
                    : sprintf($this->t('install_done'), $name);
                $this->finalizeOperation($name, $this->t('done'), $msg);
            } else {
                $this->finalizeOperation($name, $this->t('done'), sprintf($this->t('op_done_generic'), $name));
            }
        }
    }

    protected function finalizeOperation($name, $title = "Concluído", $message = null, $type = 'success')
    {
        unset($this->pendingInstallations[$name]);
        \Illuminate\Support\Facades\Cache::forget("installing_{$name}");
        
        // Limpar cache de pacote sem bloquear
        try {
            $service = new PackageService();
            $service->clearCache();
        } catch (\Throwable $e) {
            // Ignora silenciosamente
        }
        
        // Reset state machine in case of failures/closes
        app(\App\Services\UserStateMachine::class)->reset();

        // Clear active search cache to force a fresh result check
        if (!empty($this->search)) {
            \Illuminate\Support\Facades\Cache::forget("pkg_search_" . md5($this->search));
            \Illuminate\Support\Facades\Cache::forget("pkg_search_" . md5($this->search . '_flat'));
        }

        $this->syncUserState();
        $this->loadData();
        
        if ($message) {
            $this->showNotification($title, $message, $type);
        }

        $this->selectedPackage = null;
        $this->confirmingAppImageDeletion = null;
        $this->confirmingPackageRemoval = null;
        $this->confirmingPackageIsFlatpak = false;
    }

    public function loadSettings()
    {
        if (Storage::exists('settings.json')) {
            $this->settings = array_merge($this->settings, json_decode(Storage::get('settings.json'), true));
        }

        $this->viewMode = $this->settings['view_mode'] ?? 'grid';
        $this->appImageViewMode = $this->settings['appimages_view_mode'] ?? 'grid';

        // Re-apply locale on every mount so it survives within the long-running NativePHP process
        $locale = $this->settings['locale'] ?? 'system';
        if ($locale === 'system') {
            $lang   = getenv('LANG') ?: getenv('LANGUAGE') ?: 'en';
            $locale = 'en';
            if (str_starts_with($lang, 'pt')) {
                $locale = 'pt';
            } elseif (str_starts_with($lang, 'es')) {
                $locale = 'es';
            } elseif (str_starts_with($lang, 'ru')) {
                $locale = 'ru';
            }
        }
        app()->setLocale($locale);
    }

    public function updatedViewMode($value)
    {
        $this->settings['view_mode'] = $value;
        Storage::put('settings.json', json_encode($this->settings));
    }

    public function updatedAppImageViewMode($value)
    {
        $this->settings['appimages_view_mode'] = $value;
        Storage::put('settings.json', json_encode($this->settings));
    }

    public function saveSettings()
    {
        Storage::put('settings.json', json_encode($this->settings));
        
        $newLocale = $this->settings['locale'] ?? 'system';
        if ($newLocale === 'system') {
            $lang = getenv('LANG') ?: getenv('LANGUAGE') ?: (isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) ? $_SERVER['HTTP_ACCEPT_LANGUAGE'] : 'en');
            $newLocale = 'en';
            if (str_starts_with($lang, 'pt')) {
                $newLocale = 'pt';
            } elseif (str_starts_with($lang, 'es')) {
                $newLocale = 'es';
            } elseif (str_starts_with($lang, 'ru')) {
                $newLocale = 'ru';
            }
        }
        app()->setLocale($newLocale);

        $this->showNotification($this->t('saved'), $this->t('settings_saved') . ' (' . __('Restart the app to update the tray menu.') . ')');
        $this->loadData();
    }

    public function clearCache()
    {
        \Illuminate\Support\Facades\Cache::flush();
        $this->showNotification(__('Cache Cleared'), __('All cached store data has been successfully cleared.'));
    }

    public function saveTerminalAutoClose()
    {
        Storage::put('settings.json', json_encode($this->settings));
    }

    public function installFlatpak()
    {
        $service = new PackageService();
        $service->openInTerminal("sudo pacman -S --needed flatpak", "Instalação do Flatpak");
        $this->showNotification(__('Terminal Opened'), __('Installing Flatpak in terminal...'));
    }

    public function addFlathubRemote()
    {
        $service = new PackageService();
        $service->openInTerminal("flatpak remote-add --if-not-exists flathub https://dl.flathub.org/repo/flathub.flatpakrepo", "Configuração do Flathub");
        $this->showNotification(__('Terminal Opened'), __('Configuring Flathub remote in terminal...'));
    }

    public function showDetails($name, $isAur = false, $isFlatpak = false)
    {
        $service = new PackageService();
        $details = $service->getPackageDetails($name, $isAur, $isFlatpak);

        // Fallback: se getPackageDetails retornar null, usa dados básicos + busca screenshots para flatpak
        if (!$details) {
            $details = [
                'Name'         => $name,
                'Description'  => 'No additional details available for this package.',
                'Version'      => 'Unknown',
                'icon_url'     => $service->getIcon($name, $isFlatpak),
                // Para flatpaks, busca screenshots mesmo sem os detalhes completos
                'screenshots'  => $service->getScreenshots($name, $isFlatpak),
                'is_installed' => false,
            ];
        }

        $this->selectedPackage = array_merge($details, [
            'is_aur'     => $isAur,
            'is_flatpak' => $isFlatpak,
        ]);
    }

    public function closeDetails()
    {
        $this->selectedPackage = null;
    }

    public function setTab($tab)
    {
        $this->tab = $tab;
        $this->page = 1;
        $this->search = '';
        $this->selectedCategory = null;
        $this->loadData();
    }

    public function nextPage()
    {
        if ($this->page * $this->settings['search_limit'] < $this->totalResults) {
            $this->page++;
            $this->loadData();
        }
    }

    public function prevPage()
    {
        if ($this->page > 1) {
            $this->page--;
            $this->loadData();
        }
    }

    public function refresh()
    {
        $service = new PackageService();
        $service->clearCache();
        $this->loadData();
        $this->showNotification($this->t('refreshed'), $this->t('refreshed_msg'));
    }

    public function loadData()
    {
        if ($this->tab === 'settings') return;

        if ($this->tab === 'appimages') {
            $this->loadAppImages();
            return;
        }

        if ($this->tab === 'bamboo_installs') {
            $this->loadBambooInstalls();
            $this->updateInstallationStatus();
            return;
        }

        if ($this->tab === 'installed') {
            $this->loadInstalled();
            $this->systemPackages = [];
        } elseif ($this->selectedCategory) {
            $this->search = '';
            $this->loadCuratedCategories();
            $this->systemPackages = [];
        } else {
            if (empty($this->search) || strlen($this->search) < 3) {
                $this->loadCuratedCategories();
                $this->getSystemPackages();
            } else {
                $this->searchPackages();
                $this->systemPackages = [];
            }
        }
        
        // Fast update installation status for what's on screen
        $this->updateInstallationStatus();
        $this->applyFiltersAndSorting();
    }

    protected function updateInstallationStatus()
    {
        $service = new PackageService();
        $res = \Illuminate\Support\Facades\Process::run("pacman -Qq");
        $installedNames = $res->successful() ? explode("\n", trim($res->output())) : [];

        $installedFlatpaks = [];
        if ($this->settings['enable_flatpak'] && $service->commandExists('flatpak')) {
            $resF = \Illuminate\Support\Facades\Process::run("flatpak list --columns=application");
            $installedFlatpaks = $resF->successful() ? explode("\n", trim($resF->output())) : [];
        }

        foreach ($this->packages as &$pkg) {
            if ($pkg['is_flatpak'] ?? false) {
                $pkg['installed'] = in_array($pkg['name'], $installedFlatpaks);
            } else {
                $isInstalled = in_array($pkg['name'], $installedNames);
                if (!$isInstalled && $pkg['name'] === 'winboat') {
                    $isInstalled = in_array('winboat-bin', $installedNames);
                }
                if (!$isInstalled && $pkg['name'] === 'linuxtoys') {
                    $isInstalled = in_array('linuxtoys-bin', $installedNames);
                }
                if (!$isInstalled && $pkg['name'] === 'davinci-resolve') {
                    $isInstalled = in_array('davinci-resolve-studio', $installedNames) || file_exists('/opt/resolve/bin/resolve');
                }
                $pkg['installed'] = $isInstalled;
            }
        }
        
        foreach ($this->systemPackages as &$pkg) {
            $pkg['installed'] = in_array($pkg['name'], $installedNames);
        }
    }

    public function updatedSearch()
    {
        $this->page = 1;
        $this->selectedCategory = null;
        // Limpa imediatamente para evitar flash de resultados stale de requests anteriores
        $this->packages = [];
        $this->totalResults = 0;
        $this->loadData();
    }

    public function clearSearch()
    {
        $this->search = '';
        $this->selectedCategory = null;
        $this->page = 1;
        $this->loadData();
    }

    public function updatedFilterRepo()
    {
        // Na home (explore sem busca), filtro não tem efeito
        if ($this->tab === 'explore' && empty($this->search)) return;
        if ($this->tab === 'featured' || $this->tab === 'bamboo_installs') return;

        $this->page = 1;
        $this->loadData();
    }

    #[Computed]
    public function curatedCategories()
    {
        if ($this->tab !== 'explore' || !empty($this->search) || $this->selectedCategory) {
            return [];
        }
        $service = app(\App\Services\EndeavourOSCuratedService::class);
        return $service->getCuratedPackages();
    }

    public function loadCuratedCategories()
    {
        // If a category is selected, search for its packages
        if ($this->selectedCategory) {
            $service = app(\App\Services\EndeavourOSCuratedService::class);
            $allCategories = $service->getCuratedPackages();
            $categoryData = collect($allCategories)->firstWhere('name', $this->selectedCategory);
            
            if ($categoryData && !empty($categoryData['packages'])) {
                $pkgService = new PackageService();
                $this->packages = $pkgService->getPackagesByExactNames($categoryData['packages']);
            } else {
                $this->packages = [];
            }
        } else {
            $this->packages = [];
        }
    }

    public function loadBambooInstalls()
    {
        $service = app(\App\Services\BambooInstallsService::class);
        $this->packages = $service->getComplexPackages();
    }

    public function selectCategory($categoryName)
    {
        $this->search = '';
        $this->selectedCategory = $categoryName;
        $this->page = 1;
        $this->loadData();
    }


    public function getSystemPackages()
    {
        $service = new PackageService();
        $path = storage_path('app/essentials.json');
        
        $essentials = [];
        if (file_exists($path)) {
            $essentials = json_decode(file_get_contents($path), true);
            shuffle($essentials);
            $essentials = array_slice($essentials, 0, 3);
        } else {
            $essentials = ['welcome', 'eos-log-tool', 'eos-quickstart'];
        }

        $this->systemPackages = [];
        
        foreach($essentials as $name) {
            $results = $service->search($name);
            foreach($results as $pkg) {
                if ($pkg['name'] === $name) {
                    $this->systemPackages[] = $pkg;
                    break;
                }
            }
        }
    }

    public function getFeaturedPackages()
    {
        $this->packages = \Illuminate\Support\Facades\Cache::remember('featured_pkgs_data', 3600, function() {
            $service = new PackageService();
            $featured = ['discord', 'steam', 'brave-bin', 'vlc', 'obs-studio', 'visual-studio-code-bin', 'spotify'];
            $data = [];
            
            foreach($featured as $name) {
                $results = $service->search($name);
                foreach($results as $pkg) {
                    if ($pkg['name'] === $name) {
                        $data[] = $pkg;
                        break;
                    }
                }
            }
            return $data;
        });

        // Enriquece com screenshots fora do cache (funciona mesmo com cache antigo)
        $service = new PackageService();
        $this->packages = array_map(function($pkg) use ($service) {
            if (!isset($pkg['screenshots'])) {
                $pkg['screenshots'] = $service->getScreenshots($pkg['name'], $pkg['is_flatpak'] ?? false);
            }
            return $pkg;
        }, $this->packages);

        // Garante que o status de instalado esteja sempre certo mesmo se vier do cache
        $this->updateInstallationStatus();
    }

    public function loadInstalled()
    {
        $service = new PackageService();
        $allInstalled = $service->getInstalledPackages();
        
        $this->packages = array_map(function($pkg) use ($service) {
            $isFlatpak = $pkg['is_flatpak'] ?? false;
            $isAur = $pkg['is_aur'] ?? false;
            $name = $pkg['name'];
            
            return [
                'repo'         => $isFlatpak ? 'flathub' : ($isAur ? 'aur' : 'official'),
                'name'         => $name,
                'display_name' => $pkg['display_name'] ?? null,
                'version'      => $pkg['version'],
                'is_aur'       => $isAur,
                'is_flatpak'   => $isFlatpak,
                'installed'    => true,
                'description'  => $pkg['description'] ?? 'Package installed on your system.',
                'icon_url'     => $service->getIcon($name, $isFlatpak),
                'screenshots'  => $service->getScreenshots($name, $isFlatpak),
            ];
        }, $allInstalled);
    }

    public function searchPackages()
    {
        $service = new PackageService();
        $this->packages = $service->search($this->search, $this->settings['enable_flatpak'] ?? false);
    }

    protected function applyFiltersAndSorting()
    {
        // Home = explore sem busca ativa — nunca filtrar
        $isHome = ($this->tab === 'explore' && empty($this->search))
               || $this->tab === 'featured';

        if ($isHome) {
            return;
        }

        if (!empty($this->search)) {
            $this->packages = array_filter($this->packages, function($pkg) {
                return str_contains(strtolower($pkg['name']), strtolower($this->search));
            });
        }

        if ($this->filterRepo !== 'all') {
            $this->packages = array_filter($this->packages, function($pkg) {
                if ($this->filterRepo === 'aur') {
                    return isset($pkg['is_aur']) && $pkg['is_aur'];
                }
                if ($this->filterRepo === 'official') {
                    return (!isset($pkg['is_aur']) || !$pkg['is_aur']) && (!isset($pkg['is_flatpak']) || !$pkg['is_flatpak']);
                }
                if ($this->filterRepo === 'flatpak') {
                    return isset($pkg['is_flatpak']) && $pkg['is_flatpak'];
                }
                return true;
            });
        }

        if ($this->sortBy === 'name') {
            usort($this->packages, fn($a, $b) => strcasecmp($a['name'], $b['name']));
        }

        // Paginação REAL
        $this->totalResults = count($this->packages);
        $limit = (int) ($this->settings['search_limit'] ?? 50);
        $offset = ($this->page - 1) * $limit;
        
        $this->packages = array_slice(array_values($this->packages), $offset, $limit);
    }

    public function install($name, $isAur = false, $isFlatpak = false, $isCustomInstall = false)
    {
        $service = new PackageService();
        $success = $service->installInTerminal($name, $isAur, $isFlatpak, $isCustomInstall);

        if ($success) {
            $this->showNotification(__('Terminal Opened'), sprintf(__('Executing installation for %s in terminal...'), $name));
        } else {
            $this->showNotification($this->t('error'), sprintf($this->t('install_fail'), $name), 'error');
        }

        $this->loadData();
    }

    public $confirmingPackageIsFlatpak = false;

    public function remove($name, $isFlatpak = false)
    {
        $this->confirmingPackageRemoval = $name;
        $this->confirmingPackageIsFlatpak = $isFlatpak;
    }

    public function launchPackage(string $name, bool $isFlatpak = false)
    {
        if ($isFlatpak) {
            // Flatpak apps use their app ID
            $mapping = [
                'discord'                => 'com.discordapp.Discord',
                'spotify'                => 'com.spotify.Client',
                'steam'                  => 'com.valvesoftware.Steam',
                'visual-studio-code-bin' => 'com.visualstudio.code',
                'brave-bin'              => 'com.brave.Browser',
                'brave'                  => 'com.brave.Browser',
                'vlc'                    => 'org.videolan.VLC',
                'obs-studio'             => 'com.obsproject.Studio',
            ];
            $appId = $mapping[strtolower($name)] ?? $name;
            shell_exec("flatpak run " . escapeshellarg($appId) . " > /dev/null 2>&1 &");
        } else {
            // For native packages just launch the binary name
            shell_exec(escapeshellarg($name) . " > /dev/null 2>&1 &");
        }
        $this->showNotification(__('Launching'), __('Starting application...'));
    }

    public function cancelPackageRemoval()
    {
        $this->confirmingPackageRemoval = null;
        $this->confirmingPackageIsFlatpak = false;
    }

    public function deletePackageConfirmed()
    {
        $name = $this->confirmingPackageRemoval;
        $isFlatpak = $this->confirmingPackageIsFlatpak;
        
        $this->confirmingPackageRemoval = null;
        $this->confirmingPackageIsFlatpak = false;

        if (!$name) return;

        $service = new PackageService();
        $success = $service->removeInTerminal($name, $isFlatpak);

        if ($success) {
            $this->showNotification(__('Terminal Opened'), sprintf(__('Executing removal for %s in terminal...'), $name));
        } else {
            $this->showNotification($this->t('error'), sprintf($this->t('remove_fail'), $name), 'error');
        }

        $this->loadData();
    }

    public function showNotification($title, $message, $type = 'success')
    {
        $this->toast = [
            'show'    => true,
            'message' => $message,
            'type'    => $type
        ];

        $this->dispatch('notify');

        // Notificação do sistema via NotificationService (inclui ícone)
        try {
            app(NotificationService::class)->send($title, $message);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('NotificationService failed: ' . $e->getMessage());
        }
    }

    public function loadAppImages()
    {
        $service = new AppImageService();
        $this->appImages = $service->listAppImages($this->settings['appimage_path']);
    }

    public function launchAppImage($path)
    {
        (new AppImageService())->launch($path);
        $this->showNotification(__('Launching'), __('Starting application...'));
    }

    public function registerAppImage($path, $targetDir = null)
    {
        $targetDir = $targetDir ?? $this->settings['appimage_path'];
        $service = new AppImageService();
        $success = $service->registerAppImage($path, $targetDir);
        
        if ($success) {
            $this->showNotification(__('Success'), __('AppImage integrated into your menu.'));
            $this->loadAppImages();
        } else {
            $this->showNotification(__('Error'), __('Failed to integrate AppImage.'), 'error');
        }
    }

    public function selectAppImage()
    {
        $path = Dialog::new()
            ->title(__('Select AppImage'))
            ->button(__('Select'))
            ->filter('AppImage', ['AppImage', 'appimage'])
            ->open();

        if ($path) {
            $this->registerAppImage($path);
        }
    }

    public function selectAppImagePath()
    {
        $path = Dialog::new()
            ->title(__('Select Directory for AppImages'))
            ->button(__('Select'))
            ->folders()
            ->open();

        if ($path) {
            $this->settings['appimage_path'] = $path;
            $this->saveSettings();
        }
    }

    public function removeAppImage($path)
    {
        $this->confirmingAppImageDeletion = $path;
    }

    public function cancelAppImageDeletion()
    {
        $this->confirmingAppImageDeletion = null;
    }

    public function deleteAppImageConfirmed()
    {
        $path = $this->confirmingAppImageDeletion;
        $this->confirmingAppImageDeletion = null;

        $service = new AppImageService();
        if ($service->removeAppImage($path)) {
            $this->showNotification(__('Removed'), __('AppImage and menu entry removed.'));
            $this->loadAppImages();
        } else {
            $this->showNotification(__('Error'), __('Failed to remove AppImage.'), 'error');
        }
    }

    public function hideToast()
    {
        $this->toast['show'] = false;
    }
};
?>

<div 
    class="flex h-screen bg-background text-foreground overflow-hidden relative font-sans {{ $settings['theme'] ?? 'theme-bamboo' }}" 
    x-data="{ show: false, timeout: null }" 
    x-on:notify.window="show = true; clearTimeout(timeout); timeout = setTimeout(() => show = false, 4000)"
    @if(count($pendingInstallations) > 0 || $userState !== 'idle') wire:poll.2s="checkPendingInstallations" @endif
>
    <!-- Ambient Glow Background Bubbles for Glassmorphism -->
    <div class="absolute -top-40 -left-40 w-96 h-96 bg-bamboo/15 rounded-full blur-[120px] pointer-events-none z-0"></div>
    <div class="absolute bottom-10 left-10 w-80 h-80 bg-blue-500/10 rounded-full blur-[100px] pointer-events-none z-0"></div>
    
    <!-- Toast Notification -->
    <div 
        x-show="show"
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="opacity-0 transform -translate-y-10 scale-95"
        x-transition:enter-end="opacity-100 transform translate-y-0 scale-100"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100 transform translate-y-0 scale-100"
        x-transition:leave-end="opacity-0 transform -translate-y-10 scale-95"
        class="fixed top-8 right-8 z-[10000] pointer-events-auto"
    >
        <div class="bg-card border border-border rounded-lg p-5 shadow-2xl flex items-center gap-4 min-w-[350px]">
            <div class="w-12 h-12 rounded-full {{ $toast['type'] === 'success' ? 'bg-bamboo/20 text-bamboo' : 'bg-destructive/20 text-destructive' }} flex items-center justify-center">
                @if($toast['type'] === 'success')
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                @else
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                @endif
            </div>
            <div class="flex-1">
                <p class="text-sm font-bold tracking-tight">{{ $toast['type'] === 'success' ? 'Notification' : 'Error' }}</p>
                <p class="text-muted-foreground text-xs leading-relaxed">{{ $toast['message'] }}</p>
            </div>
        </div>
    </div>

    <!-- Loading Overlay -->
    <div wire:loading.delay.longer wire:target="setTab, refresh, saveSettings, nextPage, prevPage" class="fixed inset-0 z-[9999] bg-background/85 backdrop-blur-md no-drag">
        <div class="flex h-screen w-screen items-center justify-center flex-col gap-8 select-none pointer-events-none">
            <div class="pacman-container scale-110">
                <div class="pacman"></div>
                <div class="dot"></div>
                <div class="dot"></div>
                <div class="dot"></div>
            </div>
            <p class="text-xs font-black tracking-[0.4em] uppercase text-bamboo animate-pulse">
                {{ __('Syncing Store') }}
            </p>
        </div>
    </div>

    <x-store.sidebar :$tab :$sysInfo :$pendingInstallations />

    <!-- Main Content -->
    <main class="flex-1 flex flex-col min-w-0 bg-background overflow-hidden">
        @if($tab === 'explore' || $tab === 'installed' || $tab === 'bamboo_installs')
            <x-store.header :$search :$tab :$filterRepo :$settings :$viewMode />

            <!-- Content Area -->
            <div class="flex-1 overflow-y-auto">
                <div class="max-w-7xl mx-auto p-10">

                    {{-- === HOME (explore sem busca ativa) === --}}
                    @if($tab === 'explore' && (empty($search) || strlen($search) < 3))
                        @if(!empty($search) && strlen($search) < 3)
                            <p class="text-sm text-bamboo font-bold italic tracking-wide mb-8">Enter at least 3 characters for a global search...</p>
                        @endif
                        
                        <x-store.explore-categories 
                            :curatedCategories="$this->curatedCategories" 
                            :$selectedCategory 
                            :$packages 
                            :$systemPackages
                            :$pendingInstallations 
                            :$userState 
                            :$activePackage 
                            :$viewMode 
                        />

                    {{-- === SEARCH RESULTS (explore com busca ativa) === --}}
                    @elseif($tab === 'explore' && strlen($search) >= 3)
                        <x-store.explore-search-tab
                            :$packages
                            :$pendingInstallations
                            :$search
                            :$totalResults
                            :$page
                            :$settings
                            :$userState
                            :$activePackage
                            :$viewMode
                        />

                    {{-- === INSTALLED === --}}
                    @elseif($tab === 'installed')
                        <div class="mb-10">
                            <h2 class="text-3xl font-black tracking-tight mb-2">Installed Applications</h2>
                            <p class="text-xs text-muted-foreground font-bold uppercase tracking-widest">{{ $totalResults }} packages installed</p>
                        </div>

                        {{-- Skeleton while loading installed --}}
                        <div class="hidden"
                            wire:loading.class.remove="hidden"
                            wire:target="setTab"
                        >
                            @if($viewMode === 'grid')
                                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                                    @for($s = 0; $s < 8; $s++)
                                        <div class="bg-card rounded-lg overflow-hidden animate-pulse">
                                            <div class="p-6 space-y-4">
                                                <div class="w-14 h-14 bg-muted/60 rounded-lg"></div>
                                                <div class="h-4 bg-muted/60 rounded w-3/4"></div>
                                                <div class="h-3 bg-muted/40 rounded w-full"></div>
                                                <div class="h-10 bg-muted/30 rounded mt-4"></div>
                                            </div>
                                        </div>
                                    @endfor
                                </div>
                            @else
                                <div class="flex flex-col gap-4">
                                    @for($s = 0; $s < 6; $s++)
                                        <div class="bg-card rounded-lg p-4 flex items-center gap-5 animate-pulse">
                                            <div class="w-24 h-24 bg-muted/60 rounded-lg shrink-0"></div>
                                            <div class="flex-1 space-y-2.5">
                                                <div class="flex items-center gap-2">
                                                    <div class="h-4.5 bg-muted/60 rounded w-1/4"></div>
                                                    <div class="h-3.5 bg-muted/40 rounded w-16"></div>
                                                </div>
                                                <div class="h-3.5 bg-muted/40 rounded w-3/4"></div>
                                                <div class="h-3 bg-muted/30 rounded w-1/2"></div>
                                            </div>
                                            <div class="flex items-center gap-3 shrink-0">
                                                <div class="h-10 w-28 bg-muted/40 rounded-lg"></div>
                                                <div class="h-10 w-10 bg-muted/30 rounded-lg"></div>
                                            </div>
                                        </div>
                                    @endfor
                                </div>
                            @endif
                        </div>

                        @if($viewMode === 'grid')
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6"
                                wire:loading.class="hidden"
                                wire:target="setTab"
                            >
                                @forelse($packages as $pkg)
                                    <x-store.package-card :$pkg :$pendingInstallations wire:key="inst-{{ $pkg['name'] }}-{{ $loop->index }}" />
                                @empty
                                    <div class="col-span-full py-32 text-center">
                                        <h3 class="text-xl font-bold text-muted-foreground">No installed packages found.</h3>
                                    </div>
                                @endforelse
                            </div>
                        @else
                            <div class="flex flex-col gap-4"
                                wire:loading.class="hidden"
                                wire:target="setTab"
                            >
                                @forelse($packages as $pkg)
                                    <x-store.package-list-card :$pkg :$pendingInstallations wire:key="inst-{{ $pkg['name'] }}-{{ $loop->index }}" />
                                @empty
                                    <div class="col-span-full py-32 text-center">
                                        <h3 class="text-xl font-bold text-muted-foreground">No installed packages found.</h3>
                                    </div>
                                @endforelse
                            </div>
                        @endif

                        {{-- Pagination para installed --}}
                        @if($totalResults > $settings['search_limit'])
                            <div class="mt-16 flex items-center justify-between border-t border-border pt-8 pb-20">
                                <div class="text-sm text-muted-foreground font-bold">
                                    Showing <span class="text-foreground">{{ min(($page - 1) * $settings['search_limit'] + 1, $totalResults) }}</span>
                                    to <span class="text-foreground">{{ min($page * $settings['search_limit'], $totalResults) }}</span>
                                    of <span class="text-foreground">{{ $totalResults }}</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <button wire:click="prevPage" @if($page === 1) disabled @endif class="h-10 px-4 border border-input rounded-md text-xs font-black uppercase tracking-widest transition-all hover:bg-accent disabled:opacity-30 disabled:hover:bg-transparent">Previous</button>
                                    <div class="h-10 px-4 bg-accent/50 rounded-md flex items-center justify-center text-xs font-black">{{ $page }} / {{ ceil($totalResults / $settings['search_limit']) }}</div>
                                    <button wire:click="nextPage" @if($page * $settings['search_limit'] >= $totalResults) disabled @endif class="h-10 px-4 border border-input rounded-md text-xs font-black uppercase tracking-widest transition-all hover:bg-accent disabled:opacity-30 disabled:hover:bg-transparent">Next</button>
                                </div>
                            </div>
                        @endif
                    @elseif($tab === 'bamboo_installs')
                        <x-store.bamboo-installs-tab 
                            :$packages 
                            :$pendingInstallations 
                            :$userState 
                            :$activePackage 
                            :$viewMode 
                        />
                    @endif

                </div>
            </div>
        @elseif($tab === 'appimages')
            <x-store.appimages-tab :$appImages :$appImageViewMode />
        @elseif($tab === 'settings')
            <x-store.settings-tab :$settings />
        @endif
    </main>

    <!-- Details Overlay -->
    @if($selectedPackage)
        <x-store.details-overlay :$selectedPackage />
    @endif

    <!-- Custom Confirmation Modal for AppImages -->
    @if($confirmingAppImageDeletion)
        <div class="fixed inset-0 z-[10001] flex items-center justify-center p-6 bg-background/80 backdrop-blur-sm animate-in fade-in duration-200">
            <div class="bg-card border border-border w-full max-w-md rounded-3xl shadow-2xl overflow-hidden animate-in zoom-in-95 duration-200">
                <div class="p-8">
                    <div class="flex items-center gap-4 mb-6">
                        <div class="w-12 h-12 bg-destructive/10 text-destructive rounded-xl flex items-center justify-center shrink-0">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                            </svg>
                        </div>
                        <h3 class="text-2xl font-black tracking-tight leading-tight">{{ __('Confirm AppImage Deletion') }}</h3>
                    </div>
                    <p class="text-muted-foreground leading-relaxed">
                        {{ __('Are you sure you want to delete this AppImage? This will permanently remove the file from your system.') }}
                    </p>
                </div>
                
                <div class="p-6 bg-muted/30 flex items-center gap-3">
                    <button wire:click="cancelAppImageDeletion" class="flex-1 h-12 text-sm font-bold rounded-xl hover:bg-accent transition-colors">{{ __('Cancel') }}</button>
                    <button wire:click="deleteAppImageConfirmed" class="flex-1 h-12 bg-destructive text-destructive-foreground text-sm font-black rounded-xl hover:bg-destructive/90 shadow-lg shadow-destructive/20 transition-all">{{ __('Delete File') }}</button>
                </div>
            </div>
        </div>
    @endif

    <!-- Custom Confirmation Modal for System Packages -->
    @if($confirmingPackageRemoval)
        <div class="fixed inset-0 z-[10001] flex items-center justify-center p-6 bg-background/80 backdrop-blur-sm animate-in fade-in duration-200">
            <div class="bg-card border border-border w-full max-w-md rounded-3xl shadow-2xl overflow-hidden animate-in zoom-in-95 duration-200">
                <div class="p-8">
                    <div class="flex items-center gap-4 mb-6">
                        <div class="w-12 h-12 bg-destructive/10 text-destructive rounded-xl flex items-center justify-center shrink-0">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                            </svg>
                        </div>
                        <h3 class="text-2xl font-black tracking-tight leading-tight">{{ __('Confirm Package Removal') }}</h3>
                    </div>
                    <p class="text-muted-foreground leading-relaxed">
                        {{ __('Are you sure you want to uninstall this package?') }}
                    </p>
                </div>
                
                <div class="p-6 bg-muted/30 flex items-center gap-3">
                    <button wire:click="cancelPackageRemoval" wire:loading.attr="disabled" class="flex-1 h-12 text-sm font-bold rounded-xl hover:bg-accent transition-colors disabled:opacity-50">{{ __('Cancel') }}</button>
                    <button 
                        wire:click="deletePackageConfirmed" 
                        wire:loading.attr="disabled"
                        wire:target="deletePackageConfirmed"
                        class="flex-1 h-12 bg-destructive text-destructive-foreground text-sm font-black rounded-xl hover:bg-destructive/90 shadow-lg shadow-destructive/20 transition-all disabled:opacity-60 disabled:cursor-not-allowed flex items-center justify-center gap-2">
                        <svg wire:loading wire:target="deletePackageConfirmed" class="animate-spin w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                        </svg>
                        <span wire:loading.remove wire:target="deletePackageConfirmed">{{ __('Uninstall') }}</span>
                        <span wire:loading wire:target="deletePackageConfirmed">{{ __('Wait...') }}</span>
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>