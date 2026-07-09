@props(['tab', 'sysInfo', 'pendingInstallations'])

<aside {{ $attributes->merge(['class' => 'w-72 bg-card/60 backdrop-blur-md flex flex-col shrink-0 relative z-50 shadow-2xl']) }}>
    <div class="p-8 flex-1">
        <button wire:click="setTab('explore')" class="w-full flex items-center gap-4 mb-10 px-2 hover:bg-accent/10 rounded-xl transition-all group active:scale-95 text-left">
            <img src="/logo-without-background.png" class="w-12 h-12 object-contain group-hover:-translate-y-1 group-hover:scale-110 transition-all duration-300 ease-out" alt="Logo">
            <div>
                <h1 class="text-xl font-black tracking-tighter leading-none">Bamboo End</h1>
                <span class="text-[9px] font-black uppercase text-bamboo/70 tracking-widest">{{ __('Unofficial fan project') }}</span>
            </div>
        </button>

        <nav class="space-y-1.5">
            <button 
                wire:click="setTab('explore')"
                class="w-full flex items-center gap-4 px-4 py-3 rounded-md text-[15px] transition-all {{ $tab === 'explore' ? 'bg-accent text-accent-foreground font-bold' : 'text-muted-foreground hover:bg-accent/40 hover:text-foreground' }}"
            >
                @if($tab === 'explore')
                    <svg class="w-5 h-5 flex-shrink-0" viewBox="0 0 16 16" fill="none">
                        <g stroke-linecap="round" stroke-linejoin="round">
                            <path stroke="currentColor" d="m1.875 8l.686-2.743a1 1 0 0 1 .97-.757h10.938a1 1 0 0 1 .97 1.243l-.315 1.26M6 13.5H2.004A1.5 1.5 0 0 1 .5 12V3.5a1 1 0 0 1 1-1h5a1 1 0 0 1 1 1v1"/>
                            <path stroke="#7dc4e4" d="M8.5 12h7M12 15.5c-1.933 0-3.5-1.5-3.5-3.5s1.567-3.5 3.5-3.5c2 0 3.5 1.5 3.5 3.5S14 15.5 12 15.5M11.556 9c-1.38 2.01-1.448 4.01.087 6.34M12.454 9c1.36 1.98 1.45 3.98-.062 6.34"/>
                        </g>
                    </svg>
                @else
                    <svg class="w-5 h-5 flex-shrink-0" viewBox="0 0 16 16" fill="none">
                        <g stroke-linecap="round" stroke-linejoin="round">
                            <path stroke="currentColor" d="M4.5 4.5H12A1.5 1.5 0 0 1 13.5 6v.5m-7.5 7H2A1.5 1.5 0 0 1 .5 12V3.5a1 1 0 0 1 1-1h5a1 1 0 0 1 1 1v1"/>
                            <path stroke="#7dc4e4" d="M8.5 12h7M12 15.5c-1.933 0-3.5-1.5-3.5-3.5s1.567-3.5 3.5-3.5c2 0 3.5 1.5 3.5 3.5S14 15.5 12 15.5M11.556 9c-1.379 2.01-1.448 4.01.087 6.34M12.454 9c1.361 1.98 1.45 3.98-.062 6.34"/>
                        </g>
                    </svg>
                @endif
                <span>{{ __('Explore') }}</span>
            </button>
            <button 
                wire:click="setTab('installed')"
                class="w-full flex items-center gap-4 px-4 py-3 rounded-md text-[15px] transition-all {{ $tab === 'installed' ? 'bg-accent text-accent-foreground font-bold' : 'text-muted-foreground hover:bg-accent/40 hover:text-foreground' }}"
            >
                @if($tab === 'installed')
                    <svg class="w-5 h-5 flex-shrink-0" viewBox="0 0 16 16" fill="none">
                        <g stroke-linecap="round" stroke-linejoin="round">
                            <path stroke="currentColor" d="m1.875 8l.686-2.743a1 1 0 0 1 .97-.757h10.938a1 1 0 0 1 .97 1.243l-.315 1.26M6 13.5H2.004A1.5 1.5 0 0 1 .5 12V3.5a1 1 0 0 1 1-1h5a1 1 0 0 1 1 1v1"/>
                            <path stroke="#f5a97f" d="M12 15.337v-3.919L8.5 9.214m3.5 2.204l3.5-2.204M12 7.5l3.5 1.714v4.408L12 15.5l-3.5-1.878V9.214Z"/>
                        </g>
                    </svg>
                @else
                    <svg class="w-5 h-5 flex-shrink-0" viewBox="0 0 16 16" fill="none">
                        <g stroke-linecap="round" stroke-linejoin="round">
                            <path stroke="currentColor" d="M4.5 4.5H12A1.5 1.5 0 0 1 13.5 6v.5m-7.5 7H2A1.5 1.5 0 0 1 .5 12V3.5a1 1 0 0 1 1-1h5a1 1 0 0 1 1 1v1"/>
                            <path stroke="#f5a97f" d="M12 15.337v-3.919L8.5 9.214m3.5 2.204l3.5-2.204M12 7.5l3.5 1.714v4.408L12 15.5l-3.5-1.878V9.214Z"/>
                        </g>
                    </svg>
                @endif
                <span>{{ __('Installed') }}</span>
            </button>
            <button 
                wire:click="setTab('appimages')"
                class="w-full flex items-center gap-4 px-4 py-3 rounded-md text-[15px] transition-all {{ $tab === 'appimages' ? 'bg-accent text-accent-foreground font-bold' : 'text-muted-foreground hover:bg-accent/40 hover:text-foreground' }}"
            >
                @if($tab === 'appimages')
                    <svg class="w-5 h-5 flex-shrink-0" viewBox="0 0 16 16" fill="none">
                        <g stroke-linecap="round" stroke-linejoin="round">
                            <path stroke="currentColor" d="m1.9 8l.7-2.7a1 1 0 0 1 1-.8h10.9a1 1 0 0 1 1 1.2L15 7m-9 6.5H2c-.8 0-1.5-.7-1.5-1.5V3.5c0-.6.4-1 1-1h5c.6 0 1 .4 1 1v1"/>
                            <path stroke="#8087a2" d="M8.833 8.616a1.333 1.333 0 1 0 0 2.667a1.333 1.333 0 0 0 0-2.667m5.334 0a1.333 1.333 0 1 0 0 2.667a1.333 1.333 0 0 0 0-2.667"/>
                            <path stroke="#eed49f" d="M9.158 13.716L11.55 15.5l2.393-1.784l-2.393-1.783z"/>
                        </g>
                    </svg>
                @else
                    <svg class="w-5 h-5 flex-shrink-0" viewBox="0 0 16 16" fill="none">
                        <g stroke-linecap="round" stroke-linejoin="round">
                            <path stroke="currentColor" d="M4.5 4.5H12c.8 0 1.5.7 1.5 1.5v.5m-7.5 7H2c-.8 0-1.5-.7-1.5-1.5V3.5c0-.6.4-1 1-1h5c.6 0 1 .4 1 1v1"/>
                            <path stroke="#8087a2" d="M8.833 8.616a1.333 1.333 0 1 0 0 2.667a1.333 1.333 0 0 0 0-2.667m5.334 0a1.333 1.333 0 1 0 0 2.667a1.333 1.333 0 0 0 0-2.667"/>
                            <path stroke="#eed49f" d="M9.158 13.716L11.55 15.5l2.393-1.784l-2.393-1.783z"/>
                        </g>
                    </svg>
                @endif
                <span>{{ __('AppImages') }}</span>
            </button>
            <button 
                wire:click="runSystemUpdate"
                class="w-full flex items-center gap-4 px-4 py-3 rounded-md text-[15px] transition-all text-muted-foreground hover:bg-bamboo/10 hover:text-bamboo"
            >
                <svg class="w-5 h-5 flex-shrink-0" viewBox="0 0 16 16" fill="none">
                    <g stroke-linecap="round" stroke-linejoin="round">
                        <path stroke="currentColor" d="M4.5 4.5H12c.83 0 1.5.67 1.5 1.5v.5m-7.5 7H2A1.5 1.5 0 0 1 .5 12V3.5a1 1 0 0 1 1-1h5a1 1 0 0 1 1 1v1"/>
                        <path stroke="#8087a2" d="M14.25 13.5a.75.75 0 0 1 .75.75v.5a.75.75 0 0 1-.75.75h-.5a.75.75 0 0 1-.75-.75v-5.5a.75.75 0 0 1 .75-.75h.5a.75.75 0 0 1 .75.75v.5a.75.75 0 0 1-.75.75h-5.5A.75.75 0 0 1 8 9.75v-.5a.75.75 0 0 1 .75-.75h.5a.75.75 0 0 1 .75.75v5.5a.75.75 0 0 1-.75.75h-.5a.75.75 0 0 1-.75-.75v-.5a.75.75 0 0 1 .75-.75h5z"/>
                    </g>
                </svg>
                <span>{{ __('Update System') }}</span>
            </button>

            <button 
                wire:click="setTab('settings')"
                class="w-full flex items-center gap-4 px-4 py-3 rounded-md text-[15px] transition-all {{ $tab === 'settings' ? 'bg-accent text-accent-foreground font-bold' : 'text-muted-foreground hover:bg-accent/40 hover:text-foreground' }}"
            >
                @if($tab === 'settings')
                    <svg class="w-5 h-5 flex-shrink-0" viewBox="0 0 16 16" fill="none">
                        <g stroke-linecap="round" stroke-linejoin="round">
                            <path stroke="currentColor" d="m1.87 8l.7-2.74a1 1 0 0 1 .96-.76h10.94a1 1 0 0 1 .97 1.24l-.219.875M6 13.5H2A1.5 1.5 0 0 1 .5 12V3.5a1 1 0 0 1 1-1h5a1 1 0 0 1 1 1v1"/>
                            <path stroke="#8087a2" d="M11.498 13a1 1 0 1 0 0-2a1 1 0 0 0 0 2m1.752-4L15 12l-1.75 3h-3.5L8 12l1.75-3z"/>
                        </g>
                    </svg>
                @else
                    <svg class="w-5 h-5 flex-shrink-0" viewBox="0 0 16 16" fill="none">
                        <g stroke-linecap="round" stroke-linejoin="round">
                            <path stroke="currentColor" d="M4.5 4.5H12c.83 0 1.5.67 1.5 1.5v.5m-7.5 7H2A1.5 1.5 0 0 1 .5 12V3.5a1 1 0 0 1 1-1h5a1 1 0 0 1 1 1v1"/>
                            <path stroke="#8087a2" d="M11.498 13a1 1 0 1 0 0-2a1 1 0 0 0 0 2m1.752-4L15 12l-1.75 3h-3.5L8 12l1.75-3z"/>
                        </g>
                    </svg>
                @endif
                <span>{{ __('Settings') }}</span>
            </button>
        </nav>
    </div>

    <!-- Sidebar Footer -->
    <div class="p-8 bg-muted/10 space-y-5 relative">
        <div class="flex items-center gap-4">
            <div class="w-9 h-9 rounded flex items-center justify-center bg-background shadow-sm">
                <svg class="w-4 h-4 text-bamboo" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z"></path></svg>
            </div>
            <div>
                <p class="text-[10px] font-black uppercase text-muted-foreground tracking-widest leading-none mb-1">Host</p>
                <p class="text-[13px] font-bold">{{ $sysInfo['hostname'] }}</p>
            </div>
        </div>
        <div class="flex items-center gap-4">
            <div class="w-9 h-9 rounded flex items-center justify-center bg-background shadow-sm">
                <svg class="w-4 h-4 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"></path></svg>
            </div>
            <div>
                <p class="text-[10px] font-black uppercase text-muted-foreground tracking-widest leading-none mb-1">Kernel</p>
                <p class="text-[13px] font-bold">{{ $sysInfo['kernel'] }}</p>
            </div>
        </div>
        
        <div class="pt-4">
            <button wire:click="setTab('settings')" class="text-[10px] font-black uppercase text-muted-foreground hover:text-bamboo transition-colors tracking-widest leading-none">
                v{{ config('nativephp.version') }} | Made by Panda 🐼
            </button>
        </div>
    </div>
</aside>
