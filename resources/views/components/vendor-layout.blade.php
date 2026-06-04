<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Tandil - Vendor Dashboard' }}</title>
    <link rel="icon" type="image/png" href="{{ asset('images/logo.png') }}?v={{ time() }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>html, body { font-size: 14px !important; }</style>
    @stack('styles')
</head>
<body class="bg-gray-50 font-sans overflow-x-hidden text-gray-900 antialiased" style="font-family: 'Inter', sans-serif;">
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

        <div class="relative flex flex-1 flex-col overflow-y-auto overflow-x-hidden w-full max-[991px]:ml-0 min-[992px]:ml-[250px] scroll-smooth">
            @include('components.vendor.header')

            <main class="flex-1 min-w-0 max-[991px]:pl-0 min-[992px]:pl-10 bg-gray-50">
                <div class="w-full px-4 py-4 sm:px-5 sm:py-5 md:px-6 md:py-6 lg:px-8 lg:py-8" style="max-width: 1600px; margin-left: auto; margin-right: auto;">
                    @if(session('success'))
                        <div class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('success') }}</div>
                    @endif
                    @if(session('error'))
                        <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ session('error') }}</div>
                    @endif
                    @if(isset($errors) && $errors->any())
                        <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                            <ul class="list-disc pl-4 space-y-1">
                                @foreach($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                    {{ $slot }}
                </div>
            </main>

            @include('components.vendor.footer')
        </div>
    </div>
    <x-toast-notifications />
    @stack('scripts')
</body>
</html>
