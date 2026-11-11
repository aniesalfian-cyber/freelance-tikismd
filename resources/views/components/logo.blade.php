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
        <img src="/icons/icon-192.svg" alt="D&P PT. HSM Logo" class="{{ $dim }} flex-none rounded-2xl shadow-sm border border-slate-200" style="box-shadow: 0 2px 8px rgba(15,23,42,0.1), inset 0 1px 0 rgba(255,255,255,0.8);" />
        <div class="leading-tight">
            <div class="flex items-center gap-1.5">
                <span class="font-black text-slate-900 text-base tracking-tight">D&P</span>
                <span class="text-[10px] font-extrabold text-white uppercase tracking-wider px-1.5 py-0.5 rounded shadow-xs" style="background: linear-gradient(180deg, #ff8c26 0%, #ea580c 100%); border: 1px solid #ea580c; border-top: 1px solid #fdba74;">PT. HSM</span>
            </div>
            <div class="text-[10px] text-slate-500 font-bold tracking-wide">Delivery & Pick-Up System</div>
        </div>
    </div>
@else
    <img src="/icons/icon-192.svg" alt="D&P PT. HSM Icon" {{ $attributes->merge(['class' => $dim . ' rounded-2xl border border-slate-200 transition-transform duration-200 hover:scale-105 select-none ' . $class]) }} style="box-shadow: 0 3px 10px rgba(15,23,42,0.12), inset 0 1px 0 rgba(255,255,255,0.8);" />
@endif
