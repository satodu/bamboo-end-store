@props(['tab', 'sysInfo', 'pendingInstallations'])

<aside {{ $attributes->merge(['class' => 'w-72 bg-card shadow-xl flex flex-col shrink-0 relative z-50']) }}>
    <div class="p-8 flex-1">
        <button wire:click="setTab('explore')" class="w-full flex items-center gap-4 mb-2 px-2 hover:bg-accent/10 rounded-xl transition-all group active:scale-95 text-left">
            <img src="/logo-without-background.png" class="w-12 h-12 object-contain group-hover:-translate-y-1 group-hover:scale-110 transition-all duration-300 ease-out" alt="Logo">
            <div>
                <h1 class="text-xl font-black tracking-tighter leading-none">Bamboo End Store</h1>
                <span class="text-[9px] font-black uppercase text-bamboo/70 tracking-widest">Community Edition</span>
            </div>
        </button>
        <p class="px-2 mb-10 text-[10px] text-muted-foreground font-medium italic">Unofficial fan project</p>

        <nav class="space-y-1.5">
            <button 
                wire:click="setTab('explore')"
                class="w-full flex items-center gap-4 px-4 py-3 rounded-md text-[15px] transition-all {{ $tab === 'explore' ? 'bg-accent text-accent-foreground font-bold' : 'text-muted-foreground hover:bg-accent/40 hover:text-foreground' }}"
            >
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                <span>Explore</span>
            </button>
            <button 
                wire:click="setTab('installed')"
                class="w-full flex items-center gap-4 px-4 py-3 rounded-md text-[15px] transition-all {{ $tab === 'installed' ? 'bg-accent text-accent-foreground font-bold' : 'text-muted-foreground hover:bg-accent/40 hover:text-foreground' }}"
            >
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                <span>Installed</span>
            </button>
            <button 
                wire:click="setTab('appimages')"
                class="w-full flex items-center gap-4 px-4 py-3 rounded-md text-[15px] transition-all {{ $tab === 'appimages' ? 'bg-accent text-accent-foreground font-bold' : 'text-muted-foreground hover:bg-accent/40 hover:text-foreground' }}"
            >
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM16 13a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z"></path></svg>
                <span>AppImages</span>
            </button>
            <button 
                wire:click="runSystemUpdate"
                class="w-full flex items-center gap-4 px-4 py-3 rounded-md text-[15px] transition-all text-muted-foreground hover:bg-bamboo/10 hover:text-bamboo"
            >
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                <span>Update System</span>
            </button>

            <button 
                wire:click="setTab('settings')"
                class="w-full flex items-center gap-4 px-4 py-3 rounded-md text-[15px] transition-all {{ $tab === 'settings' ? 'bg-accent text-accent-foreground font-bold' : 'text-muted-foreground hover:bg-accent/40 hover:text-foreground' }}"
            >
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                <span>Settings</span>
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
