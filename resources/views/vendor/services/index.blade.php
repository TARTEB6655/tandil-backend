<x-vendor-layout>
    <x-dashboard.page-header title="Services" subtitle="Manage services linked to your products.">
        <x-slot:actions>
            <a href="{{ route('vendor.services.create') }}" class="inline-flex items-center justify-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-700">Add Service</a>
        </x-slot:actions>
    </x-dashboard.page-header>

    @if(session('success'))<div class="mb-4 rounded-lg bg-green-50 p-3 text-sm text-green-800">{{ session('success') }}</div>@endif

    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Name</th>
                    <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Category</th>
                    <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Status</th>
                    <th class="px-4 py-3 text-right text-xs font-medium uppercase text-gray-500">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse($services as $service)
                    <tr>
                        <td class="px-4 py-3 text-sm font-medium text-gray-900">{{ $service->name }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600">{{ $service->category?->name ?? '—' }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600">{{ $service->is_active ? 'Active' : 'Inactive' }}</td>
                        <td class="px-4 py-3 text-right text-sm">
                            <a href="{{ route('vendor.services.edit', $service) }}" class="text-indigo-600 hover:text-indigo-800">Edit</a>
                            <form method="POST" action="{{ route('vendor.services.destroy', $service) }}" class="inline" onsubmit="return confirm('Delete this service?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="ml-3 text-red-600 hover:text-red-800">Delete</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-4 py-8 text-center text-sm text-gray-500">No services yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $services->links() }}</div>
</x-vendor-layout>
