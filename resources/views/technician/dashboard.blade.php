<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Technician Dashboard') }}
        </h2>
    </x-slot>

    <div class="py-12 max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white shadow rounded-lg p-6">
            <h3 class="text-lg font-medium mb-4">Welcome, Technician!</h3>
            <p>View your assigned jobs, upload visit notes and before/after photos.</p>
            <!-- Add technician-specific tools -->
        </div>
    </div>
</x-app-layout>
