<x-admin-layout>
    <div class="space-y-6">
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-semibold text-gray-900 dark:text-gray-100">Shop Coupons</h1>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Manage discount codes for mobile checkout. Customers apply codes on the payment screen.</p>
            </div>
            <a href="{{ route('admin.coupons.create') }}" class="inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg shadow-sm">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Add coupon
            </a>
        </div>

        @if(session('success'))
            <div class="rounded-lg border border-green-200 bg-green-50 dark:bg-green-900/20 text-green-800 dark:text-green-200 px-4 py-3">{{ session('success') }}</div>
        @endif

        <form method="GET" action="{{ route('admin.coupons.index') }}" class="flex flex-wrap gap-3 items-end">
            <div class="flex-1 min-w-[200px] max-w-md">
                <label for="search" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Search</label>
                <input type="search" name="search" id="search" value="{{ $search }}" placeholder="Code or title"
                       class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100">
            </div>
            <button type="submit" class="px-4 py-2.5 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg text-sm font-medium hover:bg-gray-50 dark:hover:bg-gray-700">Search</button>
        </form>

        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden">
            @if($coupons->count() > 0)
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                        <thead class="bg-gray-50 dark:bg-gray-900/50">
                            <tr>
                                <th class="px-4 py-3 text-left font-semibold text-gray-600 dark:text-gray-300">Code</th>
                                <th class="px-4 py-3 text-left font-semibold text-gray-600 dark:text-gray-300">Title</th>
                                <th class="px-4 py-3 text-left font-semibold text-gray-600 dark:text-gray-300">Discount</th>
                                <th class="px-4 py-3 text-left font-semibold text-gray-600 dark:text-gray-300">Scope</th>
                                <th class="px-4 py-3 text-left font-semibold text-gray-600 dark:text-gray-300">Status</th>
                                <th class="px-4 py-3 text-right font-semibold text-gray-600 dark:text-gray-300">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach($coupons as $c)
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/30">
                                    <td class="px-4 py-3 font-mono font-semibold text-indigo-700 dark:text-indigo-300">{{ $c->code }}</td>
                                    <td class="px-4 py-3 text-gray-900 dark:text-gray-100">{{ $c->title }}</td>
                                    <td class="px-4 py-3 text-gray-600 dark:text-gray-300">
                                        @if($c->discount_type === 'percentage')
                                            {{ rtrim(rtrim(number_format((float)$c->discount_value, 2), '0'), '.') }}%
                                            @if($c->max_discount_amount)
                                                <span class="text-xs text-gray-500">(max {{ number_format((float)$c->max_discount_amount, 2) }} AED)</span>
                                            @endif
                                        @else
                                            {{ number_format((float)$c->discount_value, 2) }} AED
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 capitalize text-gray-600 dark:text-gray-400">{{ str_replace('_', ' ', $c->applies_to ?? 'all') }}</td>
                                    <td class="px-4 py-3">
                                        <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium {{ $c->is_active ? 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300' : 'bg-gray-100 text-gray-600' }}">
                                            {{ $c->is_active ? 'Active' : 'Inactive' }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-right whitespace-nowrap">
                                        <form method="POST" action="{{ route('admin.coupons.toggle-status', $c->id) }}" class="inline">
                                            @csrf
                                            <button type="submit" class="text-xs font-medium px-2 py-1 rounded {{ $c->is_active ? 'text-amber-700 bg-amber-50' : 'text-green-700 bg-green-50' }}">
                                                {{ $c->is_active ? 'Disable' : 'Enable' }}
                                            </button>
                                        </form>
                                        <a href="{{ route('admin.coupons.edit', $c->id) }}" class="ml-2 text-xs font-medium text-indigo-600 hover:underline">Edit</a>
                                        <form method="POST" action="{{ route('admin.coupons.destroy', $c->id) }}" class="inline ml-2" onsubmit="return confirm('Delete this coupon?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-xs font-medium text-red-600 hover:underline">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="px-4 py-3 border-t border-gray-200 dark:border-gray-700">{{ $coupons->links() }}</div>
            @else
                <div class="p-12 text-center text-gray-500">
                    <p>No coupons yet.</p>
                    <a href="{{ route('admin.coupons.create') }}" class="mt-3 inline-block text-indigo-600 font-medium hover:underline">Create your first coupon</a>
                </div>
            @endif
        </div>

        <div class="rounded-xl border border-indigo-200 dark:border-indigo-800 bg-indigo-50/80 dark:bg-indigo-950/30 px-4 py-3 text-sm text-indigo-900 dark:text-indigo-200">
            <strong>Mobile checkout:</strong> Apply button uses <code class="text-xs bg-white/60 dark:bg-black/30 px-1 rounded">POST /api/shop/coupons/apply</code> (client token). See Postman folder <strong>8 → K</strong>.
        </div>
    </div>
</x-admin-layout>
