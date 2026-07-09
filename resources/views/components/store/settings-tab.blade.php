@props(['settings'])

<div {{ $attributes->merge(['class' => 'flex-1 overflow-y-auto p-16']) }}>
    <div class="max-w-4xl mx-auto space-y-24">
        <!-- App Settings -->
        <div class="space-y-12">
            <div>
                <h2 class="text-4xl font-black tracking-tight mb-3">{{ __('Settings') }}</h2>
                <p class="text-[17px] text-muted-foreground font-medium">{{ __('Manage your repositories and preferences.') }}</p>
            </div>

            <div class="space-y-8">
                <!-- AUR -->
                <div class="bg-card rounded-xl p-10 space-y-8 shadow-md">
                    <div class="flex items-center justify-between">
                        <div class="space-y-1">
                            <h4 class="text-[17px] font-black tracking-tight">{{ __('Arch User Repository') }}</h4>
                            <p class="text-sm text-muted-foreground leading-relaxed">{{ __('Search and install thousands of community-maintained packages.') }}</p>
                        </div>
                        <button 
                            wire:click="$set('settings.enable_aur', {{ !$settings['enable_aur'] ? 'true' : 'false' }})"
                            class="w-12 h-6 rounded-full transition-all relative {{ $settings['enable_aur'] ? 'bg-bamboo shadow-lg shadow-bamboo/20' : 'bg-muted' }}"
                        >
                            <div class="absolute top-1 left-1 bg-white w-4 h-4 rounded-full transition-transform {{ $settings['enable_aur'] ? 'translate-x-6' : '' }}"></div>
                        </button>
                    </div>
                    <div class="pt-8 space-y-6 border-t border-border">
                        <div class="flex items-start justify-between">
                            <div class="space-y-1">
                                <h4 class="text-[17px] font-black tracking-tight">{{ __('Flatpak Integration') }}</h4>
                                <p class="text-sm text-muted-foreground leading-relaxed">{{ __('Access Flathub\'s universal application ecosystem.') }}</p>
                            </div>
                            <button 
                                wire:click="$set('settings.enable_flatpak', {{ !$settings['enable_flatpak'] ? 'true' : 'false' }})"
                                class="w-12 h-6 rounded-full transition-all relative {{ $settings['enable_flatpak'] ? 'bg-bamboo shadow-lg shadow-bamboo/20' : 'bg-muted' }} shrink-0 mt-1"
                            >
                                <div class="absolute top-1 left-1 bg-white w-4 h-4 rounded-full transition-transform {{ $settings['enable_flatpak'] ? 'translate-x-6' : '' }}"></div>
                            </button>
                        </div>

                        @php
                            $flatpakInstalled = \Illuminate\Support\Facades\Process::run('which flatpak')->successful();
                            $flathubConfigured = $flatpakInstalled && \Illuminate\Support\Facades\Process::run('flatpak remotes | grep -q flathub')->successful();
                        @endphp

                        {{-- Status checklist --}}
                        <div class="bg-muted/30 rounded-xl p-6 space-y-4">
                            <p class="text-[11px] font-black uppercase tracking-widest text-muted-foreground mb-4">{{ __('System Status') }}</p>

                            {{-- Flatpak binary --}}
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-3">
                                    @if($flatpakInstalled)
                                        <div class="w-6 h-6 rounded-full bg-green-500/15 flex items-center justify-center">
                                            <svg class="w-3.5 h-3.5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                                        </div>
                                        <span class="text-sm font-semibold">{{ __('Flatpak instalado') }}</span>
                                    @else
                                        <div class="w-6 h-6 rounded-full bg-destructive/15 flex items-center justify-center">
                                            <svg class="w-3.5 h-3.5 text-destructive" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M6 18L18 6M6 6l12 12"/></svg>
                                        </div>
                                        <span class="text-sm font-semibold text-muted-foreground">{{ __('Flatpak não instalado') }}</span>
                                    @endif
                                </div>
                                @if(!$flatpakInstalled)
                                    <button wire:click="installFlatpak" class="h-8 px-4 bg-bamboo text-white text-[11px] font-black rounded-lg hover:bg-bamboo/90 transition-all uppercase tracking-widest shadow-md">
                                        {{ __('Instalar') }}
                                    </button>
                                @else
                                    <span class="text-[10px] text-green-500 font-black uppercase tracking-widest">OK</span>
                                @endif
                            </div>

                            {{-- Flathub remote --}}
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-3">
                                    @if($flathubConfigured)
                                        <div class="w-6 h-6 rounded-full bg-green-500/15 flex items-center justify-center">
                                            <svg class="w-3.5 h-3.5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                                        </div>
                                        <span class="text-sm font-semibold">{{ __('Flathub configurado') }}</span>
                                    @else
                                        <div class="w-6 h-6 rounded-full bg-yellow-500/15 flex items-center justify-center">
                                            <svg class="w-3.5 h-3.5 text-yellow-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
                                        </div>
                                        <span class="text-sm font-semibold text-muted-foreground">{{ __('Flathub remote não configurado') }}</span>
                                    @endif
                                </div>
                                @if(!$flathubConfigured)
                                    <button 
                                        wire:click="addFlathubRemote"
                                        @if(!$flatpakInstalled) disabled @endif
                                        class="h-8 px-4 bg-orange-500 text-white text-[11px] font-black rounded-lg hover:bg-orange-500/90 transition-all uppercase tracking-widest shadow-md disabled:opacity-40 disabled:cursor-not-allowed">
                                        {{ __('Adicionar') }}
                                    </button>
                                @else
                                    <span class="text-[10px] text-green-500 font-black uppercase tracking-widest">OK</span>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Limit -->
                <div class="bg-card rounded-xl p-10 flex items-center justify-between shadow-md">
                    <div class="space-y-1">
                        <h4 class="text-[17px] font-black tracking-tight">{{ __('Search Results Limit') }}</h4>
                        <p class="text-sm text-muted-foreground leading-relaxed">{{ __('Limit the number of packages displayed for better performance.') }}</p>
                    </div>
                    <!-- Limit Select -->
                    <div x-data="{ open: false, selected: @entangle('settings.search_limit') }" class="relative">
                        <button 
                            @click="open = !open" 
                            @click.away="open = false"
                            class="h-10 w-40 flex items-center justify-between rounded-md border border-input bg-background px-4 py-2 text-sm font-medium hover:bg-accent/50 transition-colors"
                        >
                            <span x-text="selected + ' {{ __('Results') }}'"></span>
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
                            class="absolute right-0 top-12 z-50 min-w-[10rem] overflow-hidden rounded-md border bg-popover p-1 text-popover-foreground shadow-md no-drag"
                        >
                            <div class="px-2 py-1.5 text-[10px] font-black uppercase tracking-widest text-muted-foreground opacity-70">{{ __('Limit') }}</div>
                            <button @click="selected = 25; open = false" class="relative flex w-full cursor-default select-none items-center rounded-sm px-2 py-1.5 text-xs outline-none hover:bg-accent hover:text-accent-foreground transition-colors">25 {{ __('Results') }}</button>
                            <button @click="selected = 50; open = false" class="relative flex w-full cursor-default select-none items-center rounded-sm px-2 py-1.5 text-xs outline-none hover:bg-accent hover:text-accent-foreground transition-colors">50 {{ __('Results') }}</button>
                            <button @click="selected = 100; open = false" class="relative flex w-full cursor-default select-none items-center rounded-sm px-2 py-1.5 text-xs outline-none hover:bg-accent hover:text-accent-foreground transition-colors">100 {{ __('Results') }}</button>
                        </div>
                    </div>
                </div>

                <!-- Appearance (Language + Color Palette) -->
                <div class="bg-card rounded-xl p-10 space-y-8 shadow-md">
                    <div class="border-b border-border pb-4">
                        <h3 class="text-lg font-black tracking-tight">{{ __('Appearance') }}</h3>
                        <p class="text-xs text-muted-foreground leading-relaxed mt-1">{{ __('Customize application language, theme colors, and layout.') }}</p>
                    </div>

                    <!-- Language Selector -->
                    <div class="flex items-center justify-between">
                        <div class="space-y-1">
                            <h4 class="text-[16px] font-black tracking-tight">{{ __('Language') }}</h4>
                            <p class="text-sm text-muted-foreground leading-relaxed">{{ __('Choose your interface language.') }}</p>
                        </div>
                        <div x-data="{ open: false, selected: @entangle('settings.locale') }" class="relative">
                            <button 
                                @click="open = !open" 
                                @click.away="open = false"
                                class="h-10 w-48 flex items-center justify-between rounded-md border border-input bg-background px-4 py-2 text-sm font-medium hover:bg-accent/50 transition-colors"
                            >
                                <span x-text="selected === 'system' ? '{{ __('System Default') }}' : (selected === 'pt' ? '{{ __('Portuguese') }}' : (selected === 'en' ? '{{ __('English') }}' : (selected === 'es' ? '{{ __('Spanish') }}' : '{{ __('Russian') }}')))"></span>
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
                                class="absolute right-0 top-12 z-50 min-w-[10rem] overflow-hidden rounded-md border bg-popover p-1 text-popover-foreground shadow-md no-drag"
                            >
                                <div class="px-2 py-1.5 text-[10px] font-black uppercase tracking-widest text-muted-foreground opacity-70">{{ __('Language') }}</div>
                                <button @click="selected = 'system'; open = false" class="relative flex w-full cursor-default select-none items-center rounded-sm px-2 py-1.5 text-xs outline-none hover:bg-accent hover:text-accent-foreground transition-colors">{{ __('System Default') }}</button>
                                <button @click="selected = 'pt'; open = false" class="relative flex w-full cursor-default select-none items-center rounded-sm px-2 py-1.5 text-xs outline-none hover:bg-accent hover:text-accent-foreground transition-colors">{{ __('Portuguese') }}</button>
                                <button @click="selected = 'en'; open = false" class="relative flex w-full cursor-default select-none items-center rounded-sm px-2 py-1.5 text-xs outline-none hover:bg-accent hover:text-accent-foreground transition-colors">{{ __('English') }}</button>
                                <button @click="selected = 'es'; open = false" class="relative flex w-full cursor-default select-none items-center rounded-sm px-2 py-1.5 text-xs outline-none hover:bg-accent hover:text-accent-foreground transition-colors">{{ __('Spanish') }}</button>
                                <button @click="selected = 'ru'; open = false" class="relative flex w-full cursor-default select-none items-center rounded-sm px-2 py-1.5 text-xs outline-none hover:bg-accent hover:text-accent-foreground transition-colors">{{ __('Russian') }}</button>
                            </div>
                        </div>
                    </div>

                    <!-- Divider -->
                    <div class="border-t border-border"></div>

                    <!-- Color Palette Selector -->
                    <div class="space-y-4">
                        <div class="space-y-1">
                            <h4 class="text-[16px] font-black tracking-tight">{{ __('Color Palette') }}</h4>
                            <p class="text-sm text-muted-foreground leading-relaxed">{{ __('Choose an accent color theme for the store.') }}</p>
                        </div>
                        <div class="flex flex-wrap gap-3 pt-2">
                            <!-- Theme: Bamboo (Default Purple) -->
                            <button 
                                wire:click="$set('settings.theme', 'theme-bamboo')"
                                class="flex items-center gap-2.5 px-5 py-3 rounded-xl border text-xs font-black uppercase tracking-wider transition-all select-none
                                {{ (($settings['theme'] ?? 'theme-bamboo') === 'theme-bamboo') ? 'bg-primary/15 text-primary border-primary ring-2 ring-primary/20' : 'bg-card text-muted-foreground border-border hover:bg-accent/50 hover:text-foreground' }}"
                            >
                                <span class="w-4 h-4 rounded-full bg-[#7f3f98] inline-block shadow-sm"></span>
                                <span>Bamboo</span>
                            </button>

                            <!-- Theme: Catppuccin (Pastel Lavender) -->
                            <button 
                                wire:click="$set('settings.theme', 'theme-catppuccin')"
                                class="flex items-center gap-2.5 px-5 py-3 rounded-xl border text-xs font-black uppercase tracking-wider transition-all select-none
                                {{ (($settings['theme'] ?? 'theme-bamboo') === 'theme-catppuccin') ? 'bg-primary/15 text-primary border-primary ring-2 ring-primary/20' : 'bg-card text-muted-foreground border-border hover:bg-accent/50 hover:text-foreground' }}"
                            >
                                <span class="w-4 h-4 rounded-full bg-[#cba6f7] inline-block shadow-sm"></span>
                                <span>Catppuccin</span>
                            </button>

                            <!-- Theme: Nord (Frost Blue) -->
                            <button 
                                wire:click="$set('settings.theme', 'theme-nord')"
                                class="flex items-center gap-2.5 px-5 py-3 rounded-xl border text-xs font-black uppercase tracking-wider transition-all select-none
                                {{ (($settings['theme'] ?? 'theme-bamboo') === 'theme-nord') ? 'bg-primary/15 text-primary border-primary ring-2 ring-primary/20' : 'bg-card text-muted-foreground border-border hover:bg-accent/50 hover:text-foreground' }}"
                            >
                                <span class="w-4 h-4 rounded-full bg-[#88c0d0] inline-block shadow-sm"></span>
                                <span>Nord</span>
                            </button>

                            <!-- Theme: Gruvbox (Retro Orange) -->
                            <button 
                                wire:click="$set('settings.theme', 'theme-gruvbox')"
                                class="flex items-center gap-2.5 px-5 py-3 rounded-xl border text-xs font-black uppercase tracking-wider transition-all select-none
                                {{ (($settings['theme'] ?? 'theme-bamboo') === 'theme-gruvbox') ? 'bg-primary/15 text-primary border-primary ring-2 ring-primary/20' : 'bg-card text-muted-foreground border-border hover:bg-accent/50 hover:text-foreground' }}"
                            >
                                <span class="w-4 h-4 rounded-full bg-[#fe8019] inline-block shadow-sm"></span>
                                <span>Gruvbox</span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Terminal Auto-Close -->
                <div class="bg-card rounded-xl p-10 space-y-8 shadow-md">
                    <div class="flex items-center justify-between">
                        <div class="space-y-1">
                            <h4 class="text-[17px] font-black tracking-tight">{{ __('Terminal Auto-close') }}</h4>
                            <p class="text-sm text-muted-foreground leading-relaxed">{{ __('Automatically close terminal window after process completion.') }}</p>
                        </div>
                        <button 
                            wire:click="$set('settings.auto_close_terminal', {{ !$settings['auto_close_terminal'] ? 'true' : 'false' }})"
                            class="w-12 h-6 rounded-full transition-all relative {{ $settings['auto_close_terminal'] ? 'bg-bamboo shadow-lg shadow-bamboo/20' : 'bg-muted' }}"
                        >
                            <div class="absolute top-1 left-1 bg-white w-4 h-4 rounded-full transition-transform {{ $settings['auto_close_terminal'] ? 'translate-x-6' : '' }}"></div>
                        </button>
                    </div>

                    @if($settings['auto_close_terminal'] ?? true)
                        <div class="pt-8 flex items-center justify-between border-t border-border">
                            <div class="space-y-1">
                                <h4 class="text-[17px] font-black tracking-tight">{{ __('Close Delay') }}</h4>
                                <p class="text-sm text-muted-foreground leading-relaxed">{{ __('Set countdown duration before the terminal window closes.') }}</p>
                            </div>
                            <div x-data="{ open: false, selected: @entangle('settings.terminal_close_delay') }" class="relative">
                                <button 
                                    @click="open = !open" 
                                    @click.away="open = false"
                                    class="h-10 w-40 flex items-center justify-between rounded-md border border-input bg-background px-4 py-2 text-sm font-medium hover:bg-accent/50 transition-colors"
                                >
                                    <span x-text="selected + ' {{ __('seconds') }}'"></span>
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
                                    class="absolute right-0 top-12 z-50 min-w-[10rem] overflow-hidden rounded-md border bg-popover p-1 text-popover-foreground shadow-md no-drag"
                                >
                                    <div class="px-2 py-1.5 text-[10px] font-black uppercase tracking-widest text-muted-foreground opacity-70">{{ __('Delay') }}</div>
                                    <button @click="selected = 5; open = false" class="relative flex w-full cursor-default select-none items-center rounded-sm px-2 py-1.5 text-xs outline-none hover:bg-accent hover:text-accent-foreground transition-colors">5 {{ __('seconds') }}</button>
                                    <button @click="selected = 10; open = false" class="relative flex w-full cursor-default select-none items-center rounded-sm px-2 py-1.5 text-xs outline-none hover:bg-accent hover:text-accent-foreground transition-colors">10 {{ __('seconds') }}</button>
                                    <button @click="selected = 15; open = false" class="relative flex w-full cursor-default select-none items-center rounded-sm px-2 py-1.5 text-xs outline-none hover:bg-accent hover:text-accent-foreground transition-colors">15 {{ __('seconds') }}</button>
                                    <button @click="selected = 30; open = false" class="relative flex w-full cursor-default select-none items-center rounded-sm px-2 py-1.5 text-xs outline-none hover:bg-accent hover:text-accent-foreground transition-colors">30 {{ __('seconds') }}</button>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>

                <!-- Storage Directory -->
                <div class="bg-card rounded-xl p-10 flex items-center justify-between shadow-md">
                    <div class="space-y-1">
                        <h4 class="text-[17px] font-black tracking-tight">{{ __('AppImage Storage Directory') }}</h4>
                        <p class="text-sm text-muted-foreground leading-relaxed">{{ __('Choose where your AppImages are stored and managed.') }}</p>
                    </div>
                    <div class="flex items-center gap-4">
                        <div class="px-4 py-2 bg-muted/50 rounded-md text-xs font-mono text-muted-foreground truncate max-w-[200px]">
                            {{ str_replace(getenv('HOME'), '~', $settings['appimage_path']) }}
                        </div>
                        <button 
                            wire:click="selectAppImagePath"
                            class="h-10 px-4 bg-accent/50 rounded-md text-xs font-bold hover:bg-accent transition-colors"
                        >
                            {{ __('Change') }}
                        </button>
                    </div>
                </div>

                <!-- Cache Management -->
                <div class="bg-card rounded-xl p-10 flex items-center justify-between shadow-md">
                    <div class="space-y-1">
                        <h4 class="text-[17px] font-black tracking-tight">{{ __('Clear Cache') }}</h4>
                        <p class="text-sm text-muted-foreground leading-relaxed">{{ __('Clear all cached package details, searches, and comments.') }}</p>
                    </div>
                    <div>
                        <button 
                            wire:click="clearCache"
                            class="h-10 px-6 bg-destructive/10 hover:bg-destructive text-destructive hover:text-destructive-foreground text-xs font-black rounded-md transition-all uppercase tracking-widest"
                        >
                            {{ __('Clear Cache') }}
                        </button>
                    </div>
                </div>


                <!-- Save Changes -->
                <div class="flex justify-end pt-4">
                    <button wire:click="saveSettings" class="h-12 px-10 bg-primary text-primary-foreground text-xs font-black rounded-md hover:bg-primary/90 transition-all uppercase tracking-[0.2em] shadow-xl">{{ __('Save Changes') }}</button>
                </div>
            </div>
        </div>

        <!-- About Author Section -->
        <div class="pt-8">
            <div class="bg-card rounded-xl p-6 shadow-lg flex flex-col sm:flex-row items-center gap-6">
                <div class="w-16 h-16 rounded-full bg-bamboo/15 flex items-center justify-center border border-background shadow overflow-hidden text-2xl shrink-0">🐼</div>
                <div class="flex-1 text-center sm:text-left">
                    <div class="flex flex-col sm:flex-row sm:items-center gap-2 mb-1.5">
                        <h3 class="text-lg font-black tracking-tight">Panda</h3>
                        <span class="text-[10px] text-muted-foreground font-black uppercase tracking-widest sm:border-l sm:border-border sm:pl-2">Eduardo Sato — São Paulo, Brazil</span>
                    </div>
                    <p class="text-[13px] text-muted-foreground leading-relaxed">
                        {{ __('Developer and EndeavourOS fan. Built with ❤️ to enhance the Linux experience.') }}
                    </p>
                </div>
                <div class="shrink-0">
                    <a href="https://github.com/satodu" target="_blank" class="h-10 px-5 bg-muted/30 hover:bg-accent rounded-lg flex items-center gap-2.5 transition-all text-xs font-bold">
                        <svg class="w-4 h-4 text-foreground" fill="currentColor" viewBox="0 0 24 24"><path d="M12 0c-6.626 0-12 5.373-12 12 0 5.302 3.438 9.8 8.207 11.387.599.111.793-.261.793-.577v-2.234c-3.338.726-4.033-1.416-4.033-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23.957-.266 1.983-.399 3.003-.404 1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576 4.765-1.589 8.199-6.086 8.199-11.386 0-6.627-5.373-12-12-12z"/></svg>
                        <span>GitHub</span>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
