@props(['selectedPackage'])

@php $screenshots = $selectedPackage['screenshots'] ?? []; @endphp

<div 
    x-data="{
        show: false,
        lightbox: false,
        lightboxIndex: 0,
        screenshots: {{ json_encode($screenshots) }},
        openLightbox(index) { this.lightboxIndex = index; this.lightbox = true; },
        closeLightbox() { this.lightbox = false; },
        prev() { this.lightboxIndex = (this.lightboxIndex - 1 + this.screenshots.length) % this.screenshots.length; },
        next() { this.lightboxIndex = (this.lightboxIndex + 1) % this.screenshots.length; },
    }" 
    x-init="$nextTick(() => show = true)"
    @keydown.escape.window="lightbox ? closeLightbox() : null"
    @keydown.arrow-left.window="lightbox && prev()"
    @keydown.arrow-right.window="lightbox && next()"
    class="fixed inset-0 z-[100] flex items-center justify-end overflow-hidden"
>
    <!-- Backdrop -->
    <div 
        x-show="show"
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        wire:click="closeDetails" 
        class="absolute inset-0 bg-background/80 backdrop-blur-md"
    ></div>

    <!-- Sheet -->
    <div 
        x-show="show"
        x-transition:enter="transition ease-out duration-500 cubic-bezier(0.16, 1, 0.3, 1)"
        x-transition:enter-start="translate-x-full"
        x-transition:enter-end="translate-x-0"
        x-transition:leave="transition ease-in duration-300"
        x-transition:leave-start="translate-x-0"
        x-transition:leave-end="translate-x-full"
        class="relative w-full max-w-xl h-full bg-background shadow-[-20px_0_60px_-15px_rgba(0,0,0,0.5)] flex flex-col"
    >
        <div class="p-8 flex items-center justify-between">
            <div class="flex items-center gap-6">
                <div class="w-16 h-16 bg-card rounded-xl flex items-center justify-center text-4xl shadow-sm overflow-hidden">
                    @if(!empty($selectedPackage['icon_url']))
                        <img src="{{ $selectedPackage['icon_url'] }}" class="w-full h-full object-cover" alt="icon">
                    @else
                        📦
                    @endif
                </div>
                <div>
                    <h2 class="text-2xl font-black tracking-tight leading-tight mb-1">{{ $selectedPackage['display_name'] ?? $selectedPackage['Name'] ?? $selectedPackage['ID'] ?? $selectedPackage['Application'] ?? 'Unknown' }}</h2>
                    <p class="text-sm text-muted-foreground font-medium">{{ $selectedPackage['Version'] ?? '---' }}</p>
                </div>
            </div>
            <button wire:click="closeDetails" class="p-3 hover:bg-accent rounded-full transition-colors"><svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg></button>
        </div>
        
        <div class="flex-1 overflow-y-auto p-8 space-y-10 no-scrollbar">
            @if(!empty($screenshots))
                <div class="space-y-4">
                    <h3 class="text-[11px] font-black uppercase tracking-[0.2em] text-muted-foreground">{{ __('Screenshots') }}</h3>
                    <div class="flex gap-4 overflow-x-auto pb-4 no-scrollbar snap-x">
                        @foreach($screenshots as $i => $screen)
                            <img 
                                src="{{ $screen }}" 
                                @click="openLightbox({{ $i }})"
                                class="h-40 rounded-xl border border-border shadow-lg object-cover snap-center hover:scale-[1.02] hover:brightness-110 transition-all cursor-zoom-in flex-shrink-0" 
                                alt="screenshot {{ $i + 1 }}"
                            >
                        @endforeach
                    </div>
                </div>
            @endif

            <div>
                <h3 class="text-[11px] font-black uppercase tracking-[0.2em] text-muted-foreground mb-4">{{ __('Description') }}</h3>
                <p class="text-[17px] leading-relaxed font-medium text-slate-200">{{ $selectedPackage['Description'] ?? __('No description available.') }}</p>
            </div>

            <div class="grid grid-cols-3 gap-4">
                <div class="p-5 rounded-xl bg-card shadow-sm">
                    <p class="text-[10px] font-black uppercase text-muted-foreground tracking-widest mb-2">{{ __('Size') }}</p>
                    <p class="text-base font-black">{{ $selectedPackage['Installed Size'] ?? 'N/A' }}</p>
                </div>
                <div class="p-5 rounded-xl bg-card shadow-sm">
                    <p class="text-[10px] font-black uppercase text-muted-foreground tracking-widest mb-2">{{ __('Download') }}</p>
                    <p class="text-base font-black">{{ $selectedPackage['Download Size'] ?? $selectedPackage['Download Siz'] ?? 'N/A' }}</p>
                </div>
                <div class="p-5 rounded-xl bg-card shadow-sm overflow-hidden">
                    <p class="text-[10px] font-black uppercase text-muted-foreground tracking-widest mb-2">{{ __('License') }}</p>
                    @php $license = $selectedPackage['Licenses'] ?? $selectedPackage['License'] ?? 'Unknown'; @endphp
                    <p class="text-sm font-black truncate" title="{{ $license }}">{{ $license }}</p>
                </div>
            </div>

            <div class="bg-card p-8 rounded-xl shadow-sm space-y-4">
                <h3 class="text-[11px] font-black uppercase tracking-[0.2em] text-muted-foreground mb-4">{{ __('Package Metadata') }}</h3>
                <div class="space-y-4">
                    <div class="flex justify-between items-center text-sm"><span class="text-muted-foreground font-bold">{{ __('Build Date') }}</span><span class="font-black">{{ $selectedPackage['Build Date'] ?? '---' }}</span></div>
                    <div class="flex justify-between items-center text-sm"><span class="text-muted-foreground font-bold">{{ __('Groups') }}</span><span class="font-black">{{ $selectedPackage['Groups'] ?? 'None' }}</span></div>
                    <div class="flex justify-between items-center text-sm"><span class="text-muted-foreground font-bold">{{ __('Architecture') }}</span><span class="font-black">{{ $selectedPackage['Architecture'] ?? 'x86_64' }}</span></div>
                    <div class="flex justify-between items-center text-sm"><span class="text-muted-foreground font-bold">{{ __('Maintainer') }}</span><span class="font-black truncate max-w-[200px]">{{ $selectedPackage['Maintainer'] ?? 'Unknown' }}</span></div>
                </div>
            </div>
        </div>

        <div class="p-8 flex gap-4 mt-auto">
            @if($selectedPackage['is_installed'] ?? false)
                <button wire:click="remove('{{ $selectedPackage['Name'] }}', {{ $selectedPackage['is_flatpak'] ?? false ? 'true' : 'false' }})" class="flex-1 h-12 bg-destructive text-destructive-foreground text-xs font-black rounded-md hover:bg-destructive/90 transition-all uppercase tracking-widest shadow-lg shadow-destructive/20">{{ __('Uninstall Package') }}</button>
            @else
                <button wire:click="install('{{ $selectedPackage['Name'] }}', {{ $selectedPackage['is_aur'] ?? false ? 'true' : 'false' }}, {{ $selectedPackage['is_flatpak'] ?? false ? 'true' : 'false' }})" class="flex-1 h-12 bg-bamboo text-white text-xs font-black rounded-md hover:bg-bamboo/90 transition-all uppercase tracking-widest shadow-lg shadow-bamboo/20">{{ __('Install') }}</button>
            @endif
        </div>
    </div>

    <!-- Lightbox -->
    <div 
        x-show="lightbox"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 scale-95"
        x-transition:enter-end="opacity-100 scale-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 scale-100"
        x-transition:leave-end="opacity-0 scale-95"
        @click.self="closeLightbox()"
        class="fixed inset-0 z-[200] flex items-center justify-center bg-black/90 backdrop-blur-sm"
        style="display: none"
    >
        <!-- Close button -->
        <button @click="closeLightbox()" class="absolute top-6 right-6 p-2 text-white/60 hover:text-white bg-white/10 hover:bg-white/20 rounded-full transition-all">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>

        <!-- Counter -->
        <div class="absolute top-6 left-1/2 -translate-x-1/2 text-white/50 text-xs font-bold tracking-widest uppercase" x-text="(lightboxIndex + 1) + ' / ' + screenshots.length"></div>

        <!-- Prev -->
        <button 
            @click="prev()" 
            x-show="screenshots.length > 1"
            class="absolute left-6 p-3 text-white/60 hover:text-white bg-white/10 hover:bg-white/20 rounded-full transition-all">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        </button>

        <!-- Image -->
        <img 
            :src="screenshots[lightboxIndex]" 
            @click.stop
            class="max-w-[85vw] max-h-[85vh] rounded-2xl shadow-2xl object-contain select-none"
            alt="screenshot"
        >

        <!-- Next -->
        <button 
            @click="next()" 
            x-show="screenshots.length > 1"
            class="absolute right-6 p-3 text-white/60 hover:text-white bg-white/10 hover:bg-white/20 rounded-full transition-all">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
        </button>

        <!-- Dot indicators -->
        <div class="absolute bottom-6 flex gap-2" x-show="screenshots.length > 1">
            <template x-for="(s, i) in screenshots" :key="i">
                <button 
                    @click="lightboxIndex = i"
                    :class="i === lightboxIndex ? 'bg-white w-6' : 'bg-white/30 w-2'"
                    class="h-2 rounded-full transition-all duration-300">
                </button>
            </template>
        </div>
    </div>
</div>
