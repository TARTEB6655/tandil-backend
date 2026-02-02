@php
    $appTheme = trim((string) (\App\Models\Setting::get('app_theme', 'system')));
    if (!in_array($appTheme, ['dark', 'light', 'system'], true)) {
        $appTheme = 'system';
    }
    $htmlThemeClass = $appTheme === 'dark' ? 'dark' : ($appTheme === 'light' ? '' : '');
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="{{ $htmlThemeClass }}" data-theme="{{ $appTheme }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="app-theme" content="{{ $appTheme }}">

    <title>{{ config('app.name', 'Tandil') }} - Admin Dashboard</title>
    <script>
        (function(){
            var m = document.querySelector('meta[name="app-theme"]');
            var theme = m ? m.getAttribute('content') : '';
            if (theme === 'dark') document.documentElement.classList.add('dark');
            else if (theme === 'light') document.documentElement.classList.remove('dark');
        })();
    </script>

    <!-- Favicon with cache busting -->
    <link rel="icon" type="image/png" href="{{ asset('images/logo.png') }}?v={{ time() }}">
    <link rel="shortcut icon" type="image/png" href="{{ asset('images/logo.png') }}?v={{ time() }}">
    <link rel="apple-touch-icon" href="{{ asset('images/logo.png') }}?v={{ time() }}">

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
</head>
<body class="bg-gray-50 dark:bg-gray-950 font-sans overflow-x-hidden text-gray-900 dark:text-gray-100 transition-colors" style="font-family: 'Inter', sans-serif;">
    <div class="flex h-screen overflow-hidden" 
         x-data 
         x-init="
            if (window.Alpine && window.Alpine.store('sidebar')) {
                $store.sidebar.init();
            } else {
                document.addEventListener('alpine:init', () => {
                    if (window.Alpine && window.Alpine.store('sidebar')) {
                        $store.sidebar.init();
                    }
                });
            }
         ">
        
        <!-- Sidebar -->
        @include('components.admin.sidebar')

        <!-- Main Content Area -->
        <div class="relative flex flex-1 flex-col overflow-y-auto overflow-x-hidden w-full max-[991px]:ml-0 min-[992px]:ml-[250px] scroll-smooth">
            <!-- Header -->
            @include('components.admin.header')

            <!-- Main Content -->
            <main class="flex-1 min-w-0 max-[991px]:pl-0 min-[992px]:pl-10 bg-gray-50 dark:bg-gray-950">
                <div class="w-full px-3 py-3 sm:px-4 sm:py-4 md:px-6 md:py-6 2xl:px-8 2xl:py-8 text-gray-900 dark:text-gray-100" style="max-width: 95%; margin-left: auto; margin-right: auto;">
                    {{ $slot }}
                </div>
            </main>
        </div>
    </div>
    
    <!-- Toast Notifications -->
    <x-toast-notifications />

    <!-- Apply theme so dashboard colors match Theme Settings (dark / light / system) -->
    <script>
        (function() {
            var el = document.documentElement;
            var theme = (el.getAttribute('data-theme') || 'system').toLowerCase();
            function apply() {
                if (theme === 'dark') {
                    el.classList.add('dark');
                } else if (theme === 'light') {
                    el.classList.remove('dark');
                } else {
                    var dark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
                    if (dark) el.classList.add('dark'); else el.classList.remove('dark');
                }
            }
            apply();
            if (theme === 'system' && window.matchMedia) {
                window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', apply);
            }
        })();
    </script>
</body>
</html>

