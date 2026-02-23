<x-client-layout>
    <div class="mb-4 sm:mb-6">
        <h1 class="text-lg sm:text-xl font-medium text-gray-900">Memberships</h1>
        <p class="mt-1 text-xs sm:text-sm text-gray-500">Packages created by admin. Same as API GET /api/client/memberships.</p>
    </div>
    @if(session('success'))
        <div class="mb-4 bg-green-50 border-l-4 border-green-400 p-3 rounded-md"><p class="text-sm text-green-700">{{ session('success') }}</p></div>
    @endif
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @forelse($packages as $pkg)
            <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm">
                @if($pkg->image_url)
                    <img src="{{ $pkg->image_url }}" alt="{{ $pkg->name }}" class="w-full h-32 object-cover rounded-lg mb-3">
                @endif
                <h3 class="font-medium text-gray-900">{{ $pkg->name }}</h3>
                <p class="text-sm text-gray-500 mt-1">{{ $pkg->description }}</p>
                <p class="mt-2 text-sm font-medium text-indigo-600">AED {{ number_format($pkg->price, 2) }}</p>
            </div>
        @empty
            <div class="col-span-full p-8 text-center bg-white rounded-xl border border-gray-200">
                <p class="text-gray-500">No memberships available yet.</p>
            </div>
        @endforelse
    </div>
</x-client-layout>
