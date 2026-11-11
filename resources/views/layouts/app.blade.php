@php
    $currentUser = auth()->user();
    $menuItems = \App\Support\Menu::for($currentUser);
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
    toggleSidebar() {
        this.sidebarCollapsed = !this.sidebarCollapsed;
        localStorage.setItem('hsm_side', this.sidebarCollapsed ? '1' : '0');
    }
}" @online.window="online = true" @offline.window="online = false">

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

            <div class="flex items-center gap-2.5">
                <span class="hidden sm:inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-semibold border shadow-xs"
                      :class="online ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-amber-50 text-amber-700 border-amber-200'">
                    <span class="w-2 h-2 rounded-full" :class="online ? 'bg-emerald-500' : 'bg-amber-500'"></span>
                    <span x-text="online ? 'Online' : 'Offline'">Online</span>
                </span>

                <span class="badge badge-orange hidden md:inline-flex">{{ $currentUser->roleLabel() }}</span>

                <a href="{{ route('profile.edit') }}" class="flex items-center gap-2 px-2.5 py-1.5 rounded-xl bg-white hover:bg-slate-50 border border-slate-200 shadow-xs transition">
                    <span class="w-7 h-7 rounded-full bg-orange-500 border border-orange-600 flex items-center justify-center text-xs font-black text-white shadow-xs">
                        {{ $currentUser->initials() }}
                    </span>
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
