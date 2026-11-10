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
    <title>{{ $title ?? 'Login' }} — {{ config('app.name', 'Monitoring Karyawan HSM') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-dvh flex flex-col justify-center items-center p-4 sm:p-6">
    @yield('content')
</body>
</html>
