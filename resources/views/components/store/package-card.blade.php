@props(['pkg', 'pendingInstallations', 'userState' => 'idle', 'activePackage' => null])

@php 
    $isPending = isset($pendingInstallations[$pkg['name']]);
    $isAurVal = (isset($pkg['is_aur']) && $pkg['is_aur']) ? 'true' : 'false';
    $isFlatpakVal = (isset($pkg['is_flatpak']) && $pkg['is_flatpak']) ? 'true' : 'false';
    $emoji = match(true) { 
        str_contains(strtolower($pkg['name']), 'steam') => '🎮', 
        str_contains(strtolower($pkg['name']), 'discord') => '💬', 
        str_contains(strtolower($pkg['name']), 'brave') => '🦁', 
        str_contains(strtolower($pkg['name']), 'code') => '💻', 
        str_contains(strtolower($pkg['name']), 'vlc') => '🎬', 
        str_contains(strtolower($pkg['name']), 'obs') => '🎥', 
        str_contains(strtolower($pkg['name']), 'spotify') => '🎧', 
        default => '📦' 
    };
    $firstScreenshot = !empty($pkg['screenshots'][0]) ? $pkg['screenshots'][0] : null;
@endphp

<div {{ $attributes->merge(['class' => 'bg-card rounded-lg hover:shadow-lg transition-all flex flex-col h-full group relative overflow-hidden']) }}>
    
    {{-- Screenshot Banner (quando disponível) --}}
    @if($firstScreenshot)
        <div class="relative w-full h-36 overflow-hidden shrink-0">
            <img 
                src="{{ $firstScreenshot }}" 
                class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500"
                alt="Screenshot"
                onerror="this.parentElement.style.display='none'"
            >
            {{-- Gradient overlay --}}
            <div class="absolute inset-0 bg-gradient-to-t from-card via-card/20 to-transparent"></div>
            {{-- Logo do software (somente icon_url, sem emoji) --}}
            @if(!empty($pkg['icon_url']))
                <div class="absolute bottom-3 left-4 w-10 h-10 bg-card/80 backdrop-blur-sm rounded-xl overflow-hidden shadow-lg ring-1 ring-white/10">
                    <img src="{{ $pkg['icon_url'] }}" class="w-full h-full object-contain p-1" alt="icon" onerror="this.parentElement.style.display='none'">
                </div>
            @endif
            {{-- Badges --}}
            <div class="absolute top-2 right-2 flex flex-col items-end gap-1">
                @if($pkg['installed']) <span class="text-[9px] font-black bg-bamboo/80 backdrop-blur-sm text-white px-2.5 py-0.5 rounded uppercase">{{ __('Installed') }}</span> @endif
                @if(isset($pkg['is_aur']) && $pkg['is_aur']) <span class="text-[9px] font-black bg-blue-500/80 backdrop-blur-sm text-white px-2 py-0.5 rounded uppercase tracking-widest">AUR</span>
                @elseif(isset($pkg['is_flatpak']) && $pkg['is_flatpak']) <span class="text-[9px] font-black bg-orange-500/80 backdrop-blur-sm text-white px-2 py-0.5 rounded uppercase tracking-widest">Flatpak</span>
                @else <span class="text-[9px] font-black bg-purple-500/80 backdrop-blur-sm text-white px-2 py-0.5 rounded uppercase tracking-widest">{{ $pkg['repo'] ?? __('Official') }}</span> @endif
            </div>
        </div>
        <div class="p-5 flex flex-col flex-1">
            <h3 class="text-[17px] font-black mb-1 truncate tracking-tight">{{ $pkg['display_name'] ?? $pkg['name'] }}</h3>
            <p class="text-[13px] text-muted-foreground mb-4 line-clamp-2 leading-relaxed flex-1">{{ $pkg['description'] }}</p>
            <div class="flex items-center gap-3">
                @if($pkg['installed'])
                    <button wire:click="remove('{{ $pkg['name'] }}', {{ $isFlatpakVal }})" @if($userState !== 'idle') disabled @endif class="flex-1 h-10 bg-destructive/10 hover:bg-destructive text-destructive hover:text-destructive-foreground text-[11px] font-black rounded transition-all uppercase tracking-widest disabled:opacity-50">
                        <span wire:loading.remove wire:target="remove('{{ $pkg['name'] }}', {{ $isFlatpakVal }})">
                            @if($userState === 'uninstalling' && $activePackage === $pkg['name'])
                                {{ __('Uninstalling...') }}
                            @else
                                {{ __('Uninstall') }}
                            @endif
                        </span>
                        <span wire:loading wire:target="remove('{{ $pkg['name'] }}', {{ $isFlatpakVal }})">{{ __('Wait...') }}</span>
                    </button>
                @elseif($isPending)
                    <button disabled class="flex-1 h-10 bg-bamboo/20 text-bamboo text-[11px] font-black rounded transition-all uppercase tracking-widest animate-pulse">{{ __('Finalizing...') }}</button>
                @else
                    <button wire:click="install('{{ $pkg['name'] }}', {{ $isAurVal }}, {{ $isFlatpakVal }})" @if($userState !== 'idle') disabled @endif class="flex-1 h-10 bg-bamboo hover:bg-bamboo/90 text-white text-[11px] font-black rounded transition-all uppercase tracking-widest shadow-md disabled:opacity-50">
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

    {{-- Layout padrão (sem screenshot) --}}
    @else
        <div class="p-6 flex flex-col h-full">
            <div class="flex items-start justify-between mb-5">
                <div class="w-14 h-14 bg-muted rounded-lg flex items-center justify-center text-3xl group-hover:scale-105 transition-transform overflow-hidden">
                    @if(!empty($pkg['icon_url']))
                        <img src="{{ $pkg['icon_url'] }}" class="w-full h-full object-cover" alt="icon" onerror="this.style.display='none'">
                    @endif
                    <span class="group-hover:scale-110 transition-transform">{{ $emoji }}</span>
                </div>
                <div class="flex flex-col items-end gap-1.5">
                    @if($pkg['installed']) <span class="text-[9px] font-black bg-bamboo/10 text-bamboo px-2.5 py-0.5 rounded uppercase">{{ __('Installed') }}</span> @endif
                    @if(isset($pkg['is_aur']) && $pkg['is_aur']) <span class="text-[9px] font-black bg-blue-400/10 text-blue-400 px-2 py-0.5 rounded uppercase tracking-widest">AUR</span>
                    @elseif(isset($pkg['is_flatpak']) && $pkg['is_flatpak']) <span class="text-[9px] font-black bg-orange-400/10 text-orange-400 px-2 py-0.5 rounded uppercase tracking-widest">Flatpak</span>
                    @else <span class="text-[9px] font-black bg-purple-400/10 text-purple-400 px-2 py-0.5 rounded uppercase tracking-widest">{{ $pkg['repo'] ?? __('Official') }}</span> @endif
                </div>
            </div>
            <h3 class="text-[17px] font-black mb-1 truncate tracking-tight">{{ $pkg['display_name'] ?? $pkg['name'] }}</h3>
            <p class="text-[13px] text-muted-foreground mb-6 line-clamp-2 leading-relaxed h-10">{{ $pkg['description'] }}</p>
            
            <div class="mt-auto flex items-center gap-3 pt-6">
                @if($pkg['installed'])
                    <button wire:click="remove('{{ $pkg['name'] }}', {{ $isFlatpakVal }})" @if($userState !== 'idle') disabled @endif class="flex-1 h-10 bg-destructive/10 hover:bg-destructive text-destructive hover:text-destructive-foreground text-[11px] font-black rounded transition-all uppercase tracking-widest disabled:opacity-50">
                        <span wire:loading.remove wire:target="remove('{{ $pkg['name'] }}', {{ $isFlatpakVal }})">
                            @if($userState === 'uninstalling' && $activePackage === $pkg['name'])
                                {{ __('Uninstalling...') }}
                            @else
                                {{ __('Uninstall') }}
                            @endif
                        </span>
                        <span wire:loading wire:target="remove('{{ $pkg['name'] }}', {{ $isFlatpakVal }})">{{ __('Wait...') }}</span>
                    </button>
                @elseif($isPending)
                    <button disabled class="flex-1 h-10 bg-bamboo/20 text-bamboo text-[11px] font-black rounded transition-all uppercase tracking-widest animate-pulse">{{ __('Finalizing...') }}</button>
                @else
                    <button wire:click="install('{{ $pkg['name'] }}', {{ $isAurVal }}, {{ $isFlatpakVal }})" @if($userState !== 'idle') disabled @endif class="flex-1 h-10 bg-bamboo hover:bg-bamboo/90 text-white text-[11px] font-black rounded transition-all uppercase tracking-widest shadow-md disabled:opacity-50">
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
    @endif
</div>
