@php
    $grouped = collect($menuItems)->groupBy('group');
@endphp
<aside class="sidebar hidden lg:flex flex-col fixed inset-y-0 left-0 z-40 select-none" :class="{ 'is-collapsed': sidebarCollapsed }">
    <!-- Brand Header (Paper 3D Header) -->
    <div class="flex items-center justify-between px-4 h-20 border-b border-slate-200" style="background: linear-gradient(180deg, #ffffff 0%, #fafafa 100%);">
        <a href="{{ auth()->user()->dashboardRoute() }}" class="flex items-center gap-3 overflow-hidden">
            <x-logo size="md" />
            <div class="side-hide leading-tight">
                <div class="font-black text-slate-900 text-sm tracking-tight flex items-center gap-1.5">
                    <span>D&P PT. HSM</span>
                </div>
                <div class="text-[11px] text-orange-600 font-bold truncate">{{ auth()->user()->roleLabel() }}</div>
            </div>
        </a>
        <button type="button" @click="toggleSidebar()" class="p-2 rounded-lg text-slate-400 hover:text-slate-700 hover:bg-slate-100 transition" title="Lipat / Buka Sidebar">
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
    <div class="p-3 border-t border-slate-200 space-y-2" style="background: linear-gradient(180deg, #fafafa 0%, #f1f5f9 100%);">
        <div class="flex items-center gap-2.5 px-2.5 py-2 rounded-xl bg-white border border-slate-200 shadow-xs">
            <span class="w-2.5 h-2.5 rounded-full flex-none pulse-dot"
                  :class="online ? 'bg-emerald-500 text-emerald-500' : 'bg-amber-500 text-amber-500'"></span>
            <div class="side-hide text-xs">
                <div class="font-bold text-slate-800" x-text="online ? 'Sistem D&P Online' : 'Mode Offline'">Sistem D&P Online</div>
                <div class="text-[10px] text-slate-500">Paper White Edition</div>
            </div>
        </div>
        <div class="flex items-center justify-between gap-2 px-2 py-1.5">
            <a href="{{ route('profile.edit') }}" class="flex items-center gap-2.5 min-w-0">
                <div class="w-8 h-8 rounded-full border border-orange-400 flex items-center justify-center text-xs font-black text-white flex-none"
                     style="background: linear-gradient(180deg, #ff8800 0%, #ea580c 100%); box-shadow: 0 2px 6px rgba(234,88,12,0.3), inset 0 1px 0 rgba(255,255,255,0.4);">
                    {{ auth()->user()->initials() }}
                </div>
                <div class="side-hide truncate">
                    <div class="text-xs font-bold text-slate-900 truncate">{{ auth()->user()->name }}</div>
                    <div class="text-[10px] text-slate-500 truncate">{{ auth()->user()->phone ?? auth()->user()->email }}</div>
                </div>
            </a>
            <form method="POST" action="{{ route('logout') }}" class="side-hide">
                @csrf
                <button type="submit" class="p-1.5 rounded-lg text-slate-400 hover:text-red-600 hover:bg-red-50 transition" title="Keluar">
                    <x-icon name="logout" class="w-4 h-4" />
                </button>
            </form>
        </div>
    </div>
</aside>
