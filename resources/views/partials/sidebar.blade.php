@php
    $grouped = collect($menuItems)->groupBy('group');
@endphp
<aside class="sidebar hidden lg:flex flex-col fixed inset-y-0 left-0 z-40 select-none" :class="{ 'is-collapsed': sidebarCollapsed }">
    <!-- Brand Header (3D Skeuomorphic Paper Plate) -->
    <div class="flex items-center justify-between px-4 h-20 border-b border-white/10" style="background: linear-gradient(180deg, rgba(255,255,255,0.04) 0%, transparent 100%);">
        <a href="{{ auth()->user()->dashboardRoute() }}" class="flex items-center gap-3 overflow-hidden">
            <x-logo size="md" />
            <div class="side-hide leading-tight">
                <div class="font-black text-white text-sm tracking-tight flex items-center gap-1.5" style="text-shadow: 0 1px 2px rgba(0,0,0,0.6);">
                    <span>D&P PT. HSM</span>
                </div>
                <div class="text-[11px] text-orange-400 font-bold truncate">{{ auth()->user()->roleLabel() }}</div>
            </div>
        </a>
        <button type="button" @click="toggleSidebar()" class="p-2 rounded-lg text-slate-400 hover:text-white hover:bg-white/5 transition" title="Lipat / Buka Sidebar">
            <svg class="w-4 h-4 transition-transform duration-300" :class="{ 'rotate-180': sidebarCollapsed }" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="m15 18-6-6 6-6"/>
            </svg>
        </button>
    </div>

    <!-- Navigation Links -->
    <nav class="flex-1 overflow-y-auto px-3 py-3 space-y-1">
        @foreach($grouped as $groupName => $items)
            <div class="nav-group">{{ $groupName }}</div>
            @foreach($items as $item)
                <a href="{{ $item['url'] }}" class="nav-item {{ $item['active'] ? 'is-active' : '' }}" title="{{ $item['label'] }}">
                    <x-icon :name="$item['icon']" class="w-5 h-5 nav-icon" />
                    <span class="nav-label">{{ $item['label'] }}</span>
                </a>
            @endforeach
        @endforeach
    </nav>

    <!-- Footer PWA Status & Profile -->
    <div class="p-3 border-t border-white/10 space-y-2" style="background: linear-gradient(180deg, transparent 0%, rgba(0,0,0,0.3) 100%);">
        <div class="flex items-center gap-2.5 px-2.5 py-2 rounded-xl bg-white/[0.04] border border-white/10 shadow-inner">
            <span class="w-2.5 h-2.5 rounded-full flex-none pulse-dot"
                  :class="online ? 'bg-emerald-400 text-emerald-400' : 'bg-amber-400 text-amber-400'"></span>
            <div class="side-hide text-xs">
                <div class="font-bold text-white" x-text="online ? 'Sistem D&P Online' : 'Mode Offline'">Sistem D&P Online</div>
                <div class="text-[10px] text-slate-400">Paper 3D Edition</div>
            </div>
        </div>
        <div class="flex items-center justify-between gap-2 px-2 py-1.5">
            <a href="{{ route('profile.edit') }}" class="flex items-center gap-2.5 min-w-0">
                <div class="w-8 h-8 rounded-full border border-orange-400/40 flex items-center justify-center text-xs font-black text-white flex-none"
                     style="background: linear-gradient(180deg, #ff8800 0%, #ff6500 100%); box-shadow: 0 2px 6px rgba(0,0,0,0.5), inset 0 1px 0 rgba(255,255,255,0.4);">
                    {{ auth()->user()->initials() }}
                </div>
                <div class="side-hide truncate">
                    <div class="text-xs font-bold text-white truncate">{{ auth()->user()->name }}</div>
                    <div class="text-[10px] text-slate-400 truncate">{{ auth()->user()->email }}</div>
                </div>
            </a>
            <form method="POST" action="{{ route('logout') }}" class="side-hide">
                @csrf
                <button type="submit" class="p-1.5 rounded-lg text-slate-400 hover:text-red-400 hover:bg-red-500/10 transition" title="Keluar">
                    <x-icon name="logout" class="w-4 h-4" />
                </button>
            </form>
        </div>
    </div>
</aside>
