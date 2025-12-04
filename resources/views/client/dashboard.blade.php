<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Client Dashboard') }}
        </h2>
    </x-slot>

    <div class="py-12 max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white shadow rounded-lg p-6">
            <h3 class="text-lg font-medium mb-4">Welcome, Client!</h3>
            <p>Manage your subscriptions, track orders, and view reports here.</p>
            <!-- Add client-specific content -->
        </div>
    </div>
</x-app-layout>
