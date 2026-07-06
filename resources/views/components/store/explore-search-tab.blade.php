@props(['packages', 'pendingInstallations', 'search', 'totalResults', 'page', 'settings', 'userState' => 'idle', 'activePackage' => null])

<div class="mb-10 flex items-end justify-between">
    <div>
        <h2 class="text-3xl font-black tracking-tight mb-2">{{ __('Search Results') }}</h2>
        <p class="text-xs text-muted-foreground font-bold uppercase tracking-widest">
            {{ __('Found') }} {{ $totalResults }} {{ __('packages') }}
        </p>
    </div>
</div>

{{-- Skeleton: hidden by default, shown while Livewire is loading --}}
<div class="hidden"
    wire:loading.class.remove="hidden"
    wire:target="updatedSearch, clearSearch"
>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
        @for($s = 0; $s < 8; $s++)
            <div class="bg-card rounded-lg overflow-hidden animate-pulse">
                <div class="h-36 bg-muted/60"></div>
                <div class="p-5 space-y-3">
                    <div class="h-4 bg-muted/60 rounded w-3/4"></div>
                    <div class="h-3 bg-muted/40 rounded w-full"></div>
                    <div class="h-3 bg-muted/40 rounded w-2/3"></div>
                    <div class="h-10 bg-muted/30 rounded mt-4"></div>
                </div>
            </div>
        @endfor
    </div>
</div>

{{-- Real results: shown by default, hidden while loading --}}
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6"
    wire:loading.class="hidden"
    wire:target="updatedSearch, clearSearch"
>
    @forelse($packages as $pkg)
        <x-store.package-card :$pkg :$pendingInstallations :$userState :$activePackage wire:key="search-{{ $pkg['name'] }}-{{ $loop->index }}" />
    @empty
        <div class="col-span-full py-32 text-center">
            <div class="w-20 h-20 rounded-full bg-muted/30 flex items-center justify-center mx-auto mb-6">
                <svg class="w-10 h-10 text-muted-foreground/50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
            </div>
            <h3 class="text-xl font-bold text-muted-foreground">{{ __('No packages found.') }}</h3>
            <p class="text-sm text-slate-500 mt-2">{{ __('Try a different search term or check your filters.') }}</p>
        </div>
    @endforelse
</div>

{{-- Pagination --}}
@if($totalResults > $settings['search_limit'])
    <div class="mt-16 flex items-center justify-between border-t border-border pt-8 pb-20">
        <div class="text-sm text-muted-foreground font-bold">
            {{ __('Showing') }} <span class="text-foreground">{{ min(($page - 1) * $settings['search_limit'] + 1, $totalResults) }}</span>
            {{ __('to') }} <span class="text-foreground">{{ min($page * $settings['search_limit'], $totalResults) }}</span>
            {{ __('of') }} <span class="text-foreground">{{ $totalResults }}</span> {{ __('results') }}
        </div>
        <div class="flex items-center gap-2">
            <button wire:click="prevPage" @if($page === 1) disabled @endif class="h-10 px-4 border border-input rounded-md text-xs font-black uppercase tracking-widest transition-all hover:bg-accent disabled:opacity-30 disabled:hover:bg-transparent">{{ __('Previous') }}</button>
            <div class="h-10 px-4 bg-accent/50 rounded-md flex items-center justify-center text-xs font-black">{{ $page }} / {{ ceil($totalResults / $settings['search_limit']) }}</div>
            <button wire:click="nextPage" @if($page * $settings['search_limit'] >= $totalResults) disabled @endif class="h-10 px-4 border border-input rounded-md text-xs font-black uppercase tracking-widest transition-all hover:bg-accent disabled:opacity-30 disabled:hover:bg-transparent">{{ __('Next') }}</button>
        </div>
    </div>
@endif
