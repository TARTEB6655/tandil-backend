<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Vendor — Pending Approval</title>
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-screen flex items-center justify-center bg-gray-50 p-6">
    <div class="max-w-md w-full bg-white rounded-xl border border-gray-200 p-8 text-center shadow-sm">
        <h1 class="text-xl font-semibold text-gray-900">Account pending approval</h1>
        <p class="text-sm text-gray-600 mt-3">Your vendor registration is being reviewed by our team. You will get access to the vendor dashboard once approved.</p>
        <p class="text-sm text-gray-500 mt-2">Status: <strong>{{ auth()->user()->vendor?->status ?? 'pending' }}</strong></p>
        <form method="POST" action="{{ route('logout') }}" class="mt-6">
            @csrf
            <button type="submit" class="text-sm text-indigo-600 hover:underline">Logout</button>
        </form>
    </div>
</body>
</html>
