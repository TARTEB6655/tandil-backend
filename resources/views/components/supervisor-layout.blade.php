<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>Tandil - Supervisor Dashboard</title>
    <script>
        // Force title update to prevent browser cache issues
        if (document.title !== 'Tandil - Supervisor Dashboard') {
            document.title = 'Tandil - Supervisor Dashboard';
        }
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
<body class="bg-gray-50 font-sans overflow-x-hidden" style="font-family: 'Inter', sans-serif;">
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
        @include('components.supervisor.sidebar')

        <!-- Main Content Area -->
        <div class="relative flex flex-1 flex-col overflow-hidden w-full max-[991px]:ml-0 min-[992px]:ml-[250px]">
            <!-- Header -->
            @include('components.supervisor.header')

            <!-- Main Content -->
            <main class="flex-1 min-w-0 max-[991px]:pl-0 min-[992px]:pl-10 overflow-y-auto">
                <div class="w-full px-3 py-3 sm:px-4 sm:py-4 md:px-6 md:py-6 2xl:px-8 2xl:py-8" style="max-width: 100%; margin-left: auto; margin-right: auto;">
                    {{ $slot }}
                </div>
            </main>
        </div>
    </div>
</body>
</html>

