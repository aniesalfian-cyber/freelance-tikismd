@props(['size' => 'md', 'type' => 'icon', 'class' => ''])

@php
    $dim = match($size) {
        'xs' => 'w-6 h-6',
        'sm' => 'w-8 h-8',
        'md' => 'w-10 h-10',
        'lg' => 'w-14 h-14',
        'xl' => 'w-20 h-20',
        default => 'w-10 h-10',
    };
@endphp

@if($type === 'horizontal')
    <div {{ $attributes->merge(['class' => 'flex items-center gap-3 select-none ' . $class]) }}>
        <img src="/icons/icon-192.svg" alt="HSM Logo" class="{{ $dim }} flex-none rounded-2xl shadow-lg shadow-orange-500/20" />
        <div class="leading-tight">
            <div class="flex items-center gap-1.5">
                <span class="font-extrabold text-white text-base tracking-tight">HSM</span>
                <span class="text-[10px] font-bold text-orange-400 uppercase tracking-wider px-1.5 py-0.5 rounded-md bg-orange-500/10 border border-orange-500/20">Freelance</span>
            </div>
            <div class="text-[10px] text-slate-400 font-medium tracking-wide">Monitoring System</div>
        </div>
    </div>
@else
    <img src="/icons/icon-192.svg" alt="HSM Icon" {{ $attributes->merge(['class' => $dim . ' rounded-2xl shadow-lg shadow-orange-500/25 transition-transform duration-300 hover:scale-105 select-none ' . $class]) }} />
@endif
