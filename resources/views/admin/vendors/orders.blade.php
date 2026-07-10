<x-admin-layout>
    <div class="space-y-6">
        <x-admin.vendor.nav :vendor="$vendor" />
        <div>
            <h1 class="text-2xl font-semibold">Orders — {{ $vendor->profile?->business_name }}</h1>
            <p class="text-sm text-gray-500">Track fulfillment, payments, and commissions.</p>
        </div>

        <form method="GET" class="flex flex-wrap gap-2 rounded-2xl border bg-white/80 p-4 backdrop-blur dark:bg-gray-900/70">
            <input name="search" value="{{ request('search') }}" placeholder="Order ID or tracking" class="rounded-xl border-gray-300 text-sm" />
            <select name="status" class="rounded-xl border-gray-300 text-sm">
                <option value="">All statuses</option>
                @foreach(\App\Enums\VendorOrderStatus::cases() as $status)
                    <option value="{{ $status->value }}" @selected(request('status')===$status->value)>{{ $status->label() }}</option>
                @endforeach
            </select>
            <button class="rounded-xl bg-gray-900 px-4 py-2 text-sm font-medium text-white">Filter</button>
        </form>

        <div class="overflow-hidden rounded-2xl border bg-white/80 shadow-sm backdrop-blur dark:bg-gray-900/70">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y text-sm dark:divide-gray-800">
                    <thead class="bg-gray-50 dark:bg-gray-900/50">
                        <tr>
                            <th class="px-4 py-3 text-left">Order ID</th>
                            <th class="px-4 py-3 text-left">Customer</th>
                            <th class="px-4 py-3 text-left">Delivery Status</th>
                            <th class="px-4 py-3 text-right">Order Value</th>
                            <th class="px-4 py-3 text-right">Commission</th>
                            <th class="px-4 py-3 text-left">Payment</th>
                            <th class="px-4 py-3 text-left">Date</th>
                            <th class="px-4 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y dark:divide-gray-800">
                        @forelse($orders as $mapping)
                            <tr class="hover:bg-indigo-50/30">
                                <td class="px-4 py-3 font-medium text-indigo-600">#{{ $mapping->order_id }}</td>
                                <td class="px-4 py-3">{{ $mapping->order?->user?->name ?? $mapping->order?->guest_full_name ?? 'Guest' }}</td>
                                <td class="px-4 py-3"><x-admin.vendor.status-badge :status="$mapping->status" /></td>
                                <td class="px-4 py-3 text-right font-semibold">AED {{ number_format($mapping->total_amount, 2) }}</td>
                                <td class="px-4 py-3 text-right">AED {{ number_format($mapping->commission_amount ?? 0, 2) }}</td>
                                <td class="px-4 py-3 capitalize">{{ $mapping->order?->payment_status ?? '—' }}</td>
                                <td class="px-4 py-3 text-gray-600">{{ $mapping->created_at?->format('M j, Y') }}</td>
                                <td class="px-4 py-3 text-right">
                                    <a href="{{ route('admin.marketplace.orders.show', $mapping) }}" class="text-indigo-600 hover:underline">View</a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="px-4 py-12 text-center text-gray-500">No orders found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        {{ $orders->links() }}
    </div>
</x-admin-layout>
