@php
    $dockData = \App\Support\Menu::dock($menuItems, auth()->user());
    $dockItems = $dockData['dock'];
    $moreItems = $dockData['more'];
    $hasMore = count($moreItems) > 0;
    $totalSlots = count($dockItems) + ($hasMore ? 1 : 0);

    $activeIndex = 0;
    foreach ($dockItems as $idx => $dItem) {
        if ($dItem['active']) {
            $activeIndex = $idx;
            break;
        }
    }
    if ($hasMore && collect($moreItems)->contains('active', true)) {
        $activeIndex = count($dockItems);
    }
@endphp

<div class="lg:hidden" x-data="{
    active: {{ $activeIndex }},
    moving: false,
    moreOpen: false,
    go(idx, url) {
        if (this.active !== idx) {
            this.active = idx;
            this.moving = true;
            setTimeout(() => { this.moving = false; }, 520);
        }
        if (url) {
            setTimeout(() => { window.location.href = url; }, 160);
        } else {
            this.moreOpen = !this.moreOpen;
        }
    }
}">
    <!-- "Lainnya" Bottom Sheet for Multi-Menu Roles -->
    @if($hasMore)
        <div x-cloak x-show="moreOpen" @click="moreOpen = false"
             x-transition.opacity
             class="fixed inset-0 z-40 bg-black/60 backdrop-blur-xs"></div>

        <div x-cloak x-show="moreOpen"
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="translate-y-full opacity-0"
             x-transition:enter-end="translate-y-0 opacity-100"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="translate-y-0 opacity-100"
             x-transition:leave-end="translate-y-full opacity-0"
             class="more-sheet glass-strong">
            <div class="w-12 h-1.5 bg-white/20 rounded-full mx-auto mb-4"></div>
            <div class="flex items-center justify-between mb-3 px-1">
                <div>
                    <div class="text-sm font-extrabold text-white">Menu Navigasi Lengkap</div>
                    <div class="text-xs text-slate-400">{{ auth()->user()->roleLabel() }}</div>
                </div>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="btn btn-danger btn-sm">
                        <x-icon name="logout" class="w-4 h-4" /> Keluar
                    </button>
                </form>
            </div>
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-2.5">
                @foreach($moreItems as $mItem)
                    <a href="{{ $mItem['url'] }}"
                       class="flex items-center gap-2.5 p-3 rounded-xl border transition {{ $mItem['active'] ? 'bg-orange-500/20 border-orange-500/50 text-white' : 'bg-white/[0.04] border-white/10 text-slate-300 hover:text-white' }}">
                        <x-icon :name="$mItem['icon']" class="w-5 h-5 text-orange-400 flex-none" />
                        <span class="text-xs font-bold truncate">{{ $mItem['label'] }}</span>
                    </a>
                @endforeach
            </div>
        </div>
    @endif

    <!-- Flush Bottom Dock with Upward Light Beam Animation -->
    <div class="dock-wrap">
        <nav class="dock" style="--n: {{ $totalSlots }}; --i: {{ $activeIndex }};" :style="`--n: {{ $totalSlots }}; --i: ${active};`" aria-label="Navigasi Mobile">
            <!-- Upward Light Beam Stage -->
            <div class="dock-light-stage" aria-hidden="true">
                <div class="light-track" :class="{ 'is-moving': moving }">
                    <div class="light-beam"></div>
                    <div class="light-bloom"></div>
                    <div class="light-source"></div>
                </div>
            </div>

            <!-- Interactive Menu Items -->
            <ul class="dock-items">
                @foreach($dockItems as $idx => $dItem)
                    <li>
                        <a href="{{ $dItem['url'] }}"
                           @click.prevent="go({{ $idx }}, '{{ $dItem['url'] }}')"
                           class="dock-item"
                           :class="{ 'is-active': active === {{ $idx }} }">
                            <x-icon :name="$dItem['icon']" class="w-5 h-5 dock-icon" />
                            <span class="dock-label">{{ $dItem['label'] }}</span>
                        </a>
                    </li>
                @endforeach

                @if($hasMore)
                    <li>
                        <button type="button"
                                @click="go({{ count($dockItems) }}, null)"
                                class="dock-item"
                                :class="{ 'is-active': active === {{ count($dockItems) }} || moreOpen }">
                            <x-icon name="grid" class="w-5 h-5 dock-icon" />
                            <span class="dock-label">Lainnya</span>
                        </button>
                    </li>
                @endif
            </ul>
        </nav>
    </div>
</div>
