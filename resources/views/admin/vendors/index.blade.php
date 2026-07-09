<x-admin-layout>
    <div class="space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-xl font-medium text-gray-900 dark:text-gray-100">Vendor Management</h1>
                <p class="text-sm text-gray-500 mt-1">Approve, suspend, verify documents, and manage marketplace sellers.</p>
            </div>
            <a href="{{ route('admin.marketplace.dashboard') }}" class="text-sm text-indigo-600 hover:underline">Marketplace overview →</a>
        </div>
        <x-admin.marketplace-nav />

        @if(($stats['under_review'] ?? 0) > 0 || ($stats['pending'] ?? 0) > 0)
            <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
                <strong>{{ ($stats['pending'] ?? 0) + ($stats['under_review'] ?? 0) }}</strong> vendor application(s) awaiting review.
                <a href="{{ route('admin.vendors.index', ['status' => 'under_review']) }}" class="ml-2 font-medium text-amber-800 underline">Review now →</a>
            </div>
        @endif

        @if(isset($recentRequests) && $recentRequests->isNotEmpty())
            <div class="bg-white dark:bg-gray-800 rounded-xl border p-5">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="font-medium text-gray-900 dark:text-gray-100">Recent vendor requests</h2>
                    <a href="{{ route('admin.vendors.index', ['status' => 'pending']) }}" class="text-sm text-indigo-600 hover:underline">View all →</a>
                </div>
                <div class="space-y-3">
                    @foreach($recentRequests as $req)
                        <div class="flex flex-wrap items-center justify-between gap-3 p-3 rounded-lg bg-gray-50 dark:bg-gray-900/40">
                            <div>
                                <p class="font-medium text-sm">{{ $req->profile?->business_name }}</p>
                                <p class="text-xs text-gray-500">{{ $req->profile?->email }} · {{ $req->statusEnum()->label() }} · {{ $req->created_at?->diffForHumans() }}</p>
                            </div>
                            <a href="{{ route('admin.vendors.show', $req) }}" class="px-3 py-1.5 text-sm bg-indigo-600 text-white rounded-lg">Review</a>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-7 gap-3">
            @foreach([
                'total' => 'Total',
                'pending' => 'Pending',
                'under_review' => 'Under review',
                'approved' => 'Approved',
                'suspended' => 'Suspended',
                'rejected' => 'Rejected',
                'disabled' => 'Disabled',
            ] as $key => $label)
                <div class="bg-white dark:bg-gray-800 rounded-xl border p-4">
                    <p class="text-xs text-gray-500 uppercase">{{ $label }}</p>
                    <p class="text-2xl font-semibold mt-1">{{ $stats[$key] ?? 0 }}</p>
                </div>
            @endforeach
        </div>

        <form method="GET" class="flex flex-wrap gap-2">
            <input name="search" value="{{ request('search') }}" placeholder="Search business or email" class="rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 text-sm" />
            <select name="status" class="rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 text-sm">
                <option value="">All statuses</option>
                @foreach(\App\Enums\VendorStatus::cases() as $status)
                    <option value="{{ $status->value }}" @selected(request('status')===$status->value)>{{ $status->label() }}</option>
                @endforeach
            </select>
            <select name="sort" class="rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 text-sm">
                <option value="newest" @selected(($sort ?? 'newest') === 'newest')>Newest</option>
                <option value="oldest" @selected(($sort ?? '') === 'oldest')>Oldest</option>
                <option value="business" @selected(($sort ?? '') === 'business')>Business name</option>
            </select>
            <button class="px-4 py-2 bg-gray-900 text-white text-sm rounded-lg">Filter</button>
        </form>

        <div class="bg-white dark:bg-gray-800 rounded-xl border overflow-hidden">
            <table class="min-w-full text-sm divide-y divide-gray-200 dark:divide-gray-600">
                <thead class="bg-gray-50 dark:bg-gray-700/50"><tr>
                    <th class="px-4 py-3 text-left">Business</th><th class="px-4 py-3 text-left">Owner</th><th class="px-4 py-3 text-left">Email</th><th class="px-4 py-3 text-left">Commission</th><th class="px-4 py-3 text-left">Status</th><th class="px-4 py-3 text-right">Actions</th>
                </tr></thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-600">
                    @forelse($vendors as $vendor)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                            <td class="px-4 py-3 font-medium">{{ $vendor->profile?->business_name }}</td>
                            <td class="px-4 py-3">{{ $vendor->profile?->owner_name }}</td>
                            <td class="px-4 py-3">{{ $vendor->profile?->email }}</td>
                            <td class="px-4 py-3">{{ $vendor->commission_rate !== null ? $vendor->commission_rate.'%' : 'Default' }}</td>
                            <td class="px-4 py-3"><span class="px-2 py-0.5 rounded text-xs bg-gray-100 dark:bg-gray-700">{{ $vendor->statusEnum()->label() }}</span></td>
                            <td class="px-4 py-3 text-right"><a href="{{ route('admin.vendors.show', $vendor) }}" class="text-indigo-600 hover:underline">Manage</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-8 text-center text-gray-500">No vendors found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $vendors->links() }}
    </div>
</x-admin-layout>
