<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="index, follow">
    <title>@yield('title', 'Tandil') — Tandil</title>
    <link rel="icon" type="image/png" href="{{ asset('images/logo.png') }}">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        .legal-page { font-family: Figtree, ui-sans-serif, system-ui, sans-serif; font-size: 16px; }
        .legal-prose h1 { font-size: 2.25rem; line-height: 1.25; font-weight: 700; color: #0f172a; }
        .legal-prose h2 { font-size: 1.375rem; line-height: 1.35; font-weight: 700; color: #0f172a; margin: 1.75rem 0 0.65rem; }
        .legal-prose p, .legal-prose li { color: #334155; line-height: 1.7; font-size: 16px; }
        .legal-prose ul { margin: 0.5rem 0 1rem 1.25rem; list-style: disc; }
        .legal-prose li { margin-bottom: 0.4rem; }
        @media (min-width: 640px) {
            .legal-prose h1 { font-size: 2.5rem; }
            .legal-prose h2 { font-size: 1.5rem; }
        }
    </style>
</head>
<body class="legal-page min-h-screen bg-slate-50 text-slate-900 antialiased">
    <header class="border-b border-slate-200 bg-white">
        <div class="mx-auto flex max-w-3xl items-center justify-between gap-4 px-4 py-4 sm:px-6">
            <a href="{{ url('/') }}" class="flex items-center gap-3">
                <img src="{{ asset('images/logo.png') }}" alt="Tandil" class="h-10 w-10 rounded-lg object-contain">
                <span class="text-lg font-bold text-slate-900">Tandil</span>
            </a>
            <a href="{{ url('/') }}" class="text-sm font-medium text-emerald-800 hover:text-emerald-900">Home</a>
        </div>
    </header>

    <main class="mx-auto max-w-3xl px-4 py-10 sm:px-6 sm:py-12">
        @yield('content')
    </main>

    <footer class="border-t border-slate-200 bg-white py-6 text-center text-base text-slate-500" style="font-size: 16px;">
        <p>&copy; {{ date('Y') }} Tandil. All rights reserved.</p>
        <p class="mt-1">
            <a href="https://tandil.ae" class="text-emerald-800 hover:underline">tandil.ae</a>
            &middot;
            <a href="mailto:info@tandil.ae" class="text-emerald-800 hover:underline">info@tandil.ae</a>
        </p>
    </footer>
</body>
</html>
