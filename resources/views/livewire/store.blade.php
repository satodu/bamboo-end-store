<?php

use Livewire\Volt\Component;
use App\Services\PackageService;
use App\Services\AppImageService;
use Native\Laravel\Facades\Notification;
use Native\Laravel\Facades\Alert;
use Native\Laravel\Dialog;
use Illuminate\Support\Facades\Storage;

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
    
    // Terminal Integrado
    public $isTerminalOpen = false;
    public $terminalOutput = "";
    public $activeTerminalLog = "";
    public $activeTerminalPid = null;
    public $activePackageName = "";
    public $terminalDone = false; // true quando o processo termina — aciona countdown no Alpine
    // Paginação
    public $page = 1;
    public $totalResults = 0;
    
    // Configurações
    public $settings = [
        'enable_aur' => true,
        'enable_flatpak' => false,
        'search_limit' => 50,
        'appimage_path' => '',
        'locale' => 'system'
    ];

    public $sysInfo = [
        'kernel' => '',
        'hostname' => '',
        'os' => 'BambooOS'
    ];

    public function mount()
    {
        $this->sysInfo['kernel'] = php_uname('r');
        $this->sysInfo['hostname'] = gethostname();
        $this->loadSettings();
        
        if (empty($this->settings['appimage_path'])) {
            $this->settings['appimage_path'] = (new AppImageService())->getDefaultDirectory();
        }

        $this->loadData();
        $this->checkPendingInstallations();
    }

    protected function t(string $key): string
    {
        return __($key);
    }

    public function runSystemUpdate()
    {
        $service = new PackageService();
        $service->runBambooUpdate();
        $this->showNotification($this->t('refreshed'), $this->t('update_started'));
    }

    public function checkPendingInstallations()
    {
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
            $res = \Illuminate\Support\Facades\Process::run("pacman -Qq " . escapeshellarg($name));
        }
        
        $isCurrentlyInstalled = $res->successful();
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

        $this->showNotification($this->t('saved'), $this->t('settings_saved'));
        $this->loadData();
    }

    public function installFlatpak()
    {
        $logFile = storage_path('logs/flatpak_setup.log');
        file_put_contents($logFile, "Instalando flatpak...\n");
        $inner = "pacman -S --noconfirm flatpak >> " . escapeshellarg($logFile) . " 2>&1";
        $cmd = "( pkexec sh -c " . escapeshellarg($inner)
            . " || echo 'Autenticação cancelada.' >> " . escapeshellarg($logFile)
            . " ; echo '__PROCESS_DONE__' >> " . escapeshellarg($logFile) . " ) &";
        shell_exec($cmd);
        $this->activeTerminalLog = $logFile;
        $this->activePackageName = 'flatpak';
        $this->isTerminalOpen = true;
        $this->pendingInstallations['flatpak'] = ['type' => 'install', 'is_flatpak' => false, 'log' => $logFile];
        $this->showNotification($this->t('installing'), __('Installing Flatpak...'));
    }

    public function addFlathubRemote()
    {
        $logFile = storage_path('logs/flatpak_setup.log');
        file_put_contents($logFile, "Adicionando Flathub...\n");
        $inner = "flatpak remote-add --if-not-exists flathub https://dl.flathub.org/repo/flathub.flatpakrepo >> " . escapeshellarg($logFile) . " 2>&1";
        $cmd = "( " . $inner . " ; echo '__PROCESS_DONE__' >> " . escapeshellarg($logFile) . " ) &";
        shell_exec($cmd);
        $this->activeTerminalLog = $logFile;
        $this->activePackageName = 'flathub-remote';
        $this->isTerminalOpen = true;
        $this->pendingInstallations['flathub-remote'] = ['type' => 'install', 'is_flatpak' => false, 'log' => $logFile];
        $this->showNotification($this->t('installing'), __('Configuring Flathub remote...'));
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
        if ($tab !== 'settings') {
            $this->search = '';
        }
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

        if ($this->tab === 'installed') {
            $this->loadInstalled();
            $this->systemPackages = [];
        } else {
            if (empty($this->search) || strlen($this->search) < 3) {
                $this->getFeaturedPackages();
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
                $pkg['installed'] = in_array($pkg['name'], $installedNames);
            }
        }
        
        foreach ($this->systemPackages as &$pkg) {
            $pkg['installed'] = in_array($pkg['name'], $installedNames);
        }
    }

    public function updatedSearch()
    {
        $this->page = 1;
        // Limpa imediatamente para evitar flash de resultados stale de requests anteriores
        $this->packages = [];
        $this->totalResults = 0;
        $this->loadData();
    }

    public function clearSearch()
    {
        $this->search = '';
        $this->page = 1;
        $this->loadData();
    }

    public function updatedFilterRepo()
    {
        // Na home (explore sem busca), filtro não tem efeito
        if ($this->tab === 'explore' && empty($this->search)) return;
        if ($this->tab === 'featured') return;

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

    public function install($name, $isAur = false, $isFlatpak = false)
    {
        $service = new PackageService();
        
        if ($isAur) {
            $pid = $service->install($name, $isAur, $isFlatpak);
            
            if ($pid) {
                $this->pendingInstallations[$name] = [
                    'pid' => $pid,
                    'type' => 'install',
                    'is_flatpak' => $isFlatpak,
                    'log' => null
                ];
                
                \Illuminate\Support\Facades\Cache::put("installing_{$name}", true, 600);
                $this->showNotification($this->t('installing'), sprintf($this->t('install_start'), $name));
            } else {
                $this->showNotification($this->t('error'), sprintf($this->t('install_fail'), $name), 'error');
            }
            
            $this->loadData();
            return;
        }

        // Criar arquivo de log temporário
        $logFile = storage_path("logs/install_{$name}.log");
        if (!file_exists(dirname($logFile))) mkdir(dirname($logFile), 0777, true);
        file_put_contents($logFile, "Starting installation of {$name}...\n");

        $pid = $service->runInBackground($name, $isAur, $isFlatpak, $logFile);
        
        if ($pid) {
            $this->activeTerminalLog = $logFile;
            $this->activeTerminalPid = $pid;
            $this->activePackageName = $name;
            $this->isTerminalOpen = true;
            $this->terminalDone = false;
            $this->terminalOutput = "Waiting for authentication...";

            $this->pendingInstallations[$name] = [
                'pid' => $pid,
                'type' => 'install',
                'is_flatpak' => $isFlatpak,
                'log' => $logFile
            ];
            
            \Illuminate\Support\Facades\Cache::put("installing_{$name}", true, 600);
            $this->showNotification($this->t('installing'), sprintf($this->t('install_start'), $name));
        } else {
            $this->showNotification($this->t('error'), sprintf($this->t('install_fail'), $name), 'error');
        }

        $this->loadData();
    }

    public function pollTerminal()
    {
        if (!$this->isTerminalOpen || !$this->activeTerminalLog) return;

        if (file_exists($this->activeTerminalLog)) {
            $rawOutput = file_get_contents($this->activeTerminalLog) ?: '';
            $sentinel = '__PROCESS_DONE__';

            if (str_contains($rawOutput, $sentinel)) {
                // Remover a linha do sentinel da saida visível
                $cleanOutput = str_replace("\n" . $sentinel, '', $rawOutput);
                $this->terminalOutput = $cleanOutput . "\n\n[✅ Processo finalizado]"; 
                
                // Capturar dados ANTES de qualquer limpeza
                $pendingData = $this->pendingInstallations[$this->activePackageName] ?? null;
                $packageName = $this->activePackageName;
                $this->activeTerminalPid = null;

                if ($pendingData) {
                    $this->checkIfFinishedManually($packageName, $pendingData);
                }

                // Dispara o countdown de auto-close via propriedade Livewire
                $this->terminalDone = true;
                $this->dispatch('content-updated');
                return;
            }


            // Processo ainda em andamento - mostar as ultimas 50 linhas
            $lines = explode("\n", $rawOutput);
            $this->terminalOutput = implode("\n", array_slice($lines, -50));
        }

        $this->dispatch('content-updated');
    }

    public function closeTerminal()
    {
        $this->isTerminalOpen = false;
        $this->terminalDone = false;
        if ($this->activeTerminalLog && file_exists($this->activeTerminalLog)) {
            unlink($this->activeTerminalLog);
        }
        $this->activeTerminalLog = "";
        $this->activeTerminalPid = null;
        $this->terminalOutput = "";
    }

    public $confirmingPackageIsFlatpak = false;

    public function remove($name, $isFlatpak = false)
    {
        $this->confirmingPackageRemoval = $name;
        $this->confirmingPackageIsFlatpak = $isFlatpak;
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
        
        if (!$name) return;

        // Fechar o modal IMEDIATAMENTE — o terminal vai substituí-lo
        $this->confirmingPackageRemoval = null;
        $this->confirmingPackageIsFlatpak = false;

        $service = new PackageService();
        
        // Log para remoção
        $logFile = storage_path("logs/remove_{$name}.log");
        if (!file_exists(dirname($logFile))) mkdir(dirname($logFile), 0777, true);
        file_put_contents($logFile, "Iniciando remoção de {$name}...\n");

        $pid = $this->runRemoveInBackground($name, $isFlatpak, $logFile);
        
        if ($pid) {
            $this->activeTerminalLog = $logFile;
            $this->activeTerminalPid = $pid;
            $this->activePackageName = $name;
            $this->isTerminalOpen = true;
            $this->terminalDone = false;

            $this->pendingInstallations[$name] = [
                'pid' => $pid,
                'type' => 'remove',
                'is_flatpak' => $isFlatpak,
                'log' => $logFile
            ];
            $this->showNotification($this->t('removing'), sprintf($this->t('remove_start'), $name));
        } else {
            $this->showNotification($this->t('error'), sprintf($this->t('remove_fail'), $name), 'error');
        }
        // loadData() removido aqui — o pollTerminal chama finalizeOperation → loadData() quando terminar
    }

    protected function getFlatpakInstallation(string $name): string
    {
        $res = \Illuminate\Support\Facades\Process::run(
            "flatpak list --columns=application,installation | grep -F '" . str_replace("'", "'\\''", $name) . "' | awk '{print \$NF}'"
        );
        $scope = trim($res->output());
        return in_array($scope, ['user', 'system']) ? $scope : 'system';
    }

    protected function buildSentinelCommand(string $baseCmd, string $logFile): string
    {
        $sentinel = '__PROCESS_DONE__';
        // Append sentinel to log when command finishes (success or fail)
        return "( $baseCmd >> " . escapeshellarg($logFile) . " 2>&1 ; echo " . escapeshellarg($sentinel) . " >> " . escapeshellarg($logFile) . " ) &";
    }

    protected function runRemoveInBackground($name, $isFlatpak, $logFile)
    {
        file_put_contents($logFile, "Iniciando remoção de {$name}...\n");
        
        if ($isFlatpak) {
            $scope = $this->getFlatpakInstallation($name);
            if ($scope === 'user') {
                $cmd = "flatpak uninstall -y --user " . escapeshellarg($name);
                // Apps de usuário: sem pkexec, sentinela no wrapper externo
                $fullCmd = "( $cmd >> " . escapeshellarg($logFile) . " 2>&1 ; echo '__PROCESS_DONE__' >> " . escapeshellarg($logFile) . " ) &";
            } else {
                $cmd = "flatpak uninstall -y --system " . escapeshellarg($name);
                // Sentinela FORA do pkexec: escrito mesmo se o usuário cancelar
                $fullCmd = "( pkexec sh -c " . escapeshellarg($cmd . " >> " . escapeshellarg($logFile) . " 2>&1") . " || echo 'Autenticação cancelada ou falhou.' >> " . escapeshellarg($logFile) . " ; echo '__PROCESS_DONE__' >> " . escapeshellarg($logFile) . " ) &";
            }
        } else {
            $cmd = "pacman -Rs --noconfirm " . escapeshellarg($name);
            // Sentinela FORA do pkexec
            $fullCmd = "( pkexec sh -c " . escapeshellarg($cmd . " >> " . escapeshellarg($logFile) . " 2>&1") . " || echo 'Autenticação cancelada ou falhou.' >> " . escapeshellarg($logFile) . " ; echo '__PROCESS_DONE__' >> " . escapeshellarg($logFile) . " ) &";
        }

        shell_exec($fullCmd);
        return 1;
    }

    public function showNotification($title, $message, $type = 'success')
    {
        $this->toast = [
            'show' => true,
            'message' => $message,
            'type' => $type
        ];
        
        $this->dispatch('notify');

        // Notificação do sistema via NativePHP
        try {
            \Native\Laravel\Facades\Notification::title($title)
                ->message($message)
                ->show();
        } catch (\Throwable $e) {
            // NativePHP pode não estar disponível
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
    class="flex h-screen bg-background text-foreground overflow-hidden relative font-sans" 
    x-data="{ show: false, timeout: null }" 
    x-on:notify.window="show = true; clearTimeout(timeout); timeout = setTimeout(() => show = false, 4000)"
    @if(count($pendingInstallations) > 0) wire:poll.2s="checkPendingInstallations" @endif
>
    
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
        @if($tab === 'explore' || $tab === 'installed')
            <x-store.header :$search :$tab :$filterRepo :$settings />

            <!-- Content Area -->
            <div class="flex-1 overflow-y-auto">
                <div class="max-w-7xl mx-auto p-10">

                    {{-- === HOME (explore sem busca ativa) === --}}
                    @if($tab === 'explore' && (empty($search) || strlen($search) < 3))
                        @if(!empty($search) && strlen($search) < 3)
                            <p class="text-sm text-bamboo font-bold italic tracking-wide mb-8">Enter at least 3 characters for a global search...</p>
                        @endif
                        <x-store.explore-home-tab
                            :$packages
                            :$systemPackages
                            :$pendingInstallations
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
                        </div>

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
                    @endif

                </div>
            </div>
        @elseif($tab === 'appimages')
            <x-store.appimages-tab :$appImages />
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
                    <div class="w-16 h-16 bg-destructive/10 text-destructive rounded-2xl flex items-center justify-center mb-6">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                        </svg>
                    </div>
                    
                    <h3 class="text-2xl font-black tracking-tight mb-3">{{ __('Confirm AppImage Deletion') }}</h3>
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
                    <div class="w-16 h-16 bg-destructive/10 text-destructive rounded-2xl flex items-center justify-center mb-6">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                        </svg>
                    </div>
                    
                    <h3 class="text-2xl font-black tracking-tight mb-3">{{ __('Confirm Package Removal') }}</h3>
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

    <!-- Terminal Sheet -->
    <div 
        x-data="{
            show: @entangle('isTerminalOpen'),
            countdown: 0,
            countdownTimer: null,
            startCountdown() {
                if (this.countdown > 0) return; // já está rodando
                this.countdown = 5;
                clearInterval(this.countdownTimer);
                this.countdownTimer = setInterval(() => {
                    this.countdown--;
                    if (this.countdown <= 0) {
                        clearInterval(this.countdownTimer);
                        this.$wire.closeTerminal();
                    }
                }, 1000);
            },
            cancelCountdown() {
                clearInterval(this.countdownTimer);
                this.countdown = 0;
            }
        }"
        x-init="
            if (@js($terminalDone)) startCountdown();
            $wire.$watch('terminalDone', val => { if(val) startCountdown(); });
        "
        x-show="show"
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="translate-y-full"
        x-transition:enter-end="translate-y-0"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="translate-y-0"
        x-transition:leave-end="translate-y-full"
        class="fixed bottom-0 left-0 right-0 z-[200] h-[40vh] bg-[#0c0c0c] border-t border-white/10 shadow-[0_-20px_50px_rgba(0,0,0,0.5)] flex flex-col"
        wire:poll.1s="pollTerminal"
    >
        <div class="flex items-center justify-between px-6 py-3 bg-white/5 border-b border-white/5">
            <div class="flex items-center gap-3">
                <div class="flex gap-1.5">
                    <div class="w-3 h-3 rounded-full bg-red-500/50"></div>
                    <div class="w-3 h-3 rounded-full bg-yellow-500/50"></div>
                    <div class="w-3 h-3 rounded-full bg-green-500/50"></div>
                </div>
                <span class="text-[11px] font-black uppercase tracking-widest text-muted-foreground ml-2">{{ __('Integrated Console') }} — {{ $activePackageName }}</span>
            </div>
            <div class="flex items-center gap-4">
                @if(!$activeTerminalPid)
                    {{-- Close button com countdown --}}
                    <div class="relative flex items-center justify-center">
                        {{-- SVG circular countdown --}}
                        <svg x-show="countdown > 0" class="absolute w-12 h-12 -rotate-90" viewBox="0 0 36 36">
                            <circle cx="18" cy="18" r="15" fill="none" stroke="rgba(255,255,255,0.1)" stroke-width="2"/>
                            <circle 
                                cx="18" cy="18" r="15" fill="none" 
                                stroke="var(--bamboo)" stroke-width="2"
                                stroke-dasharray="94.25"
                                :stroke-dashoffset="94.25 - (94.25 * countdown / 5)"
                                style="transition: stroke-dashoffset 1s linear;"
                            />
                        </svg>
                        <button 
                            wire:click="closeTerminal" 
                            @click="cancelCountdown()"
                            class="relative text-[10px] font-black uppercase tracking-widest bg-bamboo px-4 py-1.5 rounded hover:bg-bamboo/90 transition-all">
                            <span x-show="countdown <= 0">{{ __('Close') }}</span>
                            <span x-show="countdown > 0" x-text="'{{ __('Close') }} (' + countdown + 's)'"></span>
                        </button>
                    </div>
                @else
                    <div class="flex items-center gap-2">
                        <div class="w-2 h-2 bg-bamboo rounded-full animate-pulse"></div>
                        <span class="text-[10px] font-black uppercase tracking-widest text-bamboo">{{ __('Process Running') }}</span>
                    </div>
                @endif
            </div>
        </div>
        
        <div 
            x-ref="terminalBody"
            class="flex-1 overflow-y-auto p-6 font-mono text-sm text-green-500/90 leading-relaxed no-scrollbar"
            x-init="$watch('show', value => { if(value) { $nextTick(() => $refs.terminalBody.scrollTop = $refs.terminalBody.scrollHeight) } })"
            @content-updated.window="$nextTick(() => $refs.terminalBody.scrollTop = $refs.terminalBody.scrollHeight)"
        >
            <pre class="whitespace-pre-wrap">{{ $terminalOutput }}</pre>
            @if($activeTerminalPid)
                <div class="mt-4 flex items-center gap-2 italic text-muted-foreground animate-pulse">
                    <span>></span>
                    <span class="w-2 h-4 bg-muted-foreground"></span>
                </div>
            @endif
        </div>
    </div>
</div>