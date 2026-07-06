@props(['appImages'])

<div {{ $attributes->merge(['class' => 'flex-1 overflow-y-auto p-16']) }}>
    <div class="max-w-7xl mx-auto">
        <div class="flex items-center justify-between mb-12">
            <div>
                <h2 class="text-4xl font-black tracking-tight mb-3">AppImages</h2>
                <p class="text-[17px] text-muted-foreground font-medium">Manage and integrate standalone AppImage applications.</p>
            </div>
            <button 
                wire:click="selectAppImage"
                class="h-14 px-8 bg-bamboo text-white text-sm font-black rounded-xl hover:bg-bamboo/90 transition-all uppercase tracking-[0.2em] shadow-xl flex items-center gap-3"
            >
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                <span>Add AppImage</span>
            </button>
        </div>

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
                                <span class="text-[9px] font-black bg-bamboo/10 text-bamboo px-2.5 py-0.5 rounded uppercase">Integrated</span> 
                            @endif
                            <span class="text-[9px] font-black bg-purple-400/10 text-purple-400 px-2 py-0.5 rounded uppercase tracking-widest">AppImage</span>
                        </div>
                    </div>

                    <h3 class="text-[17px] font-black mb-1 truncate tracking-tight">{{ str_replace(['.AppImage', '.appimage'], '', $app['name']) }}</h3>
                    <p class="text-[13px] text-muted-foreground mb-6 line-clamp-2 leading-relaxed h-10">Standalone application. {{ $app['size'] }}</p>
                    
                    <div class="mt-auto flex items-center gap-3 pt-6">
                        <button 
                            wire:click="launchAppImage('{{ addslashes($app['path']) }}')"
                            class="flex-1 h-10 bg-accent hover:bg-accent/80 text-accent-foreground text-[11px] font-black rounded transition-all uppercase tracking-widest"
                        >
                            Launch
                        </button>
                        
                        @if(!$app['has_desktop'])
                            <button 
                                wire:click="registerAppImage('{{ addslashes($app['path']) }}')"
                                class="flex-1 h-10 bg-bamboo hover:bg-bamboo/90 text-white text-[11px] font-black rounded transition-all uppercase tracking-widest shadow-md"
                            >
                                Integrate
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
                    <h3 class="text-2xl font-black tracking-tight mb-2">No AppImages Yet</h3>
                    <p class="text-muted-foreground max-w-sm mx-auto font-medium mb-8">
                        Add your first AppImage to have it automatically integrated into your system menu and managed from here.
                    </p>
                    <button 
                        wire:click="selectAppImage"
                        class="h-12 px-10 bg-accent text-accent-foreground text-xs font-black rounded-xl hover:bg-primary hover:text-primary-foreground transition-all uppercase tracking-widest"
                    >
                        Register AppImage
                    </button>
                </div>
            @endforelse
        </div>
    </div>
</div>
