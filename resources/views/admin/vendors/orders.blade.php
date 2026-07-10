<x-admin-layout>
    <x-admin.vendor.shell>
        <x-admin.vendor.nav :vendor="$vendor" />
        <x-admin.vendor.flash />

        <x-admin.vendor.page-header
            :title="'Orders — '.$vendor->profile?->business_name"
            description="Monitor fulfillment, payments, commissions, and customer details for this vendor.">
            <x-slot:actions>
                <x-admin.vendor.btn variant="secondary" :href="route('admin.vendors.orders.export', array_merge(['vendor' => $vendor], request()->only(['search','status','payment_status','date_from','date_to','sort'])))">
                    Export CSV
                </x-admin.vendor.btn>
            </x-slot:actions>
        </x-admin.vendor.page-header>

        <div class="grid grid-cols-2 gap-4 md:grid-cols-3 xl:grid-cols-5">
            <x-admin.vendor.stat-card label="Total Orders" :value="number_format($stats['total'] ?? 0)" />
            <x-admin.vendor.stat-card label="Pending" :value="number_format($stats['pending'] ?? 0)" accent="text-amber-600" />
            <x-admin.vendor.stat-card label="Processing" :value="number_format($stats['processing'] ?? 0)" accent="text-sky-600" />
            <x-admin.vendor.stat-card label="Delivered" :value="number_format($stats['delivered'] ?? 0)" accent="text-emerald-600" />
            <x-admin.vendor.stat-card label="Cancelled" :value="number_format($stats['cancelled'] ?? 0)" accent="text-rose-600" />
        </div>
        <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
            <x-admin.vendor.stat-card label="Refunded" :value="number_format($stats['refunded'] ?? 0)" />
            <x-admin.vendor.stat-card label="Total Revenue" :value="'AED '.number_format($stats['total_revenue'] ?? 0, 2)" accent="text-emerald-600" />
            <x-admin.vendor.stat-card label="Vendor Earnings" :value="'AED '.number_format($stats['vendor_earnings'] ?? 0, 2)" accent="text-indigo-600" />
            <x-admin.vendor.stat-card label="Platform Commission" :value="'AED '.number_format($stats['platform_commission'] ?? 0, 2)" />
        </div>

        <x-admin.vendor.card :padding="false">
            <form method="GET" class="grid gap-4 border-b border-gray-100 p-4 dark:border-gray-800 lg:grid-cols-6">
                <div class="relative lg:col-span-2">
                    <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    <input name="search" value="{{ request('search') }}" placeholder="Order ID, customer, email..." class="w-full rounded-md border-gray-300 py-2 pl-10 text-sm dark:border-gray-700 dark:bg-gray-900" />
                </div>
                <select name="status" class="rounded-md border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-900">
                    <option value="">All statuses</option>
                    @foreach(\App\Enums\VendorOrderStatus::cases() as $status)
                        <option value="{{ $status->value }}" @selected(request('status')===$status->value)>{{ $status->label() }}</option>
                    @endforeach
                </select>
                <select name="payment_status" class="rounded-md border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-900">
                    <option value="">All payments</option>
                    @foreach(['pending','paid','failed','refunded'] as $ps)
                        <option value="{{ $ps }}" @selected(request('payment_status')===$ps)>{{ ucfirst($ps) }}</option>
                    @endforeach
                </select>
                <select name="sort" class="rounded-md border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-900">
                    <option value="newest" @selected(request('sort','newest')==='newest')>Newest</option>
                    <option value="oldest" @selected(request('sort')==='oldest')>Oldest</option>
                    <option value="amount_high" @selected(request('sort')==='amount_high')>Highest value</option>
                    <option value="amount_low" @selected(request('sort')==='amount_low')>Lowest value</option>
                </select>
                <x-admin.vendor.btn variant="primary" type="submit">Filter</x-admin.vendor.btn>
                <div class="flex gap-2 lg:col-span-6">
                    <input type="date" name="date_from" value="{{ request('date_from') }}" class="rounded-md border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-900" />
                    <input type="date" name="date_to" value="{{ request('date_to') }}" class="rounded-md border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-900" />
                </div>
            </form>

            <div class="overflow-x-auto">
                <table class="min-w-[1200px] w-full text-sm">
                    <thead class="sticky top-0 z-10 border-b border-gray-200 bg-gray-50/95 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:border-gray-800 dark:bg-gray-900/95">
                        <tr>
                            <th class="px-4 py-3">Order</th>
                            <th class="px-4 py-3">Customer</th>
                            <th class="px-4 py-3">Products</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3">Payment</th>
                            <th class="px-4 py-3 text-right">Total</th>
                            <th class="px-4 py-3 text-right">Earnings</th>
                            <th class="px-4 py-3 text-right">Commission</th>
                            <th class="px-4 py-3">Date</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse($orders as $mapping)
                            @php
                                $order = $mapping->order;
                                $earnings = max(0, (float) $mapping->total_amount - (float) $mapping->commission_amount);
                                $itemSummary = $order?->items?->take(2)->map(fn ($i) => ($i->product?->name ?? 'Item').' ×'.$i->quantity)->implode(', ');
                            @endphp
                            <tr class="transition-colors hover:bg-gray-50/80 dark:hover:bg-gray-800/40">
                                <td class="px-4 py-4 font-medium text-gray-900 dark:text-gray-100">#{{ $mapping->order_id }}</td>
                                <td class="px-4 py-4">
                                    <p class="font-medium">{{ $order?->user?->name ?? $order?->guest_full_name ?? 'Guest' }}</p>
                                    <p class="text-xs text-gray-500">{{ $order?->user?->email ?? $order?->guest_email ?? '—' }}</p>
                                    <p class="text-xs text-gray-500">{{ $order?->user?->phone ?? $order?->guest_phone ?? '' }}</p>
                                </td>
                                <td class="px-4 py-4 max-w-[200px] truncate text-xs text-gray-600" title="{{ $itemSummary }}">{{ $itemSummary ?: '—' }}</td>
                                <td class="px-4 py-4"><x-admin.vendor.status-badge :status="$mapping->status" /></td>
                                <td class="px-4 py-4">
                                    <span class="inline-flex rounded-md bg-gray-100 px-2 py-0.5 text-xs font-medium capitalize dark:bg-gray-800">{{ $order?->payment_status ?? '—' }}</span>
                                    <p class="mt-1 text-[10px] text-gray-500">{{ $order?->payment_method ?? '' }}</p>
                                </td>
                                <td class="px-4 py-4 text-right tabular-nums font-medium">AED {{ number_format($mapping->total_amount, 2) }}</td>
                                <td class="px-4 py-4 text-right tabular-nums text-emerald-600">AED {{ number_format($earnings, 2) }}</td>
                                <td class="px-4 py-4 text-right tabular-nums text-indigo-600">AED {{ number_format($mapping->commission_amount ?? 0, 2) }}</td>
                                <td class="px-4 py-4 text-xs text-gray-500">{{ $mapping->created_at?->format('M j, Y') }}</td>
                                <td class="px-4 py-4 text-right">
                                    <x-admin.vendor.btn variant="ghost" :href="route('admin.vendors.orders.show', [$vendor, $mapping])">View</x-admin.vendor.btn>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="10" class="p-0"><x-admin.vendor.empty-state title="No orders found" description="No orders match your current filters." /></td></tr>
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
