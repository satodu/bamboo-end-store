@props(['pkg', 'pendingInstallations', 'userState' => 'idle', 'activePackage' => null])

@php 
    $isPending = isset($pendingInstallations[$pkg['name']]);
    $isAurVal = (isset($pkg['is_aur']) && $pkg['is_aur']) ? 'true' : 'false';
    $isFlatpakVal = (isset($pkg['is_flatpak']) && $pkg['is_flatpak']) ? 'true' : 'false';
    $firstScreenshot = !empty($pkg['screenshots'][0]) ? $pkg['screenshots'][0] : null;
@endphp

<div {{ $attributes->merge(['class' => 'bg-card rounded-lg hover:shadow-lg transition-all flex items-center p-4 gap-5 group relative overflow-hidden w-full']) }}>
    
    {{-- Thumbnail / Icon no lado esquerdo --}}
    @if($firstScreenshot)
        <div class="relative w-36 h-24 overflow-hidden rounded-lg shrink-0 bg-muted">
            <img 
                src="{{ $firstScreenshot }}" 
                class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500"
                alt="Screenshot"
                onerror="this.parentElement.style.display='none'"
            >
            {{-- Gradient overlay --}}
            <div class="absolute inset-0 bg-gradient-to-t from-card via-card/20 to-transparent"></div>
            {{-- Logo --}}
            <div class="absolute bottom-2 left-2 w-7 h-7 bg-card/90 backdrop-blur-sm rounded-lg overflow-hidden shadow shadow-black/20 ring-1 ring-white/10 p-0.5">
                <x-store.package-icon :name="$pkg['name']" :iconUrl="!empty($pkg['icon_url']) ? $pkg['icon_url'] : null" :isFlatpak="$pkg['is_flatpak'] ?? false" />
            </div>
        </div>
    @else
        <div class="w-24 h-24 bg-muted/60 rounded-lg flex items-center justify-center text-4xl shrink-0 group-hover:scale-102 transition-all duration-300 relative overflow-hidden">
            <x-store.package-icon :name="$pkg['name']" :iconUrl="!empty($pkg['icon_url']) ? $pkg['icon_url'] : null" :isFlatpak="$pkg['is_flatpak'] ?? false" class="w-14 h-14" />
        </div>
    @endif

    {{-- Detalhes (Nome, badges e descrição) no centro --}}
    <div class="flex-1 min-w-0 flex flex-col justify-center">
        <div class="flex items-center gap-2 mb-1.5 flex-wrap">
            <h3 class="text-lg font-black tracking-tight truncate max-w-[200px] sm:max-w-[350px] md:max-w-[450px]">
                {{ $pkg['display_name'] ?? $pkg['name'] }}
            </h3>
            
            {{-- Badges alinhadas com o título --}}
            <div class="flex items-center gap-1.5">
                @if($pkg['installed']) 
                    <span class="text-[9px] font-black bg-bamboo/10 text-bamboo px-2 py-0.5 rounded uppercase">{{ __('Installed') }}</span> 
                @endif
                
                @if(isset($pkg['is_aur']) && $pkg['is_aur']) 
                    <span class="text-[9px] font-black bg-blue-400/10 text-blue-400 px-2 py-0.5 rounded uppercase tracking-widest">AUR</span>
                @elseif(isset($pkg['is_flatpak']) && $pkg['is_flatpak']) 
                    <span class="text-[9px] font-black bg-orange-400/10 text-orange-400 px-2 py-0.5 rounded uppercase tracking-widest">Flatpak</span>
                @else 
                    <span class="text-[9px] font-black bg-purple-400/10 text-purple-400 px-2 py-0.5 rounded uppercase tracking-widest">{{ $pkg['repo'] ?? __('Official') }}</span> 
                @endif
            </div>
        </div>
        <p class="text-[13px] text-muted-foreground line-clamp-2 leading-relaxed max-w-2xl">
            {{ $pkg['description'] }}
        </p>
    </div>

    {{-- Botões de Ação no lado direito --}}
    <div class="flex items-center gap-3 shrink-0 ml-auto pl-4">
        @if($pkg['installed'])
            <button wire:click="remove('{{ $pkg['name'] }}', {{ $isFlatpakVal }})" @if($userState !== 'idle') disabled @endif class="h-10 px-5 bg-destructive/10 hover:bg-destructive text-destructive hover:text-destructive-foreground text-[11px] font-black rounded transition-all uppercase tracking-widest disabled:opacity-50 min-w-[120px]">
                <span wire:loading.remove wire:target="remove('{{ $pkg['name'] }}', {{ $isFlatpakVal }})">
                    @if($userState === 'uninstalling' && $activePackage === $pkg['name'])
                        {{ __('Uninstalling...') }}
                    @else
                        {{ __('Uninstall') }}
                    @endif
                </span>
                <span wire:loading wire:target="remove('{{ $pkg['name'] }}', {{ $isFlatpakVal }})">{{ __('Wait...') }}</span>
            </button>
            {{-- Launch button --}}
            <button
                wire:click="launchPackage('{{ $pkg['name'] }}', {{ $isFlatpakVal }})"
                title="{{ __('Launch') }}"
                class="h-10 w-10 bg-bamboo/10 hover:bg-bamboo text-bamboo hover:text-white rounded flex items-center justify-center transition-all duration-200 shrink-0"
            >
                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M4 13a8 8 0 0 1 7 7a6 6 0 0 0 3-5a9 9 0 0 0 6-8a3 3 0 0 0-3-3a9 9 0 0 0-8 6a6 6 0 0 0-5 3" />
                    <path d="M7 14a6 6 0 0 0-3 6a6 6 0 0 0 6-3m4-8a1 1 0 1 0 2 0a1 1 0 1 0-2 0" />
                </svg>
            </button>
        @elseif($isPending)

            <button disabled class="h-10 px-5 bg-bamboo/20 text-bamboo text-[11px] font-black rounded transition-all uppercase tracking-widest animate-pulse min-w-[120px]">{{ __('Finalizing...') }}</button>
        @else
            <button wire:click="install('{{ $pkg['name'] }}', {{ $isAurVal }}, {{ $isFlatpakVal }})" @if($userState !== 'idle') disabled @endif class="h-10 px-5 bg-bamboo hover:bg-bamboo/90 text-white text-[11px] font-black rounded transition-all uppercase tracking-widest shadow-md disabled:opacity-50 min-w-[120px]">
                <span wire:loading.remove wire:target="install('{{ $pkg['name'] }}', {{ $isAurVal }}, {{ $isFlatpakVal }})">
                    @if($userState === 'installing' && $activePackage === $pkg['name'])
                        {{ __('Installing...') }}
                    @else
                        {{ __('Install') }}
                    @endif
                </span>
                <span wire:loading wire:target="install('{{ $pkg['name'] }}', {{ $isAurVal }}, {{ $isFlatpakVal }})">{{ __('Loading...') }}</span>
            </button>
        @endif

        <button
            wire:click="showDetails('{{ $pkg['name'] }}', {{ $isAurVal }}, {{ $isFlatpakVal }})"
            wire:loading.attr="disabled"
            wire:target="showDetails('{{ $pkg['name'] }}', {{ $isAurVal }}, {{ $isFlatpakVal }})"
            class="h-10 w-10 bg-accent/50 rounded flex items-center justify-center text-muted-foreground hover:bg-accent transition-colors"
        >
            <svg wire:loading.remove wire:target="showDetails('{{ $pkg['name'] }}', {{ $isAurVal }}, {{ $isFlatpakVal }})" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            <svg wire:loading wire:target="showDetails('{{ $pkg['name'] }}', {{ $isAurVal }}, {{ $isFlatpakVal }})" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/></svg>
        </button>
    </div>
</div>
