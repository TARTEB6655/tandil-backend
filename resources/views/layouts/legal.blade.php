<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="index, follow">
    <title>@yield('title', 'Tandil') — Tandil</title>
    <link rel="icon" type="image/png" href="{{ asset('images/logo.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @include('legal.partials.legal-prose-styles')
    <style>
        .legal-page {
            font-family: 'Inter', ui-sans-serif, system-ui, sans-serif;
            font-size: 16px;
            background: #f3f4f6;
        }
    </style>
</head>
<body class="legal-page min-h-screen text-gray-900 antialiased">
    <x-public.admin-style-header />

    <main class="mx-auto max-w-3xl px-4 py-8 sm:px-6 sm:py-10 lg:py-12">
        @yield('content')
    </main>

    <footer class="border-t border-gray-200 bg-white py-6 text-center text-base text-gray-500" style="font-size: 16px;">
        <p>&copy; {{ date('Y') }} Tandil. All rights reserved.</p>
    </footer>
</body>
</html>
