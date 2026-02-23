<x-client-layout>
    <div class="mb-4 sm:mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
            <h1 class="text-lg sm:text-xl font-medium text-gray-900">Addresses</h1>
            <p class="mt-1 text-xs sm:text-sm text-gray-500">Manage your saved addresses. Same as API /api/user/addresses.</p>
        </div>
        <a href="{{ route('client.addresses.create') }}" class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700">Add Address</a>
    </div>
    @if(session('success'))
        <div class="mb-4 bg-green-50 border-l-4 border-green-400 p-3 rounded-md"><p class="text-sm text-green-700">{{ session('success') }}</p></div>
    @endif
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm divide-y divide-gray-200">
        @forelse($addresses as $addr)
            <div class="p-4 flex flex-wrap items-start justify-between gap-3">
                <div>
                    <p class="font-medium text-gray-900">{{ $addr->full_name }}</p>
                    <p class="text-sm text-gray-600">{{ $addr->street_address }}, {{ $addr->city }}{{ $addr->state ? ', ' . $addr->state : '' }} {{ $addr->zip_code }} {{ $addr->country }}</p>
                    <p class="text-sm text-gray-500">{{ $addr->phone_number }}</p>
                    @if($addr->is_default)
                        <span class="inline-block mt-1 px-2 py-0.5 text-xs font-medium bg-indigo-100 text-indigo-800 rounded">Default</span>
                    @endif
                </div>
                <div class="flex gap-2">
                    <a href="{{ route('client.addresses.edit', $addr->id) }}" class="text-sm text-indigo-600 hover:text-indigo-900">Edit</a>
                    <form action="{{ route('client.addresses.destroy', $addr->id) }}" method="POST" class="inline" onsubmit="return confirm('Remove this address?');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="text-sm text-red-600 hover:text-red-900">Remove</button>
                    </form>
                </div>
            </div>
        @empty
            <div class="p-8 text-center text-gray-500">
                <p>No addresses yet.</p>
                <a href="{{ route('client.addresses.create') }}" class="mt-2 inline-block text-indigo-600 hover:text-indigo-900 text-sm font-medium">Add your first address</a>
            </div>
        @endforelse
    </div>
</x-client-layout>
