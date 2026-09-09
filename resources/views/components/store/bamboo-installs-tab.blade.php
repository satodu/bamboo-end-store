@props(['packages', 'pendingInstallations', 'userState' => 'idle', 'activePackage' => null, 'viewMode' => 'grid'])

<div class="mb-10">
    <div class="flex items-center gap-4 mb-2">
        <div class="w-12 h-12 bg-bamboo/20 text-bamboo rounded-xl flex items-center justify-center">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 002-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
        </div>
        <div>
            <h2 class="text-3xl font-black tracking-tight">{{ __('Bamboo Installs') }}</h2>
            <p class="text-xs text-muted-foreground font-bold uppercase tracking-widest">{{ __('Custom Scripts & Optimized Packages') }}</p>
        </div>
    </div>
    <p class="mt-4 text-sm text-muted-foreground leading-relaxed">
        {{ __('Esses pacotes requerem passos extras de instalação (como dependências de 32 bits, wine ou aceitação de termos) que os gerenciadores tradicionais falham em lidar automaticamente. O Bamboo automatiza esses passos para você usando scripts especiais.') }}
    </p>
</div>

@if(empty($packages))
    <div class="py-20 text-center flex flex-col items-center justify-center gap-6">
        <div class="pacman-container scale-125">
            <div class="pacman"></div>
            <div class="dot"></div>
            <div class="dot"></div>
            <div class="dot"></div>
        </div>
        <p class="text-xs font-black tracking-[0.4em] uppercase text-bamboo animate-pulse">
            {{ __('Loading Scripts...') }}
        </p>
    </div>
@else
    @if($viewMode === 'grid')
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
            @foreach($packages as $pkg)
                <x-store.package-card :$pkg :$pendingInstallations :$userState :$activePackage wire:key="bamboo-{{ $pkg['name'] }}" />
            @endforeach
        </div>
    @else
        <div class="flex flex-col gap-4">
            @foreach($packages as $pkg)
                <x-store.package-list-card :$pkg :$pendingInstallations :$userState :$activePackage wire:key="bamboo-{{ $pkg['name'] }}" />
            @endforeach
        </div>
    @endif
@endif
