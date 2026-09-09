@props(['curatedCategories', 'selectedCategory', 'packages', 'pendingInstallations', 'userState' => 'idle', 'activePackage' => null, 'viewMode' => 'grid'])

<div>
    {{-- Local Loading State specifically for category selection / navigation --}}
    <div wire:loading.flex wire:target="selectCategory" class="min-h-[400px] flex items-center justify-center flex-col gap-6 w-full select-none py-28">
        <div class="pacman-container scale-125">
            <div class="pacman"></div>
            <div class="dot"></div>
            <div class="dot"></div>
            <div class="dot"></div>
        </div>
        <p class="text-xs font-black tracking-[0.4em] uppercase text-bamboo animate-pulse">
            {{ __('Loading Packages...') }}
        </p>
    </div>

    {{-- Content Area (hidden during selectCategory loading) --}}
    <div wire:loading.remove wire:target="selectCategory">
        @if(!$selectedCategory)
            <div class="mb-10">
                <h2 class="text-3xl font-black tracking-tight mb-2">{{ __('Explore Categories') }}</h2>
                <p class="text-xs text-muted-foreground font-bold uppercase tracking-widest">{{ __('Curated software by EndeavourOS') }}</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                @forelse($curatedCategories as $cat)
                    @php
                        $catSlug = strtolower(str_replace([' ', '&'], ['_', 'and'], $cat['name']));
                        $pngPath = "images/categories/{$catSlug}.png";
                        $jpgPath = "images/categories/{$catSlug}.jpg";
                        $imagePath = file_exists(public_path($pngPath)) ? $pngPath : (file_exists(public_path($jpgPath)) ? $jpgPath : null);
                    @endphp
                    <button wire:click="selectCategory('{{ $cat['name'] }}')" 
                            wire:loading.attr="disabled" 
                            class="relative overflow-hidden bg-card rounded-2xl p-6 min-h-[140px] text-left border border-transparent hover:border-purple-500/50 transition-all duration-300 group hover:shadow-xl hover:shadow-purple-500/10 hover:-translate-y-1 disabled:opacity-60 disabled:pointer-events-none flex flex-col justify-between">
                        
                        {{-- Ambient Glow behind the graphic (apenas no hover) --}}
                        <div class="absolute -right-6 -bottom-6 w-36 h-36 bg-purple-500/20 rounded-full blur-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-500 pointer-events-none"></div>

                        {{-- Watermark Graphic on the Right --}}
                        @if($imagePath)
                            <div class="absolute -right-2 -bottom-2 w-32 h-32 pointer-events-none select-none opacity-60 group-hover:opacity-100 transition-all duration-500 transform group-hover:scale-110 group-hover:rotate-2">
                                <img src="{{ asset($imagePath) }}" alt="{{ $cat['name'] }}" class="w-full h-full object-contain filter group-hover:drop-shadow-[0_0_15px_rgba(168,85,247,0.4)] transition-all">
                            </div>
                        @else
                            <div class="absolute -right-4 -bottom-4 w-28 h-28 opacity-10 group-hover:opacity-25 transition-opacity duration-500 transform group-hover:scale-110 pointer-events-none select-none">
                                <svg class="w-full h-full text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 002-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                                </svg>
                            </div>
                        @endif

                        {{-- Left Content --}}
                        <div class="relative z-10 pr-24">
                            <h3 class="text-[17px] font-black tracking-tight text-foreground group-hover:text-purple-300 transition-colors mb-2">
                                {{ __($cat['name']) }}
                            </h3>
                            <div class="w-6 h-1 rounded-full bg-border group-hover:w-10 group-hover:bg-purple-400 transition-all mb-4"></div>
                        </div>

                        <div class="relative z-10 mt-auto">
                            <span class="inline-flex items-center text-[11px] font-bold uppercase tracking-widest text-muted-foreground group-hover:text-purple-300 transition-colors">
                                {{ count($cat['packages']) }} {{ __('Packages') }}
                            </span>
                        </div>
                    </button>
                @empty
                    <div class="col-span-full py-20 text-center">
                        <p class="text-muted-foreground font-bold">{{ __('Loading curated categories...') }}</p>
                    </div>
                @endforelse
            </div>
        @else
            <div class="mb-10 flex items-center gap-4">
                <button wire:click="selectCategory(null)" wire:loading.attr="disabled" class="w-10 h-10 rounded-full bg-accent flex items-center justify-center hover:bg-bamboo hover:text-white transition-colors shrink-0 disabled:opacity-60 disabled:pointer-events-none">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
                </button>
                @php
                    $selectedSlug = strtolower(str_replace([' ', '&'], ['_', 'and'], $selectedCategory ?? ''));
                    $selectedPng = "images/categories/{$selectedSlug}.png";
                    $selectedJpg = "images/categories/{$selectedSlug}.jpg";
                    $selectedImgPath = !empty($selectedSlug) && file_exists(public_path($selectedPng)) ? $selectedPng : (!empty($selectedSlug) && file_exists(public_path($selectedJpg)) ? $selectedJpg : null);
                @endphp
                @if($selectedImgPath)
                    <div class="w-12 h-12 rounded-2xl overflow-hidden bg-purple-950/30 border border-purple-500/30 shadow-md shadow-purple-500/10 shrink-0 p-1">
                        <img src="{{ asset($selectedImgPath) }}" alt="{{ $selectedCategory }}" class="w-full h-full object-contain">
                    </div>
                @endif
                <div>
                    <h2 class="text-3xl font-black tracking-tight mb-1">{{ __($selectedCategory) }}</h2>
                    <p class="text-xs text-muted-foreground font-bold uppercase tracking-widest">{{ __('Curated software by EndeavourOS') }}</p>
                </div>
            </div>

            @if(empty($packages))
                <div class="py-20 text-center flex flex-col items-center justify-center gap-6">
                    <p class="text-xs font-black tracking-[0.2em] uppercase text-muted-foreground">
                        {{ __('No packages found in this category.') }}
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
        @endif
    </div>
</div>
