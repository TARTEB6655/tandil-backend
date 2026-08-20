<x-admin-layout>
    <div class="space-y-6">
        <h1 class="text-xl font-semibold">Vendor orders</h1>
        <x-admin.marketplace-nav />
        <form method="GET" class="flex flex-wrap gap-2 items-center">
            <input name="search" value="{{ request('search') }}" placeholder="Order ID" class="rounded-lg border-gray-300 text-sm dark:bg-gray-800" />
            <select name="status" class="rounded-lg border-gray-300 text-sm dark:bg-gray-800">
                <option value="">All statuses</option>
                @foreach(['pending','confirmed','processing','shipped','delivered','cancelled'] as $s)
                    <option value="{{ $s }}" @selected(request('status')===$s)>{{ ucfirst($s) }}</option>
                @endforeach
            </select>
            <label class="inline-flex items-center gap-2 text-sm"><input type="checkbox" name="dispute" value="1" @checked(request('dispute')) /> Disputes only</label>
            <label class="inline-flex items-center gap-2 text-sm"><input type="checkbox" name="exclude_demo" value="1" @checked(request('exclude_demo')) /> Hide demo orders</label>
            <button class="px-4 py-2 bg-gray-900 text-white text-sm rounded-lg">Filter</button>
        </form>
        <div class="bg-white dark:bg-gray-800 rounded-xl border overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 dark:bg-gray-700/50"><tr>
                    <th class="px-4 py-3 text-left">Order</th><th class="px-4 py-3 text-left">Vendor</th><th class="px-4 py-3 text-left">Customer</th><th class="px-4 py-3 text-left">Status</th><th class="px-4 py-3 text-right">Total</th><th class="px-4 py-3 text-right"></th>
                </tr></thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                    @forelse($orders as $o)
                        @php
                            $isDemo = str_starts_with((string) ($o->order?->special_instructions ?? ''), \Database\Seeders\VendorDemoOrdersSeeder::DEMO_MARKER);
                        @endphp
                        <tr>
                            <td class="px-4 py-3">
                                #{{ $o->order_id }}
                                @if($isDemo)
                                    <span class="ml-1 inline-flex rounded bg-amber-100 px-1.5 py-0.5 text-[10px] font-semibold uppercase text-amber-800">Demo</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">{{ $o->vendor?->profile?->business_name }}</td>
                            <td class="px-4 py-3">{{ $o->order?->user?->name ?? $o->order?->guest_full_name ?? 'Guest' }}</td>
                            <td class="px-4 py-3">{{ ucfirst($o->status) }} @if($o->dispute_status)<span class="text-amber-600 text-xs">· dispute</span>@endif</td>
                            <td class="px-4 py-3 text-right">AED {{ number_format($o->total_amount, 2) }}</td>
                            <td class="px-4 py-3 text-right"><a href="{{ route('admin.marketplace.orders.show', $o) }}" class="text-indigo-600">View</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-8 text-center text-gray-500">No orders.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $orders->links() }}
    </div>
</x-admin-layout>
