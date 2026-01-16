<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>404 - Page Not Found | {{ config('app.name', 'Tandil') }}</title>

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
</head>
<body class="bg-gray-50 font-sans antialiased" style="font-family: 'Inter', sans-serif;">
    <div class="min-h-screen flex flex-col items-center justify-center px-4 py-12">
        <div class="max-w-md w-full text-center">
            <!-- Logo -->
            <div class="mb-8 flex justify-center">
                <img src="{{ asset('images/logo.png') }}" alt="{{ config('app.name', 'Tandil') }}" style="width: 100px; height: auto;">
            </div>

            <!-- 404 Illustration -->
            <div class="mb-8">
                <svg class="mx-auto h-48 w-48 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </div>

            <!-- Error Message -->
            <div class="mb-8">
                <h1 class="text-6xl font-bold text-gray-900 mb-4">404</h1>
                <h2 class="text-2xl font-semibold text-gray-800 mb-3">Page Not Found</h2>
                <p class="text-gray-600 text-lg">
                    Sorry, the page you are looking for doesn't exist or has been moved.
                </p>
            </div>

            <!-- Action Buttons -->
            <div class="flex flex-col sm:flex-row gap-4 justify-center items-center">
                <a href="{{ route('login') }}" 
                   class="inline-flex items-center px-6 py-3 text-white font-medium rounded-lg shadow-sm transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-offset-2"
                   style="background-color: #1d4c28;"
                   onmouseover="this.style.backgroundColor='#1a4323'"
                   onmouseout="this.style.backgroundColor='#1d4c28'"
                   onfocus="this.style.boxShadow='0 0 0 3px rgba(29, 76, 40, 0.3)'"
                   onblur="this.style.boxShadow=''">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                    </svg>
                    Go to Login
                </a>
                
                <button onclick="window.history.back()" 
                        class="inline-flex items-center px-6 py-3 font-medium rounded-lg border shadow-sm transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-offset-2"
                        style="background-color: #6b5137; border-color: #6b5137; color: white;"
                        onmouseover="this.style.backgroundColor='#5a4530'; this.style.borderColor='#5a4530'"
                        onmouseout="this.style.backgroundColor='#6b5137'; this.style.borderColor='#6b5137'"
                        onfocus="this.style.boxShadow='0 0 0 3px rgba(107, 81, 55, 0.3)'"
                        onblur="this.style.boxShadow=''">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                    Go Back
                </button>
            </div>

            <!-- Additional Help Text -->
            <div class="mt-8 pt-8 border-t border-gray-200">
                <p class="text-sm text-gray-500">
                    If you believe this is an error, please contact support.
                </p>
            </div>
        </div>
    </div>
</body>
</html>

