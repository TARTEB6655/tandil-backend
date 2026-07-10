@php
    $order = $vendorOrder->order;
    $earnings = max(0, (float) $vendorOrder->total_amount - (float) $vendorOrder->commission_amount);
@endphp

<x-admin-layout>
    <x-admin.vendor.shell>
        <x-admin.vendor.nav :vendor="$vendor" />
        <x-admin.vendor.flash />

        <x-admin.vendor.page-header
            :title="'Order #'.$vendorOrder->order_id"
            :description="$vendor->profile?->business_name.' · Placed '.$vendorOrder->created_at?->format('M j, Y g:i A')">
            <x-slot:actions>
                <x-admin.vendor.btn variant="secondary" :href="route('admin.vendors.orders.invoice', [$vendor, $vendorOrder])">Download invoice</x-admin.vendor.btn>
                <x-admin.vendor.btn variant="secondary" :href="route('admin.vendors.orders.invoice', [$vendor, $vendorOrder]).'?print=1'" target="_blank">Print</x-admin.vendor.btn>
            </x-slot:actions>
        </x-admin.vendor.page-header>

        <div class="grid grid-cols-2 gap-4 md:grid-cols-4 lg:grid-cols-6">
            <x-admin.vendor.stat-card label="Order Total" :value="'AED '.number_format($vendorOrder->total_amount, 2)" />
            <x-admin.vendor.stat-card label="Vendor Earnings" :value="'AED '.number_format($earnings, 2)" accent="text-emerald-600" />
            <x-admin.vendor.stat-card label="Commission" :value="'AED '.number_format($vendorOrder->commission_amount ?? 0, 2)" accent="text-indigo-600" />
            <x-admin.vendor.stat-card label="Subtotal" :value="'AED '.number_format($vendorOrder->subtotal, 2)" />
            <x-admin.vendor.stat-card label="Payment" :value="ucfirst($order?->payment_status ?? '—')" />
            <x-admin.vendor.stat-card label="Delivery" :value="ucfirst(str_replace('_', ' ', $vendorOrder->status))" />
        </div>

        <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
            <div class="space-y-6 xl:col-span-2">
                <x-admin.vendor.card title="Ordered products" :padding="false">
                    <table class="w-full text-sm">
                        <thead class="border-b border-gray-200 bg-gray-50/80 text-left text-xs uppercase text-gray-500 dark:border-gray-800">
                            <tr>
                                <th class="px-5 py-3">Product</th>
                                <th class="px-5 py-3 text-right">Qty</th>
                                <th class="px-5 py-3 text-right">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @forelse($order?->items ?? [] as $item)
                                <tr>
                                    <td class="px-5 py-4 font-medium">{{ $item->product?->name ?? $item->name ?? 'Item' }}</td>
                                    <td class="px-5 py-4 text-right tabular-nums">{{ $item->quantity }}</td>
                                    <td class="px-5 py-4 text-right tabular-nums">AED {{ number_format($item->subtotal ?? ($item->price * $item->quantity), 2) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="px-5 py-8 text-center text-gray-500">No line items.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </x-admin.vendor.card>

                <x-admin.vendor.card title="Status timeline">
                    <ol class="space-y-3">
                        @forelse($vendorOrder->statusLogs as $log)
                            <li class="flex items-start gap-3 text-sm">
                                <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full bg-indigo-600"></span>
                                <div>
                                    <p class="font-medium capitalize">{{ str_replace('_', ' ', $log->status) }}</p>
                                    <p class="text-xs text-gray-500">{{ $log->created_at?->format('M j, Y g:i A') }} · {{ $log->changedByUser?->name ?? 'System' }}</p>
                                    @if($log->note)<p class="text-xs text-gray-600">{{ $log->note }}</p>@endif
                                </div>
                            </li>
                        @empty
                            <p class="text-sm text-gray-500">No status changes recorded.</p>
                        @endforelse
                    </ol>
                </x-admin.vendor.card>
            </div>

            <div class="space-y-6">
                <x-admin.vendor.card title="Customer">
                    <dl class="space-y-3 text-sm">
                        <div><dt class="text-xs uppercase text-gray-500">Name</dt><dd class="mt-1 font-medium">{{ $order?->user?->name ?? $order?->guest_full_name ?? 'Guest' }}</dd></div>
                        <div><dt class="text-xs uppercase text-gray-500">Email</dt><dd class="mt-1">{{ $order?->user?->email ?? $order?->guest_email ?? '—' }}</dd></div>
                        <div><dt class="text-xs uppercase text-gray-500">Phone</dt><dd class="mt-1">{{ $order?->user?->phone ?? $order?->guest_phone ?? '—' }}</dd></div>
                        <div><dt class="text-xs uppercase text-gray-500">Shipping address</dt><dd class="mt-1 text-gray-600">{{ $order?->formatted_shipping_address ?? $order?->shippingAddress?->address_line_1 ?? '—' }}</dd></div>
                    </dl>
                    @if($order?->user?->email)
                        <a href="mailto:{{ $order->user->email }}" class="mt-4 inline-flex text-sm font-medium text-indigo-600 hover:text-indigo-500">Contact customer</a>
                    @endif
                </x-admin.vendor.card>

                <x-admin.vendor.card title="Vendor">
                    <p class="text-sm font-medium">{{ $vendor->profile?->business_name }}</p>
                    <p class="text-sm text-gray-500">{{ $vendor->profile?->email }}</p>
                    <a href="mailto:{{ $vendor->profile?->email }}" class="mt-4 inline-flex text-sm font-medium text-indigo-600 hover:text-indigo-500">Contact vendor</a>
                </x-admin.vendor.card>

                <x-admin.vendor.card title="Update order status">
                    <form method="POST" action="{{ route('admin.vendors.orders.status', [$vendor, $vendorOrder]) }}" class="space-y-3">
                        @csrf
                        <select name="status" class="w-full rounded-md border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-900">
                            @foreach(\App\Enums\VendorOrderStatus::cases() as $status)
                                <option value="{{ $status->value }}" @selected($vendorOrder->status === $status->value)>{{ $status->label() }}</option>
                            @endforeach
                        </select>
                        <input name="note" placeholder="Status note (optional)" class="w-full rounded-md border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-900" />
                        <x-admin.vendor.btn variant="brand" type="submit" class="w-full">Update status</x-admin.vendor.btn>
                    </form>
                </x-admin.vendor.card>

                <x-admin.vendor.card title="Payment status">
                    <form method="POST" action="{{ route('admin.vendors.orders.payment', [$vendor, $vendorOrder]) }}" class="space-y-3">
                        @csrf
                        <select name="payment_status" class="w-full rounded-md border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-900">
                            @foreach(['pending','paid','failed','refunded'] as $ps)
                                <option value="{{ $ps }}" @selected(($order?->payment_status ?? '') === $ps)>{{ ucfirst($ps) }}</option>
                            @endforeach
                        </select>
                        <x-admin.vendor.btn variant="secondary" type="submit" class="w-full">Update payment</x-admin.vendor.btn>
                    </form>
                </x-admin.vendor.card>

                <x-admin.vendor.card title="Refund">
                    <form method="POST" action="{{ route('admin.vendors.orders.refund', [$vendor, $vendorOrder]) }}" class="space-y-3" onsubmit="return confirm('Process this refund?')">
                        @csrf
                        <input type="number" step="0.01" name="refund_amount" required max="{{ $order?->total_amount ?? 0 }}" value="{{ $order?->total_amount ?? 0 }}" class="w-full rounded-md border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-900" />
                        <input name="refund_reason" placeholder="Refund reason" class="w-full rounded-md border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-900" />
                        <x-admin.vendor.btn variant="danger" type="submit" class="w-full">Process refund</x-admin.vendor.btn>
                    </form>
                </x-admin.vendor.card>

                <x-admin.vendor.card title="Cancel order">
                    <form method="POST" action="{{ route('admin.vendors.orders.cancel', [$vendor, $vendorOrder]) }}" class="space-y-3" onsubmit="return confirm('Cancel this order?')">
                        @csrf
                        <input name="reason" required placeholder="Cancellation reason" class="w-full rounded-md border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-900" />
                        <x-admin.vendor.btn variant="danger" type="submit" class="w-full">Cancel order</x-admin.vendor.btn>
                    </form>
                </x-admin.vendor.card>
            </div>
        </div>
    </x-admin.vendor.shell>
</x-admin-layout>
