<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Tandil') }} - Admin</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet" />

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans antialiased bg-gray-50" style="font-family: 'Inter', sans-serif;">
    @auth
        @if(auth()->user()->role === 'admin')
            <div x-data="{ sidebarOpen: false }"
                 x-init="
                    if (window.innerWidth >= 1024) { sidebarOpen = true; }
                    window.addEventListener('resize', () => {
                        if (window.innerWidth >= 1024) { sidebarOpen = true; }
                        else { sidebarOpen = false; }
                    });
                 ">
                <!-- Topbar -->
                @include('partials.topbar')

                <!-- Sidebar -->
                @include('partials.sidebar')

                <!-- Main Content -->
                <main class="ml-0 lg:ml-64 mt-16 p-4 lg:p-6">
                    <div class="max-w-7xl mx-auto px-6">
                        {{ $slot }}
                    </div>
                </main>
            </div>
        @else
            @include('layouts.navigation')
            <main class="p-6">
                <div class="max-w-7xl mx-auto px-6">
                    {{ $slot }}
                </div>
            </main>
        @endif
    @else
        @include('layouts.navigation')
        <main class="p-6">
            <div class="max-w-7xl mx-auto px-6">
                {{ $slot }}
            </div>
        </main>
    @endauth

    <script>
        function toggleSidebar() {
            window.dispatchEvent(new CustomEvent('toggle-sidebar'));
        }
    </script>
</body>
</html>
