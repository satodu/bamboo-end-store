<?php

use Livewire\Volt\Component;
use App\Services\PackageManagers\AurManager;

new class extends Component {
    public string $packageName = '';
    public array  $comments    = [];
    public bool   $loading     = true;
    public bool   $error       = false;
    public int    $perPage     = 5;

    public function mount(string $packageName): void
    {
        $this->packageName = $packageName;
    }

    public function loadComments(): void
    {
        $this->loading = true;
        $this->error   = false;

        try {
            $this->comments = (new AurManager())->getComments($this->packageName);
        } catch (\Throwable $e) {
            $this->error = true;
        } finally {
            $this->loading = false;
        }
    }

    public function loadMore(): void
    {
        $this->perPage += 5;
    }
};

?>

<div wire:init="loadComments" class="space-y-4">

    <div class="flex items-center justify-between">
        <h3 class="text-[11px] font-black uppercase tracking-[0.2em] text-muted-foreground flex items-center gap-2">
            <span>Community Comments</span>
            @if(!empty($comments))
                <span class="bg-purple-500/10 text-purple-400 font-extrabold text-[10px] px-1.5 py-0.5 rounded-full">
                    {{ count($comments) }}
                </span>
            @endif
        </h3>
        <a
            href="https://aur.archlinux.org/packages/{{ $packageName }}#comment-form"
            target="_blank"
            class="text-[10px] font-bold text-purple-400 hover:text-purple-300 transition-colors flex items-center gap-1"
        >
            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"
                    d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
            </svg>
            AUR page
        </a>
    </div>

    {{-- Skeleton loader --}}
    @if($loading)
        <div class="space-y-3">
            @for($i = 0; $i < 3; $i++)
                <div class="bg-card rounded-xl p-4 animate-pulse">
                    <div class="flex items-center gap-3 mb-3">
                        <div class="w-8 h-8 rounded-full bg-muted"></div>
                        <div class="space-y-1.5 flex-1">
                            <div class="h-3 bg-muted rounded w-24"></div>
                            <div class="h-2.5 bg-muted rounded w-36"></div>
                        </div>
                    </div>
                    <div class="space-y-2">
                        <div class="h-2.5 bg-muted rounded w-full"></div>
                        <div class="h-2.5 bg-muted rounded w-5/6"></div>
                        <div class="h-2.5 bg-muted rounded w-4/6"></div>
                    </div>
                </div>
            @endfor
        </div>

    {{-- Error state --}}
    @elseif($error)
        <div class="bg-card rounded-xl p-5 flex items-center gap-3 text-muted-foreground">
            <svg class="w-5 h-5 shrink-0 text-destructive" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <p class="text-xs font-bold">Could not load comments. Check your connection.</p>
            <button wire:click="loadComments" class="ml-auto text-xs font-black text-purple-400 hover:text-purple-300 transition-colors">
                Retry
            </button>
        </div>

    {{-- Empty state --}}
    @elseif(empty($comments))
        <div class="bg-card rounded-xl p-5 flex items-center gap-3 text-muted-foreground justify-center">
            <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
            </svg>
            <p class="text-xs font-bold">No comments yet for this package.</p>
        </div>

    {{-- Comments list --}}
    @else
        <div class="space-y-3">
            @foreach(array_slice($comments, 0, $perPage) as $comment)
                <div class="bg-card rounded-xl p-4 group hover:ring-1 hover:ring-purple-500/30 transition-all">
                    <div class="flex items-start gap-3">

                        {{-- Avatar --}}
                        <div class="w-8 h-8 rounded-full bg-purple-500/20 flex items-center justify-center shrink-0 mt-0.5">
                            <span class="text-xs font-black text-purple-400 uppercase">
                                {{ mb_substr($comment['author'], 0, 1) }}
                            </span>
                        </div>

                        <div class="flex-1 min-w-0">
                            {{-- Header --}}
                            <div class="flex items-center justify-between gap-2 mb-1.5">
                                <span class="text-xs font-black text-foreground truncate">
                                    {{ $comment['author'] }}
                                </span>
                                <span class="text-[10px] text-muted-foreground font-medium shrink-0">
                                    {{ $comment['date'] }}
                                </span>
                            </div>

                            {{-- Content --}}
                            <p class="text-xs leading-relaxed text-muted-foreground whitespace-pre-line select-text">
                                {{ $comment['content'] }}
                            </p>
                        </div>
                    </div>
                </div>
            @endforeach


            {{-- Footer Pagination Control --}}
            <div class="pt-2 flex flex-col items-center gap-2">
                @if(count($comments) > $perPage)
                    <button
                        wire:click="loadMore"
                        wire:loading.attr="disabled"
                        class="w-full py-2.5 bg-purple-500/10 hover:bg-purple-500/20 border border-purple-500/20 text-purple-400 text-xs font-black rounded-lg transition-all uppercase tracking-widest flex items-center justify-center gap-2 disabled:opacity-50"
                    >
                        <span wire:loading.remove wire:target="loadMore">
                            Load More Comments ({{ count($comments) - $perPage }} left)
                        </span>
                        <span wire:loading wire:target="loadMore" class="flex items-center gap-1.5">
                            <svg class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path>
                            </svg>
                            Loading...
                        </span>
                    </button>
                @else
                    <p class="text-center text-[10px] text-muted-foreground font-bold py-1">
                        Showing all {{ count($comments) }} comments
                    </p>
                @endif
            </div>
        </div>
    @endif

</div>
