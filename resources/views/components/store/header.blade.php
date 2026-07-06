@props(['search', 'tab', 'filterRepo', 'settings'])

<header {{ $attributes->merge(['class' => 'h-16 shadow-md flex items-center px-8 gap-6 bg-background/95 backdrop-blur-xl sticky top-0 z-40']) }}>
    <div class="relative flex-1 group">
        <div wire:loading wire:target="search" class="absolute left-5 top-1/2 -translate-y-1/2 z-10">
            <svg class="animate-spin h-5 w-5 text-bamboo" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
        </div>
        <svg wire:loading.remove wire:target="search" class="absolute left-5 top-1/2 -translate-y-1/2 w-5 h-5 text-muted-foreground group-focus-within:text-bamboo transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
        <input 
            wire:model.live.debounce.600ms="search"
            type="text" 
            placeholder="{{ $tab === 'installed' ? __('Search installed applications...') : __('Search packages...') }}" 
            class="w-full h-10 bg-background border border-input rounded-md pl-12 pr-10 text-[15px] placeholder:text-muted-foreground focus:ring-2 focus:ring-bamboo focus:border-bamboo outline-none transition-all"
        >
        @if(!empty($search))
            <button 
                wire:click="clearSearch"
                class="absolute right-3 top-1/2 -translate-y-1/2 p-1.5 hover:bg-muted rounded text-muted-foreground hover:text-foreground transition-all"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        @endif
    </div>

    <div class="flex items-center gap-3">
        <!-- Shadcn Style Repo Select -->
        <div x-data="{ open: false, selected: @entangle('filterRepo').live }" class="relative">
            <button 
                @click="open = !open" 
                @click.away="open = false"
                class="h-10 w-48 flex items-center justify-between rounded-md border border-input bg-background px-3 py-2 text-xs font-medium ring-offset-background placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 hover:bg-accent/50 transition-colors"
            >
                <span x-text="{
                    'all': '{{ __('All Repositories') }}',
                    'official': '{{ __('Official') }}',
                    'aur': '{{ __('AUR') }}',
                    'flatpak': '{{ __('Flatpaks') }}'
                }[selected] || '{{ __('All Repositories') }}'"></span>
                <svg class="h-4 w-4 opacity-50" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>
            </button>

            <div 
                x-show="open" 
                x-transition:enter="transition ease-out duration-100"
                x-transition:enter-start="opacity-0 scale-95"
                x-transition:enter-end="opacity-100 scale-100"
                x-transition:leave="transition ease-in duration-75"
                x-transition:leave-start="opacity-100 scale-100"
                x-transition:leave-end="opacity-0 scale-95"
                class="absolute right-0 top-11 z-50 min-w-[12rem] overflow-hidden rounded-md border bg-popover p-1 text-popover-foreground shadow-md no-drag"
            >
                <div class="px-2 py-1.5 text-[10px] font-black uppercase tracking-widest text-muted-foreground opacity-70">{{ __('All Repositories') }}</div>
                <button @click="selected = 'all'; $wire.set('filterRepo', 'all'); open = false" class="relative flex w-full cursor-default select-none items-center rounded-sm px-2 py-1.5 text-xs outline-none hover:bg-accent hover:text-accent-foreground transition-colors">{{ __('All Repositories') }}</button>
                <button @click="selected = 'official'; $wire.set('filterRepo', 'official'); open = false" class="relative flex w-full cursor-default select-none items-center rounded-sm px-2 py-1.5 text-xs outline-none hover:bg-accent hover:text-accent-foreground transition-colors">{{ __('Official') }}</button>
                @if($settings['enable_aur'])
                    <button @click="selected = 'aur'; $wire.set('filterRepo', 'aur'); open = false" class="relative flex w-full cursor-default select-none items-center rounded-sm px-2 py-1.5 text-xs outline-none hover:bg-accent hover:text-accent-foreground transition-colors">{{ __('AUR') }}</button>
                @endif
                @if($settings['enable_flatpak'])
                    <button @click="selected = 'flatpak'; $wire.set('filterRepo', 'flatpak'); open = false" class="relative flex w-full cursor-default select-none items-center rounded-sm px-2 py-1.5 text-xs outline-none hover:bg-accent hover:text-accent-foreground transition-colors">{{ __('Flatpaks') }}</button>
                @endif
            </div>
        </div>

        <button wire:click="refresh" wire:loading.attr="disabled" class="h-10 w-10 border border-input rounded-md flex items-center justify-center hover:bg-accent transition-all group">
            <svg wire:loading.class="animate-spin" wire:target="refresh" class="w-5 h-5 text-muted-foreground group-hover:text-foreground" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
        </button>
    </div>
</header>
