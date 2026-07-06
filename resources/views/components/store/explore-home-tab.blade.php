@props(['packages', 'systemPackages', 'pendingInstallations', 'userState' => 'idle', 'activePackage' => null])

{{-- Official Endeavour Tools --}}
@if(!empty($systemPackages))
    <div class="mb-14">
        <h3 class="text-xs font-black text-muted-foreground uppercase tracking-[0.2em] mb-8">{{ __('Official EndeavourOS Tools') }}</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
            @foreach($systemPackages as $pkg)
                <x-store.package-card :$pkg :$pendingInstallations :$userState :$activePackage wire:key="sys-{{ $pkg['name'] }}" />
            @endforeach
        </div>
    </div>
@endif

{{-- Popular Software --}}
<div class="mb-10">
    <h2 class="text-3xl font-black tracking-tight mb-2">{{ __('Popular Software') }}</h2>
</div>

<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
    @foreach($packages as $pkg)
        <x-store.package-card :$pkg :$pendingInstallations :$userState :$activePackage wire:key="home-{{ $pkg['name'] }}" />
    @endforeach
</div>
