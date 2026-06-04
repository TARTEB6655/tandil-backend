<x-vendor-layout>
    <div class="mb-6">
        <h1 class="text-xl font-semibold text-gray-900">Order #{{ $mapping->order_id }}</h1>
        <p class="mt-1 text-sm text-gray-500"><a href="{{ route('vendor.orders.index') }}" class="text-indigo-600 hover:underline">← Back to orders</a></p>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
            <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                <h2 class="mb-4 font-medium text-gray-900">Order items</h2>
                <ul class="divide-y divide-gray-100">
                    @foreach($mapping->order?->items ?? [] as $item)
                        <li class="flex justify-between py-3 text-sm">
                            <span>{{ $item->product?->name ?? 'Product' }} × {{ $item->quantity }}</span>
                            <span class="text-gray-600">AED {{ number_format($item->subtotal, 2) }}</span>
                        </li>
                    @endforeach
                </ul>
                <div class="mt-4 border-t border-gray-200 pt-4 text-sm">
                    <div class="flex justify-between"><span>Subtotal</span><span>AED {{ number_format($mapping->subtotal, 2) }}</span></div>
                    <div class="flex justify-between"><span>Tax</span><span>AED {{ number_format($mapping->tax_amount, 2) }}</span></div>
                    <div class="flex justify-between"><span>Shipping</span><span>AED {{ number_format($mapping->shipping_amount, 2) }}</span></div>
                    <div class="mt-2 flex justify-between font-semibold"><span>Total</span><span>AED {{ number_format($mapping->total_amount, 2) }}</span></div>
                </div>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                <h2 class="mb-4 font-medium text-gray-900">Status history</h2>
                <ul class="space-y-2 text-sm text-gray-600">
                    @forelse($mapping->statusLogs as $log)
                        <li>{{ $log->created_at?->format('M d, H:i') }} — <strong class="capitalize text-gray-900">{{ $log->status }}</strong> @if($log->note)<span class="text-gray-500">({{ $log->note }})</span>@endif</li>
                    @empty
                        <li>No status changes yet.</li>
                    @endforelse
                </ul>
            </div>
        </div>

        <div class="space-y-4">
            <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                <p class="text-sm text-gray-500">Customer</p>
                <p class="mt-1 font-medium text-gray-900">{{ $mapping->order?->user?->name ?? $mapping->order?->guest_full_name ?? 'Guest' }}</p>
                <p class="text-sm text-gray-600">{{ $mapping->order?->user?->email ?? $mapping->order?->guest_email }}</p>
                <p class="mt-4 text-sm text-gray-500">Current status</p>
                <p class="mt-1 text-lg font-semibold capitalize text-indigo-700">{{ $mapping->status }}</p>
            </div>

            <form method="POST" action="{{ route('vendor.orders.update-status', $mapping->id) }}" class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                @csrf
                <h2 class="mb-3 font-medium text-gray-900">Update status</h2>
                <select name="status" class="mb-3 w-full rounded-lg border-gray-300 text-sm">
                    @foreach($statuses as $status)
                        <option value="{{ $status->value }}" @selected($mapping->status === $status->value)>{{ $status->label() }}</option>
                    @endforeach
                </select>
                <textarea name="note" rows="2" placeholder="Optional note" class="mb-3 w-full rounded-lg border-gray-300 text-sm"></textarea>
                <button type="submit" class="w-full rounded-lg bg-indigo-600 py-2 text-sm font-medium text-white hover:bg-indigo-700">Update</button>
            </form>
        </div>
    </div>
</x-vendor-layout>
