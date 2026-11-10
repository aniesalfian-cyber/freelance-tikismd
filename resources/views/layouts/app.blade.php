@php
    $currentUser = auth()->user();
    $menuItems = \App\Support\Menu::for($currentUser);
@endphp
<!DOCTYPE html>
<html lang="id" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#08101C">
    <link rel="manifest" href="/manifest.webmanifest">
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="apple-touch-icon" href="/icons/icon-192.svg">
    <title>{{ isset($title) ? $title.' — ' : '' }}{{ config('app.name', 'Monitoring Freelancer HSM') }}</title>
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
    toggleSidebar() {
        this.sidebarCollapsed = !this.sidebarCollapsed;
        localStorage.setItem('hsm_side', this.sidebarCollapsed ? '1' : '0');
    }
}" @online.window="online = true" @offline.window="online = false">

    <!-- Luxury Desktop/Tablet Sidebar -->
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
                    <h1 class="text-sm sm:text-base font-extrabold text-white truncate">{{ $title ?? 'Monitoring Freelancer HSM' }}</h1>
                    <p class="text-[11px] text-slate-400 truncate">{{ $subtitle ?? now()->translatedFormat('l, d F Y') }}</p>
                </div>
            </div>

            <div class="flex items-center gap-2.5">
                <span class="hidden sm:inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-semibold border"
                      :class="online ? 'bg-emerald-500/10 text-emerald-300 border-emerald-500/30' : 'bg-amber-500/15 text-amber-300 border-amber-500/30'">
                    <span class="w-2 h-2 rounded-full" :class="online ? 'bg-emerald-400' : 'bg-amber-400'"></span>
                    <span x-text="online ? 'Online' : 'Offline'">Online</span>
                </span>

                <span class="badge badge-orange hidden md:inline-flex">{{ $currentUser->roleLabel() }}</span>

                <a href="{{ route('profile.edit') }}" class="flex items-center gap-2 px-2.5 py-1.5 rounded-xl bg-white/[0.04] hover:bg-white/[0.08] border border-white/10 transition">
                    <span class="w-7 h-7 rounded-full bg-orange-500/25 border border-orange-400/40 flex items-center justify-center text-xs font-extrabold text-orange-300">
                        {{ $currentUser->initials() }}
                    </span>
                    <span class="hidden sm:inline text-xs font-semibold text-white max-w-32 truncate">{{ $currentUser->name }}</span>
                </a>
            </div>
        </header>

        <!-- Page Body -->
        <main class="flex-1 px-4 sm:px-6 pt-5 max-w-7xl w-full mx-auto space-y-6">
            @if(session('success'))
                <div class="glass px-4 py-3 border-emerald-500/40 bg-emerald-500/10 text-emerald-200 text-sm flex items-center justify-between gap-3">
                    <span>{{ session('success') }}</span>
                </div>
            @endif

            @if(session('error'))
                <div class="glass px-4 py-3 border-red-500/40 bg-red-500/10 text-red-200 text-sm flex items-center justify-between gap-3">
                    <span>{{ session('error') }}</span>
                </div>
            @endif

            @if($errors->any())
                <div class="glass px-4 py-3 border-red-500/40 bg-red-500/10 text-red-200 text-xs space-y-1">
                    <div class="font-bold text-sm text-red-300">Periksa kembali input Anda:</div>
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

    <!-- Mobile Floating Water-Bubble Bottom Dock -->
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
