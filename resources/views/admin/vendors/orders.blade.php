@php
    $pageRevenue = $orders->sum('total_amount');
    $pageCommission = $orders->sum('commission_amount');
@endphp

<x-admin-layout>
    <x-admin.vendor.shell>
        <x-admin.vendor.nav :vendor="$vendor" />

        <x-admin.vendor.page-header
            :title="'Orders — '.$vendor->profile?->business_name"
            description="Track fulfillment, payments, and commissions for this vendor." />

        <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
            <x-admin.vendor.stat-card label="Total Orders" :value="number_format($orders->total())" />
            <x-admin.vendor.stat-card label="On This Page" :value="number_format($orders->count())" hint="Current results page" />
            <x-admin.vendor.stat-card label="Page Revenue" :value="'AED '.number_format($pageRevenue, 2)" accent="text-emerald-600" />
            <x-admin.vendor.stat-card label="Page Commission" :value="'AED '.number_format($pageCommission, 2)" accent="text-indigo-600" />
        </div>

        <x-admin.vendor.card :padding="false">
            <form method="GET" class="flex flex-col gap-4 border-b border-gray-100 p-4 dark:border-gray-800 sm:flex-row sm:items-center">
                <div class="relative flex-1">
                    <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    <input name="search" value="{{ request('search') }}" placeholder="Search order ID or tracking..."
                           class="w-full rounded-md border-gray-300 py-2 pl-10 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500/40 dark:border-gray-700 dark:bg-gray-900" />
                </div>
                <select name="status" class="rounded-md border-gray-300 text-sm shadow-sm dark:border-gray-700 dark:bg-gray-900 sm:w-48">
                    <option value="">All statuses</option>
                    @foreach(\App\Enums\VendorOrderStatus::cases() as $status)
                        <option value="{{ $status->value }}" @selected(request('status')===$status->value)>{{ $status->label() }}</option>
                    @endforeach
                </select>
                <x-admin.vendor.btn variant="primary" type="submit">Filter</x-admin.vendor.btn>
            </form>

            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="border-b border-gray-200 bg-gray-50/80 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:border-gray-800 dark:bg-gray-900/50">
                        <tr>
                            <th class="px-4 py-3">Order</th>
                            <th class="px-4 py-3">Customer</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3">Payment</th>
                            <th class="px-4 py-3 text-right">Value</th>
                            <th class="px-4 py-3 text-right">Commission</th>
                            <th class="px-4 py-3">Date</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse($orders as $mapping)
                            <tr class="transition-colors hover:bg-gray-50/80 dark:hover:bg-gray-800/40">
                                <td class="px-4 py-4">
                                    <div class="flex items-center gap-3">
                                        <div class="flex h-8 w-8 items-center justify-center rounded-md bg-indigo-50 text-xs font-semibold text-indigo-600 dark:bg-indigo-950/40">
                                            #
                                        </div>
                                        <span class="font-medium text-gray-900 dark:text-gray-100">{{ $mapping->order_id }}</span>
                                    </div>
                                </td>
                                <td class="px-4 py-4">
                                    <p class="font-medium text-gray-900 dark:text-gray-100">{{ $mapping->order?->user?->name ?? $mapping->order?->guest_full_name ?? 'Guest' }}</p>
                                    <p class="text-xs text-gray-500">{{ $mapping->order?->user?->email ?? '—' }}</p>
                                </td>
                                <td class="px-4 py-4"><x-admin.vendor.status-badge :status="$mapping->status" /></td>
                                <td class="px-4 py-4">
                                    <span class="inline-flex items-center rounded-md bg-gray-100 px-2 py-0.5 text-xs font-medium capitalize text-gray-700 dark:bg-gray-800 dark:text-gray-300">
                                        {{ $mapping->order?->payment_status ?? '—' }}
                                    </span>
                                </td>
                                <td class="px-4 py-4 text-right tabular-nums font-medium text-gray-900 dark:text-gray-100">AED {{ number_format($mapping->total_amount, 2) }}</td>
                                <td class="px-4 py-4 text-right tabular-nums text-indigo-600">AED {{ number_format($mapping->commission_amount ?? 0, 2) }}</td>
                                <td class="px-4 py-4 text-xs text-gray-500">{{ $mapping->created_at?->format('M j, Y') }}</td>
                                <td class="px-4 py-4 text-right">
                                    <x-admin.vendor.btn variant="ghost" :href="route('admin.marketplace.orders.show', $mapping)">View</x-admin.vendor.btn>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="p-0">
                                    <x-admin.vendor.empty-state title="No orders found" description="This vendor has no orders matching your filters." />
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($orders->hasPages())
                <div class="border-t border-gray-200 px-4 py-3 dark:border-gray-800">{{ $orders->links() }}</div>
            @endif
        </x-admin.vendor.card>
    </x-admin.vendor.shell>
</x-admin-layout>
