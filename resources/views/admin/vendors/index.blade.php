<x-admin-layout>
    <div class="space-y-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-semibold text-gray-900 dark:text-gray-100">Vendor Management</h1>
                <p class="mt-1 text-sm text-gray-500">Track sellers, performance metrics, and application reviews.</p>
            </div>
            <a href="{{ route('admin.marketplace.dashboard') }}" class="inline-flex items-center gap-1 text-sm font-medium text-indigo-600 hover:text-indigo-800">
                Marketplace overview
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </a>
        </div>

        <x-admin.marketplace-nav />

        @if(($stats['under_review'] ?? 0) > 0 || ($stats['pending'] ?? 0) > 0)
            <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                <span><strong>{{ ($stats['pending'] ?? 0) + ($stats['under_review'] ?? 0) }}</strong> vendor application(s) awaiting review.</span>
                <a href="{{ route('admin.vendors.index', ['status' => 'under_review']) }}" class="font-medium text-amber-800 underline hover:text-amber-900">Review now</a>
            </div>
        @endif

        <div class="grid grid-cols-2 gap-3 md:grid-cols-4 lg:grid-cols-7">
            @foreach([
                'total' => ['label' => 'Total', 'color' => 'text-gray-900'],
                'pending' => ['label' => 'Pending', 'color' => 'text-yellow-600'],
                'under_review' => ['label' => 'Under review', 'color' => 'text-blue-600'],
                'approved' => ['label' => 'Approved', 'color' => 'text-green-600'],
                'suspended' => ['label' => 'Suspended', 'color' => 'text-amber-600'],
                'rejected' => ['label' => 'Rejected', 'color' => 'text-red-600'],
                'disabled' => ['label' => 'Disabled', 'color' => 'text-gray-500'],
            ] as $key => $meta)
                <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-500">{{ $meta['label'] }}</p>
                    <p class="mt-1 text-2xl font-semibold {{ $meta['color'] }} dark:text-gray-100">{{ $stats[$key] ?? 0 }}</p>
                </div>
            @endforeach
        </div>

        @if(isset($recentRequests) && $recentRequests->isNotEmpty())
            <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="font-semibold text-gray-900 dark:text-gray-100">Recent vendor requests</h2>
                    <a href="{{ route('admin.vendors.index', ['status' => 'pending']) }}" class="text-sm font-medium text-indigo-600 hover:underline">View all</a>
                </div>
                <div class="divide-y divide-gray-100 dark:divide-gray-700">
                    @foreach($recentRequests as $req)
                        <div class="flex flex-wrap items-center justify-between gap-3 py-3 first:pt-0 last:pb-0">
                            <div class="flex items-center gap-3">
                                @if($req->profile?->logo_url)
                                    <img src="{{ $req->profile->logo_url }}" alt="" class="h-10 w-10 rounded-lg border object-cover" />
                                @else
                                    <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-gray-100 text-sm font-semibold text-gray-500 dark:bg-gray-700">{{ strtoupper(substr($req->profile?->business_name ?? 'V', 0, 1)) }}</div>
                                @endif
                                <div>
                                    <p class="font-medium text-sm text-gray-900 dark:text-gray-100">{{ $req->profile?->business_name }}</p>
                                    <p class="text-xs text-gray-500">{{ $req->profile?->email }} · {{ $req->statusEnum()->label() }} · {{ $req->created_at?->diffForHumans() }}</p>
                                </div>
                            </div>
                            <a href="{{ route('admin.vendors.show', $req) }}" class="rounded-lg bg-indigo-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-indigo-700">Review</a>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        <form method="GET" class="flex flex-wrap items-end gap-2 rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <div class="min-w-[200px] flex-1">
                <label class="mb-1 block text-xs font-medium text-gray-500">Search</label>
                <input name="search" value="{{ request('search') }}" placeholder="Business, email, or owner" class="w-full rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-900" />
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-gray-500">Status</label>
                <select name="status" class="rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-900">
                    <option value="">All statuses</option>
                    @foreach(\App\Enums\VendorStatus::cases() as $status)
                        <option value="{{ $status->value }}" @selected(request('status')===$status->value)>{{ $status->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-gray-500">Sort</label>
                <select name="sort" class="rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-900">
                    <option value="newest" @selected(($sort ?? 'newest') === 'newest')>Newest</option>
                    <option value="oldest" @selected(($sort ?? '') === 'oldest')>Oldest</option>
                    <option value="business" @selected(($sort ?? '') === 'business')>Business name</option>
                </select>
            </div>
            <button class="rounded-lg bg-gray-900 px-4 py-2 text-sm font-medium text-white hover:bg-gray-800">Apply filters</button>
        </form>

        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-900/50">
                        <tr>
                            <th class="px-4 py-3 text-left font-medium text-gray-500">Vendor</th>
                            <th class="px-4 py-3 text-left font-medium text-gray-500">Contact</th>
                            <th class="px-4 py-3 text-left font-medium text-gray-500">Status</th>
                            <th class="px-4 py-3 text-right font-medium text-gray-500">Products</th>
                            <th class="px-4 py-3 text-right font-medium text-gray-500">Orders</th>
                            <th class="px-4 py-3 text-right font-medium text-gray-500">Pending</th>
                            <th class="px-4 py-3 text-right font-medium text-gray-500">Revenue</th>
                            <th class="px-4 py-3 text-right font-medium text-gray-500">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @forelse($vendors as $vendor)
                            @php
                                $m = $metricsMap[$vendor->id] ?? ['total_products' => 0, 'active_products' => 0, 'total_orders' => 0, 'pending_orders' => 0, 'revenue' => 0];
                                $statusColors = [
                                    'approved' => 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300',
                                    'pending' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/40 dark:text-yellow-300',
                                    'under_review' => 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300',
                                    'suspended' => 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300',
                                    'rejected' => 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300',
                                    'disabled' => 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300',
                                ];
                                $badge = $statusColors[$vendor->status] ?? 'bg-gray-100 text-gray-700';
                            @endphp
                            <tr class="transition hover:bg-gray-50 dark:hover:bg-gray-900/30">
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-3">
                                        @if($vendor->profile?->logo_url)
                                            <img src="{{ $vendor->profile->logo_url }}" alt="" class="h-9 w-9 rounded-lg border object-cover" />
                                        @else
                                            <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-indigo-50 text-xs font-semibold text-indigo-600 dark:bg-indigo-900/30 dark:text-indigo-300">{{ strtoupper(substr($vendor->profile?->business_name ?? 'V', 0, 1)) }}</div>
                                        @endif
                                        <div>
                                            <p class="font-medium text-gray-900 dark:text-gray-100">{{ $vendor->profile?->business_name ?? '—' }}</p>
                                            <p class="text-xs text-gray-500">{{ $vendor->profile?->owner_name }} · {{ $vendor->profile?->emirate ?? '—' }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <p class="text-gray-900 dark:text-gray-200">{{ $vendor->profile?->email }}</p>
                                    <p class="text-xs text-gray-500">{{ $vendor->profile?->phone ?? '—' }}</p>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium {{ $badge }}">{{ $vendor->statusEnum()->label() }}</span>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <span class="font-medium text-gray-900 dark:text-gray-100">{{ number_format($m['total_products']) }}</span>
                                    <span class="block text-xs text-gray-500">{{ $m['active_products'] }} active</span>
                                </td>
                                <td class="px-4 py-3 text-right font-medium text-gray-900 dark:text-gray-100">{{ number_format($m['total_orders']) }}</td>
                                <td class="px-4 py-3 text-right">
                                    @if($m['pending_orders'] > 0)
                                        <span class="font-medium text-amber-600">{{ number_format($m['pending_orders']) }}</span>
                                    @else
                                        <span class="text-gray-400">0</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right font-medium text-gray-900 dark:text-gray-100">AED {{ number_format($m['revenue'], 2) }}</td>
                                <td class="px-4 py-3 text-right">
                                    <a href="{{ route('admin.vendors.show', $vendor) }}" class="font-medium text-indigo-600 hover:text-indigo-800">View</a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="px-4 py-12 text-center text-gray-500">No vendors found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{ $vendors->links() }}
    </div>
</x-admin-layout>
