<x-admin-layout>
    <div class="space-y-6">
        <div>
            <h1 class="text-xl font-semibold text-gray-900 dark:text-gray-100">Marketplace Control Center</h1>
            <p class="text-sm text-gray-500 mt-1">Unified visibility over vendors, products, orders, revenue, and inventory.</p>
        </div>
        <x-admin.marketplace-nav />

        <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-3">
            @foreach([
                ['Vendors', $overview['vendors']['total']],
                ['Pending vendors', $overview['vendors']['pending']],
                ['Products', $overview['products']['total']],
                ['Pending products', $overview['products']['pending_approval']],
                ['Vendor orders', $overview['orders']['total']],
                ['Open disputes', $overview['orders']['open_disputes']],
            ] as [$label, $val])
                <div class="bg-white dark:bg-gray-800 rounded-xl border p-4">
                    <p class="text-xs text-gray-500 uppercase">{{ $label }}</p>
                    <p class="text-2xl font-semibold mt-1">{{ $val }}</p>
                </div>
            @endforeach
        </div>

        <div class="grid md:grid-cols-2 gap-4">
            <a href="{{ route('admin.support-chat.index', ['user_role' => 'vendor', 'status' => 'in_progress']) }}" class="block bg-white dark:bg-gray-800 rounded-xl border p-5 hover:border-indigo-300 transition-colors">
                <p class="text-xs text-gray-500 uppercase">Active vendor live chats</p>
                <p class="text-3xl font-semibold mt-1 text-indigo-600">{{ $activeLiveChats ?? 0 }}</p>
                <p class="text-sm text-gray-500 mt-2">Open support conversations →</p>
            </a>
            <a href="{{ route('admin.vendors.index', ['status' => 'pending']) }}" class="block bg-white dark:bg-gray-800 rounded-xl border p-5 hover:border-amber-300 transition-colors">
                <p class="text-xs text-gray-500 uppercase">Pending vendor applications</p>
                <p class="text-3xl font-semibold mt-1 text-amber-600">{{ $overview['vendors']['pending'] ?? 0 }}</p>
                <p class="text-sm text-gray-500 mt-2">Review applications →</p>
            </a>
        </div>

        @if(isset($recentVendorRequests) && $recentVendorRequests->isNotEmpty())
            <div class="bg-white dark:bg-gray-800 rounded-xl border p-6">
                <h2 class="font-medium mb-4">Recent vendor requests</h2>
                <div class="space-y-2">
                    @foreach($recentVendorRequests as $req)
                        <div class="flex items-center justify-between py-2 border-b border-gray-100 dark:border-gray-700 last:border-0">
                            <div>
                                <p class="text-sm font-medium">{{ $req->profile?->business_name }}</p>
                                <p class="text-xs text-gray-500">{{ $req->statusEnum()->label() }} · {{ $req->created_at?->diffForHumans() }}</p>
                            </div>
                            <a href="{{ route('admin.vendors.show', $req) }}" class="text-sm text-indigo-600 hover:underline">Review</a>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        <div class="grid lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2 bg-white dark:bg-gray-800 rounded-xl border p-6">
                <h2 class="font-medium mb-4">Revenue</h2>
                <dl class="grid sm:grid-cols-3 gap-4 text-sm">
                    <div><dt class="text-gray-500">Gross</dt><dd class="text-xl font-semibold">AED {{ number_format($overview['revenue']['gross'], 2) }}</dd></div>
                    <div><dt class="text-gray-500">Platform commission</dt><dd class="text-xl font-semibold text-indigo-600">AED {{ number_format($overview['revenue']['platform_commission'], 2) }}</dd></div>
                    <div><dt class="text-gray-500">Vendor payout (est.)</dt><dd class="text-xl font-semibold">AED {{ number_format($overview['revenue']['vendor_payout_estimate'], 2) }}</dd></div>
                </dl>
                <p class="text-xs text-gray-500 mt-4">Default commission: {{ $overview['settings']['commission_percent'] }}% · Product approval: {{ $overview['settings']['product_approval_required'] ? 'On' : 'Off' }}</p>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-xl border p-6">
                <h2 class="font-medium mb-4">Inventory alerts</h2>
                <p class="text-sm">Low stock: <strong>{{ $overview['inventory']['low_stock'] }}</strong></p>
                <p class="text-sm mt-2">Out of stock: <strong>{{ $overview['inventory']['out_of_stock'] }}</strong></p>
                <a href="{{ route('admin.marketplace.inventory.index', ['filter' => 'low']) }}" class="inline-block mt-4 text-sm text-indigo-600 hover:underline">View inventory →</a>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-xl border p-6">
            <h2 class="font-medium mb-4">Top vendors by revenue</h2>
            <table class="min-w-full text-sm">
                <thead><tr class="text-left text-gray-500"><th class="pb-2">Business</th><th class="pb-2">Orders</th><th class="pb-2 text-right">Revenue</th></tr></thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                    @forelse($topVendors as $row)
                        <tr>
                            <td class="py-2"><a href="{{ route('admin.vendors.show', $row['vendor_id']) }}" class="text-indigo-600 hover:underline">{{ $row['business_name'] }}</a></td>
                            <td class="py-2">{{ $row['order_count'] }}</td>
                            <td class="py-2 text-right">AED {{ number_format($row['revenue'], 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="py-4 text-gray-500">No vendor orders yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-admin-layout>
