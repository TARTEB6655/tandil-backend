<x-admin-layout>
    <div class="space-y-6 max-w-4xl">
        @if(session('success'))<div class="p-3 bg-green-50 text-green-800 text-sm rounded-lg">{{ session('success') }}</div>@endif
        <x-admin.marketplace-nav />
        <h1 class="text-xl font-semibold">Vendor order #{{ $vendorOrder->order_id }}</h1>
        <p class="text-sm text-gray-500">{{ $vendorOrder->vendor?->profile?->business_name }} · {{ ucfirst($vendorOrder->status) }}</p>
        <div class="grid sm:grid-cols-3 gap-4 text-sm bg-white dark:bg-gray-800 rounded-xl border p-6">
            <div><span class="text-gray-500">Subtotal</span><p class="font-semibold">AED {{ number_format($vendorOrder->subtotal, 2) }}</p></div>
            <div><span class="text-gray-500">Total</span><p class="font-semibold">AED {{ number_format($vendorOrder->total_amount, 2) }}</p></div>
            <div><span class="text-gray-500">Commission</span><p class="font-semibold text-indigo-600">AED {{ number_format($vendorOrder->commission_amount, 2) }}</p></div>
        </div>
        <form method="POST" action="{{ route('admin.marketplace.orders.status', $vendorOrder) }}" class="bg-white dark:bg-gray-800 rounded-xl border p-4 flex flex-wrap gap-2 items-end">
            @csrf
            <div><label class="text-xs text-gray-500">Update status</label>
                <select name="status" class="block rounded-lg border-gray-300 text-sm mt-1">
                    @foreach(['pending','confirmed','processing','shipped','delivered','cancelled'] as $s)
                        <option value="{{ $s }}" @selected($vendorOrder->status===$s)>{{ ucfirst($s) }}</option>
                    @endforeach
                </select>
            </div>
            <input name="note" placeholder="Note" class="rounded-lg border-gray-300 text-sm flex-1 min-w-[120px]" />
            <button class="px-4 py-2 bg-indigo-600 text-white text-sm rounded-lg">Update</button>
        </form>
        <form method="POST" action="{{ route('admin.marketplace.orders.cancel', $vendorOrder) }}" class="bg-white dark:bg-gray-800 rounded-xl border p-4 flex gap-2">
            @csrf
            <input name="reason" required placeholder="Cancellation reason" class="flex-1 rounded-lg border-gray-300 text-sm" />
            <button class="px-4 py-2 bg-red-600 text-white text-sm rounded-lg">Cancel order</button>
        </form>
        <form method="POST" action="{{ route('admin.marketplace.orders.dispute', $vendorOrder) }}" class="bg-white dark:bg-gray-800 rounded-xl border p-4 space-y-3">
            @csrf
            <h3 class="font-medium text-sm">Dispute resolution</h3>
            <select name="dispute_status" class="w-full rounded-lg border-gray-300 text-sm">
                @foreach(['open','under_review','resolved','closed'] as $s)
                    <option value="{{ $s }}" @selected($vendorOrder->dispute_status===$s)>{{ str_replace('_',' ', ucfirst($s)) }}</option>
                @endforeach
            </select>
            <textarea name="dispute_notes" rows="2" placeholder="Dispute notes" class="w-full rounded-lg border-gray-300 text-sm">{{ $vendorOrder->dispute_notes }}</textarea>
            <textarea name="admin_notes" rows="2" placeholder="Admin notes (internal)" class="w-full rounded-lg border-gray-300 text-sm">{{ $vendorOrder->admin_notes }}</textarea>
            <button class="px-4 py-2 bg-amber-600 text-white text-sm rounded-lg">Save dispute</button>
        </form>
        <div class="bg-white dark:bg-gray-800 rounded-xl border p-4">
            <h3 class="font-medium text-sm mb-2">Status timeline</h3>
            <ul class="text-sm space-y-1">
                @foreach($vendorOrder->statusLogs as $log)
                    <li>{{ $log->created_at?->format('Y-m-d H:i') }} — {{ ucfirst($log->status) }} @if($log->note)<span class="text-gray-500">({{ $log->note }})</span>@endif</li>
                @endforeach
            </ul>
        </div>
    </div>
</x-admin-layout>
