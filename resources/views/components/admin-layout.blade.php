@php
    $locale = app()->getLocale();
    $isRtl = in_array($locale, ['ar', 'ur'], true);
    $htmlLang = $locale === 'ur' ? 'ur' : str_replace('_', '-', $locale);
@endphp
<!DOCTYPE html>
<html lang="{{ $htmlLang }}" dir="{{ $isRtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Tandil') }} - {{ __('admin.dashboard') }}</title>

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
    <style>
        html, body, body * {
            font-size: 12px !important;
        }
    </style>
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
</head>
<body class="bg-gray-100 font-sans overflow-x-hidden text-gray-900 antialiased" style="font-family: 'Inter', sans-serif;">
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
            <main class="flex-1 min-w-0 max-[991px]:pl-0 min-[992px]:pl-10 bg-gray-100/80">
                <div class="w-full px-4 py-4 sm:px-5 sm:py-5 md:px-6 md:py-6 lg:px-8 lg:py-8 2xl:px-10 2xl:py-8 text-gray-900" style="max-width: 1600px; margin-left: auto; margin-right: auto;">
                    {{ $slot }}
                </div>
            </main>
        </div>
    </div>
    
    <!-- Toast Notifications -->
    <x-toast-notifications />

    @stack('scripts')
</body>
</html>

