@php
    $currentUser = auth()->user();
    $menuItems = \App\Support\Menu::for($currentUser);
    $todayAtt = $currentUser->isFreelancer()
        ? \App\Models\Attendance::where('user_id', $currentUser->id)->whereDate('date', today())->first()
        : null;
    $isCourierWorking = $todayAtt !== null && $todayAtt->check_out_at === null;
    $initialUnread = \App\Models\AppNotification::where('user_id', $currentUser->id)->whereNull('read_at')->count();
    $initialNotifs = \App\Models\AppNotification::where('user_id', $currentUser->id)->latest()->limit(8)->get()->map(fn ($n) => [
        'id' => $n->id,
        'title' => $n->title,
        'body' => $n->body,
        'url' => $n->url ?: route('pwa.workspace'),
        'is_unread' => $n->read_at === null,
        'time_ago' => $n->created_at?->diffForHumans() ?? 'Baru saja',
    ]);
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#F3F4F6">
    <link rel="manifest" href="/manifest.webmanifest">
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="apple-touch-icon" href="/icons/icon-192.svg">
    <title>{{ isset($title) ? $title.' — ' : '' }}{{ config('app.name', 'D&P PT. HSM') }}</title>
    <script>
        window.HSM_SAMARINDA = { lat: -0.502106, lng: 117.153709, zoom: 14, label: 'Kota Samarinda, Kalimantan Timur' };
        window.__hsmReadyQueue = window.__hsmReadyQueue || [];
        window.hsmReady = window.hsmReady || function (fn) { window.__hsmReadyQueue.push(fn); };
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body x-data="{
    sidebarCollapsed: localStorage.getItem('hsm_side') === '1',
    online: navigator.onLine,
    isStandalone: window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true,
    isWorking: @js($isCourierWorking),
    gpsActive: false,
    lastGpsTime: @js($currentUser->last_location_at?->format('H:i:s') ?? ''),
    currentCoords: null,
    notifOpen: false,
    unreadCount: @js($initialUnread),
    notifications: @js($initialNotifs),
    pushPermission: ('Notification' in window) ? Notification.permission : 'unsupported',
    toggleSidebar() {
        this.sidebarCollapsed = !this.sidebarCollapsed;
        localStorage.setItem('hsm_side', this.sidebarCollapsed ? '1' : '0');
    },
    installPwa() {
        if (window.hsmTriggerPwaInstall) window.hsmTriggerPwaInstall();
    },
    async enablePush() {
        if (window.hsmRequestNotificationPermission) {
            this.pushPermission = await window.hsmRequestNotificationPermission();
        }
    },
    async openNotification(n) {
        try {
            await fetch('/notifications/' + n.id + '/read', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Accept': 'application/json' }
            });
        } catch (_) {}
        window.location.href = n.url || '/pwa/workspace';
    },
    async markAllRead() {
        try {
            await fetch('/notifications/read-all', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Accept': 'application/json' }
            });
            this.unreadCount = 0;
            this.notifications = this.notifications.map(x => ({ ...x, is_unread: false }));
        } catch (_) {}
    },
    async sendHeartbeat() {
        if (!this.online) return;
        const payload = {};
        if (this.isWorking && this.currentCoords) {
            payload.latitude = this.currentCoords.lat;
            payload.longitude = this.currentCoords.lng;
            payload.accuracy = this.currentCoords.acc;
        }
        try {
            const res = await fetch('{{ route('pwa.heartbeat') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content
                },
                body: JSON.stringify(payload)
            });
            if (!res.ok) return;
            const data = await res.json();
            if (data.ok) {
                this.isWorking = data.is_working;
                this.unreadCount = data.unread_count;
                this.notifications = data.notifications || [];
                if (data.location_saved) {
                    this.gpsActive = true;
                    this.lastGpsTime = data.last_location_at || '';
                }
                if (Array.isArray(data.push_queue) && data.push_queue.length > 0) {
                    data.push_queue.forEach(item => {
                        if (window.hsmShowPushNotification) {
                            window.hsmShowPushNotification(item.title, item.body, item.url);
                        }
                    });
                }
            }
        } catch (_) {}
    },
    initLiveTracking() {
        window.addEventListener('appinstalled', () => { this.isStandalone = true; });
        if (this.isWorking && 'geolocation' in navigator) {
            navigator.geolocation.getCurrentPosition(
                (pos) => {
                    this.currentCoords = { lat: pos.coords.latitude, lng: pos.coords.longitude, acc: pos.coords.accuracy };
                    this.gpsActive = true;
                    this.sendHeartbeat();
                },
                () => {},
                { enableHighAccuracy: true, timeout: 10000, maximumAge: 5000 }
            );
            navigator.geolocation.watchPosition(
                (pos) => {
                    this.currentCoords = { lat: pos.coords.latitude, lng: pos.coords.longitude, acc: pos.coords.accuracy };
                    this.gpsActive = true;
                },
                () => {},
                { enableHighAccuracy: true, maximumAge: 10000 }
            );
        }
        setTimeout(() => this.sendHeartbeat(), 2500);
        setInterval(() => this.sendHeartbeat(), 15000);
    }
}" x-init="initLiveTracking()" @online.window="online = true" @offline.window="online = false">

    <!-- Paper 3D Desktop/Tablet Sidebar -->
    @include('partials.sidebar', ['menuItems' => $menuItems])

    <!-- Main Content Area -->
    <div class="min-h-dvh flex flex-col transition-all duration-300 pb-28 lg:pb-10"
         :class="sidebarCollapsed ? 'lg:pl-[5.25rem]' : 'lg:pl-[17rem]'">

        <!-- Topbar -->
        <header class="topbar sticky top-0 z-30 px-4 sm:px-6 h-16 flex items-center justify-between gap-3">
            <div class="flex items-center gap-3 min-w-0">
                <a href="{{ $currentUser->dashboardRoute() }}" class="lg:hidden flex-none">
                    <x-logo size="sm" />
                </a>
                <div class="min-w-0">
                    <h1 class="text-sm sm:text-base font-extrabold text-slate-900 truncate">{{ $title ?? 'Delivery & Pick-Up PT. HSM' }}</h1>
                    <p class="text-[11px] text-slate-500 truncate">{{ $subtitle ?? now()->translatedFormat('l, d F Y') }}</p>
                </div>
            </div>

            <div class="flex items-center gap-2">
                <!-- Tombol Install PWA jika belum terinstall -->
                <template x-if="!isStandalone">
                    <button type="button" @click="installPwa()"
                            class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-xl bg-orange-50 hover:bg-orange-100 text-orange-700 border border-orange-300 text-xs font-extrabold shadow-xs transition"
                            title="Pasang Aplikasi PWA di Perangkat Anda">
                        <x-icon name="download" class="w-3.5 h-3.5 text-orange-600" />
                        <span class="hidden sm:inline">Install PWA</span>
                    </button>
                </template>

                <!-- Indikator Live GPS Aktif untuk Kurir yang Sedang Masuk Kerja -->
                @if($currentUser->isFreelancer())
                    <template x-if="isWorking">
                        <span class="hidden md:inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-bold bg-emerald-50 text-emerald-800 border border-emerald-300 shadow-xs"
                              title="Lokasi Anda otomatis dikirim ke Dashboard Monitoring HRD selama jam kerja">
                            <span class="w-2 h-2 rounded-full bg-emerald-500 animate-ping"></span>
                            <span>GPS Live</span>
                        </span>
                    </template>
                @endif

                <span class="hidden sm:inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-semibold border shadow-xs"
                      :class="online ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-amber-50 text-amber-700 border-amber-200'">
                    <span class="w-2 h-2 rounded-full" :class="online ? 'bg-emerald-500' : 'bg-amber-500'"></span>
                    <span x-text="online ? 'Online' : 'Offline'">Online</span>
                </span>

                <!-- Notification Bell & Dropdown -->
                <div class="relative" @click.outside="notifOpen = false">
                    <button type="button" @click="notifOpen = !notifOpen"
                            class="relative p-2 rounded-xl bg-white hover:bg-slate-50 border border-slate-200 text-slate-700 shadow-xs transition"
                            title="Notifikasi Penugasan">
                        <x-icon name="bell" class="w-4 h-4" />
                        <template x-if="unreadCount > 0">
                            <span x-text="unreadCount > 9 ? '9+' : unreadCount"
                                  class="absolute -top-1 -right-1 min-w-[18px] h-[18px] px-1 rounded-full bg-orange-600 text-white text-[10px] font-black flex items-center justify-center border-2 border-white"></span>
                        </template>
                    </button>

                    <!-- Dropdown Panel -->
                    <div x-show="notifOpen" x-cloak
                         class="absolute right-0 mt-2 w-80 sm:w-96 rounded-2xl bg-white border border-slate-200 shadow-2xl z-50 overflow-hidden">
                        <div class="p-3.5 bg-slate-50 border-b border-slate-200 flex items-center justify-between">
                            <div>
                                <div class="text-xs font-extrabold text-slate-900">Notifikasi Penugasan</div>
                                <div class="text-[11px] text-slate-500" x-text="unreadCount + ' belum dibaca'"></div>
                            </div>
                            <div class="flex items-center gap-2">
                                <template x-if="pushPermission !== 'granted' && pushPermission !== 'unsupported'">
                                    <button type="button" @click="enablePush()" class="text-[11px] font-bold text-orange-600 hover:underline">
                                        Aktifkan Push
                                    </button>
                                </template>
                                <template x-if="unreadCount > 0">
                                    <button type="button" @click="markAllRead()" class="text-[11px] font-semibold text-slate-600 hover:text-slate-900">
                                        Tandai Dibaca
                                    </button>
                                </template>
                            </div>
                        </div>

                        <div class="max-h-80 overflow-y-auto divide-y divide-slate-100">
                            <template x-for="n in notifications" :key="n.id">
                                <button type="button" @click="openNotification(n)"
                                        class="w-full text-left p-3.5 hover:bg-orange-50/50 transition flex items-start gap-3"
                                        :class="n.is_unread ? 'bg-orange-50/30' : ''">
                                    <span class="w-2.5 h-2.5 rounded-full mt-1.5 flex-none"
                                          :class="n.is_unread ? 'bg-orange-500' : 'bg-slate-300'"></span>
                                    <div class="min-w-0 flex-1">
                                        <div class="text-xs font-extrabold text-slate-900" x-text="n.title"></div>
                                        <div class="text-[11px] text-slate-600 mt-0.5 leading-relaxed" x-text="n.body"></div>
                                        <div class="text-[10px] text-slate-400 mt-1 font-medium" x-text="n.time_ago"></div>
                                    </div>
                                </button>
                            </template>
                            <template x-if="notifications.length === 0">
                                <div class="p-6 text-center text-xs text-slate-500">
                                    Belum ada notifikasi penugasan baru.
                                </div>
                            </template>
                        </div>
                    </div>
                </div>

                <span class="badge badge-orange hidden md:inline-flex">{{ $currentUser->roleLabel() }}</span>

                <a href="{{ route('profile.edit') }}" class="flex items-center gap-2 px-2.5 py-1.5 rounded-xl bg-white hover:bg-slate-50 border border-slate-200 shadow-xs transition">
                    @if($currentUser->avatarUrl())
                        <img src="{{ $currentUser->avatarUrl() }}" alt="{{ $currentUser->name }}" class="w-7 h-7 rounded-full object-cover border border-orange-500">
                    @else
                        <span class="w-7 h-7 rounded-full bg-orange-500 border border-orange-600 flex items-center justify-center text-xs font-black text-white shadow-xs">
                            {{ $currentUser->initials() }}
                        </span>
                    @endif
                    <span class="hidden sm:inline text-xs font-bold text-slate-800 max-w-32 truncate">{{ $currentUser->name }}</span>
                </a>
            </div>
        </header>

        <!-- Page Body -->
        <main class="flex-1 px-4 sm:px-6 pt-5 max-w-7xl w-full mx-auto space-y-6">
            @if(session('success'))
                <div class="glass px-4 py-3 border-emerald-300 bg-emerald-50 text-emerald-900 text-sm flex items-center justify-between gap-3 shadow-sm">
                    <span>{{ session('success') }}</span>
                </div>
            @endif

            @if(session('error'))
                <div class="glass px-4 py-3 border-red-300 bg-red-50 text-red-900 text-sm flex items-center justify-between gap-3 shadow-sm">
                    <span>{{ session('error') }}</span>
                </div>
            @endif

            @if($errors->any())
                <div class="glass px-4 py-3 border-red-300 bg-red-50 text-red-900 text-xs space-y-1 shadow-sm">
                    <div class="font-bold text-sm text-red-800">Periksa kembali input Anda:</div>
                    <ul class="list-disc list-inside">
                        @foreach($errors->all() as $err)
                            <li>{{ $err }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @yield('content')
        </main>
    </div>

    <!-- Mobile 3D Paper Bottom Console -->
    @include('partials.bottom-dock', ['menuItems' => $menuItems])

    <!-- Credential Copy / WhatsApp Modal -->
    @include('partials.credential-modal')

    @if(session('success') || session('error'))
        <script>
            window.hsmReady(() => {
                if (window.HsmSwal) {
                    @if(session('success'))
                        window.HsmSwal.toast(@json(session('success')), 'success');
                    @elseif(session('error'))
                        window.HsmSwal.toast(@json(session('error')), 'error');
                    @endif
                }
            });
        </script>
    @endif

    @stack('scripts')
</body>
</html>
