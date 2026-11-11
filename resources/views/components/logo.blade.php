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
        <img src="/icons/icon-192.svg" alt="D&P PT. HSM Logo" class="{{ $dim }} flex-none rounded-2xl shadow-md border border-white/20" style="box-shadow: 0 4px 12px rgba(0,0,0,0.5), inset 0 1px 0 rgba(255,255,255,0.3);" />
        <div class="leading-tight">
            <div class="flex items-center gap-1.5">
                <span class="font-black text-white text-base tracking-tight" style="text-shadow: 0 1px 3px rgba(0,0,0,0.6);">D&P</span>
                <span class="text-[10px] font-extrabold text-white uppercase tracking-wider px-1.5 py-0.5 rounded shadow-sm" style="background: linear-gradient(180deg, #ff8c26 0%, #ff6500 100%); border: 1px solid #ff7a1a; border-top: 1px solid #ffba7a;">PT. HSM</span>
            </div>
            <div class="text-[10px] text-slate-300 font-bold tracking-wide">Delivery & Pick-Up System</div>
        </div>
    </div>
@else
    <img src="/icons/icon-192.svg" alt="D&P PT. HSM Icon" {{ $attributes->merge(['class' => $dim . ' rounded-2xl border border-white/20 transition-transform duration-200 hover:scale-105 select-none ' . $class]) }} style="box-shadow: 0 4px 14px rgba(0,0,0,0.5), inset 0 1px 0 rgba(255,255,255,0.3);" />
@endif
