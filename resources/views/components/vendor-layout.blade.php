<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Tandil - Vendor Dashboard</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>html, body { font-size: 14px !important; }</style>
    @stack('styles')
</head>
<body class="bg-gray-50 font-sans overflow-x-hidden" style="font-family: 'Inter', sans-serif;">
    <div class="flex h-screen overflow-hidden" x-data x-init="document.addEventListener('alpine:init', () => { if (window.Alpine?.store('sidebar')) $store.sidebar.init(); })">
        <x-vendor.sidebar />
        <div class="flex flex-col flex-1 min-w-0 lg:pl-[250px]">
            <x-vendor.header />
            <main class="flex-1 overflow-y-auto p-4 sm:p-6 lg:p-8">
                {{ $slot }}
            </main>
        </div>
    </div>
    <x-toast-notifications />
    @stack('scripts')
</body>
</html>
