<x-vendor-layout>
    <x-dashboard.page-header title="Orders" subtitle="Orders assigned to your store." />

    <form method="GET" class="mb-4 flex flex-wrap gap-2">
        <input type="text" name="search" value="{{ request('search') }}" placeholder="Order ID or customer..." class="rounded-lg border-gray-300 text-sm" />
        <select name="status" class="rounded-lg border-gray-300 text-sm">
            <option value="">All statuses</option>
            @foreach(['pending','confirmed','processing','shipped','delivered','cancelled'] as $st)
                <option value="{{ $st }}" @selected(request('status') === $st)>{{ ucfirst($st) }}</option>
            @endforeach
        </select>
        <button type="submit" class="rounded-lg bg-gray-800 px-4 py-2 text-sm text-white">Filter</button>
    </form>

    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Order</th>
                    <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Customer</th>
                    <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Total</th>
                    <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Status</th>
                    <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Date</th>
                    <th class="px-4 py-3 text-right text-xs font-medium uppercase text-gray-500">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse($orders as $mapping)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 text-sm font-medium text-gray-900">#{{ $mapping->order_id }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600">{{ $mapping->order?->user?->name ?? $mapping->order?->guest_full_name ?? 'Guest' }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600">AED {{ number_format($mapping->total_amount, 2) }}</td>
                        <td class="px-4 py-3 text-sm capitalize text-gray-600">{{ $mapping->status }}</td>
                        <td class="px-4 py-3 text-sm text-gray-500">{{ $mapping->created_at?->format('M d, Y') }}</td>
                        <td class="px-4 py-3 text-right text-sm">
                            <a href="{{ route('vendor.orders.show', $mapping->id) }}" class="text-indigo-600 hover:text-indigo-800">View</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-8 text-center text-sm text-gray-500">No orders found.</td></tr>
                @endforelse
            </tbody>
        </table>
        @if($orders->hasPages())
            <div class="border-t border-gray-200 px-4 py-3">{{ $orders->links() }}</div>
        @endif
    </div>
</x-vendor-layout>
