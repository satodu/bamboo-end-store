@props(['appImages', 'appImageViewMode' => 'grid'])

<div {{ $attributes->merge(['class' => 'flex-1 overflow-y-auto p-16']) }}>
    <div class="max-w-7xl mx-auto">
        <div class="flex items-center justify-between mb-12">
            <div>
                <h2 class="text-4xl font-black tracking-tight mb-3">AppImages</h2>
                <p class="text-[17px] text-muted-foreground font-medium">{{ __('Manage and integrate standalone AppImage applications.') }}</p>
            </div>
            
            <div class="flex items-center gap-4">
                {{-- Alternador de Layout (Grade / Lista) --}}
                <div class="flex items-center border border-input rounded-md overflow-hidden p-0.5 bg-background select-none">
                    <button 
                        wire:click="$set('appImageViewMode', 'grid')" 
                        class="h-9 w-9 flex items-center justify-center rounded-sm transition-all {{ $appImageViewMode === 'grid' ? 'bg-accent text-foreground shadow-sm' : 'text-muted-foreground hover:bg-accent/50 hover:text-foreground' }}"
                        title="{{ __('Grid View') }}"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <rect x="3" y="3" width="7" height="7" rx="1"></rect>
                            <rect x="14" y="3" width="7" height="7" rx="1"></rect>
                            <rect x="14" y="14" width="7" height="7" rx="1"></rect>
                            <rect x="3" y="14" width="7" height="7" rx="1"></rect>
                        </svg>
                    </button>
                    <button 
                        wire:click="$set('appImageViewMode', 'list')" 
                        class="h-9 w-9 flex items-center justify-center rounded-sm transition-all {{ $appImageViewMode === 'list' ? 'bg-accent text-foreground shadow-sm' : 'text-muted-foreground hover:bg-accent/50 hover:text-foreground' }}"
                        title="{{ __('List View') }}"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <line x1="8" y1="6" x2="21" y2="6"></line>
                            <line x1="8" y1="12" x2="21" y2="12"></line>
                            <line x1="8" y1="18" x2="21" y2="18"></line>
                            <rect x="3" y="5" width="2" height="2" rx="0.5"></rect>
                            <rect x="3" y="11" width="2" height="2" rx="0.5"></rect>
                            <rect x="3" y="17" width="2" height="2" rx="0.5"></rect>
                        </svg>
                    </button>
                </div>

                <button 
                    wire:click="selectAppImage"
                    class="h-14 px-8 bg-bamboo text-white text-sm font-black rounded-xl hover:bg-bamboo/90 transition-all uppercase tracking-[0.2em] shadow-xl flex items-center gap-3"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                    <span>{{ __('Add AppImage') }}</span>
                </button>
            </div>
        </div>

        @if($appImageViewMode === 'grid')
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                @forelse($appImages as $app)
                    <div class="bg-card rounded-lg p-6 hover:shadow-lg transition-all flex flex-col h-full group relative">
                        <div class="flex items-start justify-between mb-5">
                            <div class="w-14 h-14 bg-muted rounded-lg flex items-center justify-center shadow-inner group-hover:scale-105 transition-transform duration-500 overflow-hidden">
                                @if($app['icon_url'])
                                    <img src="{{ $app['icon_url'] }}" class="w-full h-full object-cover">
                                @else
                                    <span class="text-3xl">📦</span>
                                @endif
                            </div>
                            <div class="flex flex-col items-end gap-1.5">
                                @if($app['has_desktop']) 
                                    <span class="text-[9px] font-black bg-bamboo/10 text-bamboo px-2.5 py-0.5 rounded uppercase">{{ __('Integrated') }}</span> 
                                @endif
                                <span class="text-[9px] font-black bg-purple-400/10 text-purple-400 px-2 py-0.5 rounded uppercase tracking-widest">AppImage</span>
                            </div>
                        </div>

                        <h3 class="text-[17px] font-black mb-1 truncate tracking-tight">{{ str_replace(['.AppImage', '.appimage'], '', $app['name']) }}</h3>
                        <p class="text-[13px] text-muted-foreground mb-6 line-clamp-2 leading-relaxed h-10">{{ __('Standalone application.') }} {{ $app['size'] }}</p>
                        
                        <div class="mt-auto flex items-center gap-3 pt-6">
                            <button 
                                wire:click="launchAppImage('{{ addslashes($app['path']) }}')"
                                class="flex-1 h-10 bg-accent hover:bg-accent/80 text-accent-foreground text-[11px] font-black rounded transition-all uppercase tracking-widest"
                            >
                                {{ __('Launch') }}
                            </button>
                            
                            @if(!$app['has_desktop'])
                                <button 
                                    wire:click="registerAppImage('{{ addslashes($app['path']) }}')"
                                    class="flex-1 h-10 bg-bamboo hover:bg-bamboo/90 text-white text-[11px] font-black rounded transition-all uppercase tracking-widest shadow-md"
                                >
                                    {{ __('Integrate') }}
                                </button>
                            @endif

                            <button 
                                wire:click="removeAppImage('{{ addslashes($app['path']) }}')"
                                class="h-10 w-10 bg-destructive/10 rounded flex items-center justify-center text-destructive hover:bg-destructive hover:text-white transition-colors"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                            </button>
                        </div>
                    </div>
                @empty
                    <div class="col-span-full py-24 flex flex-col items-center justify-center text-center bg-muted/10 rounded-3xl shadow-inner">
                        <div class="w-20 h-20 rounded-full bg-muted flex items-center justify-center mb-6 text-3xl grayscale opacity-50">
                            📦
                        </div>
                        <h3 class="text-2xl font-black tracking-tight mb-2">{{ __('No AppImages Yet') }}</h3>
                        <p class="text-muted-foreground max-w-sm mx-auto font-medium mb-8">
                            {{ __('Add your first AppImage to have it automatically integrated into your system menu and managed from here.') }}
                        </p>
                        <button 
                            wire:click="selectAppImage"
                            class="h-12 px-10 bg-accent text-accent-foreground text-xs font-black rounded-xl hover:bg-primary hover:text-primary-foreground transition-all uppercase tracking-widest"
                        >
                            {{ __('Register AppImage') }}
                        </button>
                    </div>
                @endforelse
            </div>
        @else
            <div class="flex flex-col gap-4">
                @forelse($appImages as $app)
                    <div class="bg-card rounded-lg hover:shadow-lg transition-all flex items-center p-4 gap-5 group relative overflow-hidden w-full">
                        
                        {{-- Thumbnail / Icon no lado esquerdo --}}
                        <div class="w-24 h-24 bg-muted/60 rounded-lg flex items-center justify-center text-4xl shrink-0 group-hover:scale-102 transition-all duration-300 relative">
                            @if($app['icon_url'])
                                <img src="{{ $app['icon_url'] }}" class="w-14 h-14 object-contain" alt="icon">
                            @else
                                <span class="group-hover:scale-110 transition-transform">📦</span>
                            @endif
                        </div>

                        {{-- Detalhes (Nome, badges e descrição) no centro --}}
                        <div class="flex-1 min-w-0 flex flex-col justify-center">
                            <div class="flex items-center gap-2 mb-1.5 flex-wrap">
                                <h3 class="text-lg font-black tracking-tight truncate max-w-[200px] sm:max-w-[350px] md:max-w-[450px]">
                                    {{ str_replace(['.AppImage', '.appimage'], '', $app['name']) }}
                                </h3>
                                
                                {{-- Badges alinhadas com o título --}}
                                <div class="flex items-center gap-1.5">
                                    @if($app['has_desktop']) 
                                        <span class="text-[9px] font-black bg-bamboo/10 text-bamboo px-2.5 py-0.5 rounded uppercase">{{ __('Integrated') }}</span> 
                                    @endif
                                    <span class="text-[9px] font-black bg-purple-400/10 text-purple-400 px-2 py-0.5 rounded uppercase tracking-widest">AppImage</span>
                                </div>
                            </div>
                            <p class="text-[13px] text-muted-foreground line-clamp-2 leading-relaxed max-w-2xl">
                                {{ __('Standalone application.') }} {{ $app['size'] }}
                            </p>
                        </div>

                        {{-- Botões de Ação no lado direito --}}
                        <div class="flex items-center gap-3 shrink-0 ml-auto pl-4">
                            <button 
                                wire:click="launchAppImage('{{ addslashes($app['path']) }}')"
                                class="h-10 px-5 bg-accent hover:bg-accent/80 text-accent-foreground text-[11px] font-black rounded transition-all uppercase tracking-widest min-w-[100px]"
                            >
                                {{ __('Launch') }}
                            </button>
                            
                            @if(!$app['has_desktop'])
                                <button 
                                    wire:click="registerAppImage('{{ addslashes($app['path']) }}')"
                                    class="h-10 px-5 bg-bamboo hover:bg-bamboo/90 text-white text-[11px] font-black rounded transition-all uppercase tracking-widest shadow-md min-w-[100px]"
                                >
                                    {{ __('Integrate') }}
                                </button>
                            @endif

                            <button 
                                wire:click="removeAppImage('{{ addslashes($app['path']) }}')"
                                class="h-10 w-10 bg-destructive/10 rounded flex items-center justify-center text-destructive hover:bg-destructive hover:text-white transition-colors"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                            </button>
                        </div>
                    </div>
                @empty
                    <div class="col-span-full py-24 flex flex-col items-center justify-center text-center bg-muted/10 rounded-3xl shadow-inner">
                        <div class="w-20 h-20 rounded-full bg-muted flex items-center justify-center mb-6 text-3xl grayscale opacity-50">
                            📦
                        </div>
                        <h3 class="text-2xl font-black tracking-tight mb-2">{{ __('No AppImages Yet') }}</h3>
                        <p class="text-muted-foreground max-w-sm mx-auto font-medium mb-8">
                            {{ __('Add your first AppImage to have it automatically integrated into your system menu and managed from here.') }}
                        </p>
                        <button 
                            wire:click="selectAppImage"
                            class="h-12 px-10 bg-accent text-accent-foreground text-xs font-black rounded-xl hover:bg-primary hover:text-primary-foreground transition-all uppercase tracking-widest"
                        >
                            {{ __('Register AppImage') }}
                        </button>
                    </div>
                @endforelse
            </div>
        @endif
    </div>
</div>
