<x-admin-layout>
    @include('admin.loyalty._theme')

    <div class="space-y-6">
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.loyalty.index') }}" class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-gray-300 bg-white text-gray-600 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </a>
            <div>
                <h1 class="text-2xl font-semibold text-gray-900 dark:text-gray-100">Loyalty customers</h1>
                <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">Same data as <code class="text-xs">GET /api/admin/loyalty/customers</code></p>
            </div>
        </div>

        <form method="GET" action="{{ route('admin.loyalty.customers') }}" class="flex flex-wrap items-end gap-3">
            <div class="min-w-[200px] max-w-md flex-1">
                <label for="search" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Search</label>
                <input type="search" name="search" id="search" value="{{ $search }}" placeholder="Name, email, or phone"
                       class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100">
            </div>
            <button type="submit" class="rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200">Search</button>
        </form>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <div class="rounded-xl border border-indigo-200 bg-indigo-50 p-4 dark:border-indigo-800 dark:bg-indigo-950/30">
                <p class="text-xs font-semibold uppercase tracking-wide text-indigo-700 dark:text-indigo-300">Visible</p>
                <p class="mt-1 text-2xl font-bold text-indigo-900 dark:text-indigo-100">{{ $summary['visible'] }}</p>
            </div>
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 dark:border-emerald-800 dark:bg-emerald-950/30">
                <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700 dark:text-emerald-300">Points pool</p>
                <p class="mt-1 text-2xl font-bold text-emerald-900 dark:text-emerald-100">{{ number_format($summary['points_pool']) }}</p>
            </div>
            <div class="rounded-xl border border-teal-200 bg-teal-50 p-4 dark:border-teal-800 dark:bg-teal-950/30">
                <p class="text-xs font-semibold uppercase tracking-wide text-teal-700 dark:text-teal-300">Holders</p>
                <p class="mt-1 text-2xl font-bold text-teal-900 dark:text-teal-100">{{ $summary['holders'] }}</p>
            </div>
        </div>

        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
            @if(count($customers) > 0)
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-900/50">
                            <tr>
                                <th class="px-4 py-3 text-left font-semibold text-gray-600 dark:text-gray-300">Customer</th>
                                <th class="px-4 py-3 text-left font-semibold text-gray-600 dark:text-gray-300">City</th>
                                <th class="px-4 py-3 text-right font-semibold text-gray-600 dark:text-gray-300">Points</th>
                                <th class="px-4 py-3 text-right font-semibold text-gray-600 dark:text-gray-300"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach($customers as $c)
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/30">
                                    <td class="px-4 py-3">
                                        <div class="flex items-center gap-3">
                                            @if(!empty($c['profile_picture_url']))
                                                <img src="{{ $c['profile_picture_url'] }}" alt="" class="h-9 w-9 rounded-full object-cover ring-1 ring-gray-200 dark:ring-gray-600">
                                            @else
                                                <div class="flex h-9 w-9 items-center justify-center rounded-full bg-indigo-100 text-xs font-semibold text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300">
                                                    {{ strtoupper(substr($c['name'] ?? '?', 0, 1)) }}
                                                </div>
                                            @endif
                                            <div>
                                                <p class="font-semibold text-gray-900 dark:text-gray-100">{{ $c['name'] }}</p>
                                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ $c['email'] }}</p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ $c['city'] }}</td>
                                    <td class="px-4 py-3 text-right font-bold text-indigo-700 dark:text-indigo-300">{{ number_format($c['points']) }}</td>
                                    <td class="px-4 py-3 text-right">
                                        <a href="{{ route('admin.loyalty.customers.show', $c['id']) }}" class="text-xs font-medium text-indigo-600 hover:underline">Open ledger</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="p-12 text-center text-sm text-gray-500">No customers found.</div>
            @endif
        </div>
    </div>
</x-admin-layout>
