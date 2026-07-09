<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Tandil - Vendor Dashboard</title>
    <script>
        if (document.title !== 'Tandil - Vendor Dashboard') {
            document.title = 'Tandil - Vendor Dashboard';
        }
    </script>
    <link rel="icon" type="image/png" href="{{ asset('images/logo.png') }}?v={{ time() }}">
    <link rel="shortcut icon" type="image/png" href="{{ asset('images/logo.png') }}?v={{ time() }}">
    <link rel="apple-touch-icon" href="{{ asset('images/logo.png') }}?v={{ time() }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>html, body { font-size: 14px !important; }</style>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    @stack('styles')
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
        @include('components.vendor.sidebar')

        <div class="relative flex w-full max-[991px]:ml-0 min-[992px]:ml-[250px] flex-1 flex-col overflow-hidden">
            @include('components.vendor.header')

            <main class="flex-1 min-w-0 max-[991px]:pl-0 min-[992px]:pl-10 overflow-y-auto">
                <div class="w-full px-3 py-3 sm:px-4 sm:py-4 md:px-6 md:py-6 2xl:px-8 2xl:py-8" style="max-width: 100%; margin-left: auto; margin-right: auto;">
                    @if(session('success'))
                        <div class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('success') }}</div>
                    @endif
                    @if(session('error'))
                        <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ session('error') }}</div>
                    @endif
                    @if(isset($errors) && $errors->any())
                        <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                            <ul class="list-disc space-y-1 pl-4">
                                @foreach($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                    {{ $slot }}
                </div>
            </main>
        </div>
    </div>
    <x-toast-notifications />
    <x-live-chat-widget />
    @stack('scripts')
</body>
</html>
