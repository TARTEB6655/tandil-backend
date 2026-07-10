<x-admin-layout>
    @php
        $totalRevenue = collect($metricsMap ?? [])->sum('revenue');
        $pendingCount = ($stats['pending'] ?? 0) + ($stats['under_review'] ?? 0);
    @endphp

    <x-admin.vendor.shell
        x-data="{
            bulkOpen: false,
            filtersOpen: {{ request()->hasAny(['search','status','verified','sort']) && request('sort') !== 'newest' ? 'true' : 'false' }},
            selected: [],
            pageIds: {{ $vendors->pluck('id')->values()->toJson() }},
            toggleAll(checked) {
                this.selected = checked ? [...this.pageIds] : [];
                this.bulkOpen = this.selected.length > 0;
            },
            toggleOne(id, checked) {
                id = Number(id);
                if (checked) {
                    if (!this.selected.includes(id)) this.selected.push(id);
                } else {
                    this.selected = this.selected.filter(i => i !== id);
                }
                this.bulkOpen = this.selected.length > 0;
            },
            isSelected(id) { return this.selected.includes(Number(id)); },
            allSelected() { return this.pageIds.length > 0 && this.selected.length === this.pageIds.length; },
            clearSelection() { this.selected = []; this.bulkOpen = false; }
        }">

        <x-admin.vendor.page-header
            :title="$pageTitle ?? 'Vendor Management'"
            :description="$pageSubtitle ?? 'Monitor marketplace vendors, performance metrics, and account status in one place.'">
            <x-slot:actions>
                <x-admin.vendor.btn variant="secondary" :href="route('admin.vendors.export', array_filter(['preset' => $listPreset ?? null] + request()->only(['search','status','sort','verified'])))">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                    Export
                </x-admin.vendor.btn>
                <x-admin.vendor.btn variant="primary" type="button" @click="bulkOpen = !bulkOpen">
                    Bulk actions
                </x-admin.vendor.btn>
            </x-slot:actions>
        </x-admin.vendor.page-header>

        <x-admin.vendor.nav />

        <x-admin.vendor.flash />

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-5">
            <x-admin.vendor.stat-card label="Total Vendors" :value="number_format($stats['total'] ?? 0)" />
            <x-admin.vendor.stat-card label="Active Vendors" :value="number_format($stats['approved'] ?? 0)" accent="text-emerald-600" />
            <x-admin.vendor.stat-card label="Pending Approval" :value="number_format($pendingCount)" accent="text-amber-600" />
            <x-admin.vendor.stat-card label="Suspended" :value="number_format($stats['suspended'] ?? 0)" accent="text-orange-600" />
            <x-admin.vendor.stat-card label="Total Revenue" :value="'AED '.number_format($totalRevenue, 2)" accent="text-indigo-600" />
        </div>

        {{-- Selection toolbar --}}
        <div x-show="selected.length > 0" x-cloak
             class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-indigo-200 bg-indigo-50/80 px-4 py-3 dark:border-indigo-900/50 dark:bg-indigo-950/30">
            <p class="text-sm font-medium text-indigo-900 dark:text-indigo-200">
                <span x-text="selected.length"></span> vendor(s) selected
            </p>
            <div class="flex gap-2">
                <x-admin.vendor.btn variant="brand" type="button" @click="bulkOpen = true">Apply bulk action</x-admin.vendor.btn>
                <x-admin.vendor.btn variant="secondary" type="button" @click="clearSelection()">Clear</x-admin.vendor.btn>
            </div>
        </div>

        @if(($listPreset ?? null) === null && isset($recentRequests) && $recentRequests->isNotEmpty())
            <x-admin.vendor.card title="Recent registration requests" :padding="false">
                <div class="divide-y divide-gray-100 dark:divide-gray-800">
                    @foreach($recentRequests as $req)
                        <div class="flex items-center justify-between gap-4 px-5 py-3">
                            <div class="flex items-center gap-3 min-w-0">
                                <x-admin.vendor.avatar :name="$req->profile?->business_name ?? 'V'" :src="$req->logo_url" size="sm" />
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-medium text-gray-900 dark:text-gray-100">{{ $req->profile?->business_name }}</p>
                                    <p class="text-xs text-gray-500">{{ $req->created_at?->diffForHumans() }}</p>
                                </div>
                            </div>
                            <x-admin.vendor.btn variant="brand" :href="route('admin.vendors.show', $req)">Review</x-admin.vendor.btn>
                        </div>
                    @endforeach
                </div>
            </x-admin.vendor.card>
        @endif

        <div x-show="bulkOpen" x-cloak>
            <x-admin.vendor.card title="Bulk actions">
                <form method="POST" action="{{ route('admin.vendors.bulk') }}" class="flex flex-wrap items-end gap-4">
                    @csrf
                    <template x-for="id in selected" :key="id">
                        <input type="hidden" name="vendor_ids[]" :value="id" />
                    </template>
                    <div class="min-w-[180px]">
                        <label class="mb-1.5 block text-xs font-medium text-gray-600 dark:text-gray-400">Action</label>
                        <select name="action" class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500/40 dark:border-gray-700 dark:bg-gray-900" required>
                            <option value="approve">Approve</option>
                            <option value="suspend">Suspend</option>
                            <option value="activate">Activate</option>
                            <option value="disable">Disable</option>
                            <option value="delete">Delete permanently</option>
                        </select>
                    </div>
                    <p class="text-sm text-gray-500" x-show="selected.length === 0">Select vendors using the checkboxes in the table.</p>
                    <x-admin.vendor.btn variant="primary" type="submit"
                        x-bind:disabled="selected.length === 0"
                        x-bind:class="selected.length === 0 ? 'opacity-50 cursor-not-allowed' : ''">
                        Apply to <span x-text="selected.length"></span> vendor(s)
                    </x-admin.vendor.btn>
                </form>
            </x-admin.vendor.card>
        </div>

        {{-- Search & filters --}}
        <x-admin.vendor.card :padding="false">
            <form method="GET" class="p-4">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-center">
                    <div class="relative flex-1">
                        <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        <input name="search" value="{{ request('search') }}" placeholder="Search by name, email, or phone..."
                               class="w-full rounded-md border-gray-300 py-2 pl-10 pr-4 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500/40 dark:border-gray-700 dark:bg-gray-900" />
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <x-admin.vendor.btn variant="secondary" type="button" @click="filtersOpen = !filtersOpen">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
                            Filters
                        </x-admin.vendor.btn>
                        <x-admin.vendor.btn variant="primary" type="submit">Search</x-admin.vendor.btn>
                    </div>
                </div>

                <div x-show="filtersOpen" x-cloak class="mt-4 grid gap-4 border-t border-gray-100 pt-4 dark:border-gray-800 sm:grid-cols-2 lg:grid-cols-4">
                    @if(empty($listPreset))
                        <div>
                            <label class="mb-1.5 block text-xs font-medium text-gray-600 dark:text-gray-400">Status</label>
                            <select name="status" class="w-full rounded-md border-gray-300 text-sm shadow-sm dark:border-gray-700 dark:bg-gray-900">
                                <option value="">All statuses</option>
                                @foreach(\App\Enums\VendorStatus::cases() as $status)
                                    <option value="{{ $status->value }}" @selected(request('status')===$status->value)>{{ $status->label() }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                    <div>
                        <label class="mb-1.5 block text-xs font-medium text-gray-600 dark:text-gray-400">Verification</label>
                        <select name="verified" class="w-full rounded-md border-gray-300 text-sm shadow-sm dark:border-gray-700 dark:bg-gray-900">
                            <option value="">All</option>
                            <option value="yes" @selected(request('verified')==='yes')>Verified</option>
                            <option value="no" @selected(request('verified')==='no')>Unverified</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-xs font-medium text-gray-600 dark:text-gray-400">Sort by</label>
                        <select name="sort" class="w-full rounded-md border-gray-300 text-sm shadow-sm dark:border-gray-700 dark:bg-gray-900">
                            <option value="newest" @selected(($sort ?? 'newest') === 'newest')>Newest first</option>
                            <option value="oldest" @selected(($sort ?? '') === 'oldest')>Oldest first</option>
                            <option value="business" @selected(($sort ?? '') === 'business')>Business name</option>
                            <option value="revenue" @selected(($sort ?? '') === 'revenue')>Highest revenue</option>
                        </select>
                    </div>
                </div>
            </form>
        </x-admin.vendor.card>

        {{-- Data table --}}
        <x-admin.vendor.card :padding="false">
            <div class="max-h-[calc(100vh-16rem)] overflow-auto">
                <table class="min-w-[1200px] w-full text-sm">
                    <thead class="sticky top-0 z-10 border-b border-gray-200 bg-gray-50/95 backdrop-blur dark:border-gray-800 dark:bg-gray-900/95">
                        <tr class="text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                            <th class="w-12 px-4 py-3">
                                <input type="checkbox" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500/40"
                                       :checked="allSelected()"
                                       @change="toggleAll($event.target.checked)" />
                            </th>
                            <th class="px-4 py-3">Vendor</th>
                            <th class="px-4 py-3">Store</th>
                            <th class="px-4 py-3">Email</th>
                            <th class="px-4 py-3">Phone</th>
                            <th class="px-4 py-3 text-right">Products</th>
                            <th class="px-4 py-3 text-right">Orders</th>
                            <th class="px-4 py-3 text-right">Revenue</th>
                            <th class="px-4 py-3 text-right">Commission</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3">Verified</th>
                            <th class="px-4 py-3">Last active</th>
                            <th class="w-12 px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse($vendors as $vendor)
                            @php
                                $m = $metricsMap[$vendor->id] ?? ['total_products'=>0,'active_products'=>0,'total_orders'=>0,'revenue'=>0,'commission_earned'=>0];
                                $verified = ($listService ?? null)?->isVerified($vendor) ?? false;
                                $businessName = $vendor->profile?->business_name ?? '—';
                                $ownerName = $vendor->profile?->owner_name ?? '—';
                                $lastActive = $vendor->user?->updated_at ?? $vendor->updated_at;
                            @endphp
                            <tr class="transition-colors"
                                :class="isSelected({{ $vendor->id }}) ? 'bg-indigo-50/60 dark:bg-indigo-950/20' : 'hover:bg-gray-50/80 dark:hover:bg-gray-800/40'">
                                <td class="px-4 py-4 align-middle">
                                    <input type="checkbox" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500/40"
                                           :checked="isSelected({{ $vendor->id }})"
                                           @change="toggleOne({{ $vendor->id }}, $event.target.checked)" />
                                </td>
                                <td class="px-4 py-4 align-middle">
                                    <div class="flex items-center gap-3">
                                        <x-admin.vendor.avatar :name="$ownerName" :src="$vendor->logo_url" size="sm" />
                                        <div class="min-w-0">
                                            <a href="{{ route('admin.vendors.show', $vendor) }}" class="truncate font-medium text-gray-900 hover:text-indigo-600 dark:text-gray-100">{{ $ownerName }}</a>
                                            <p class="text-xs text-gray-500">ID #{{ $vendor->id }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-4 align-middle">
                                    <p class="font-medium text-gray-900 dark:text-gray-100">{{ $businessName }}</p>
                                </td>
                                <td class="px-4 py-4 align-middle text-gray-600 dark:text-gray-300">{{ $vendor->profile?->email ?? '—' }}</td>
                                <td class="px-4 py-4 align-middle text-gray-600 dark:text-gray-300">{{ $vendor->profile?->phone ?? '—' }}</td>
                                <td class="px-4 py-4 align-middle text-right tabular-nums">
                                    <span class="font-medium text-gray-900 dark:text-gray-100">{{ number_format($m['total_products']) }}</span>
                                    <span class="text-xs text-gray-500">({{ $m['active_products'] }} active)</span>
                                </td>
                                <td class="px-4 py-4 align-middle text-right tabular-nums font-medium text-gray-900 dark:text-gray-100">{{ number_format($m['total_orders']) }}</td>
                                <td class="px-4 py-4 align-middle text-right tabular-nums font-medium text-emerald-600">AED {{ number_format($m['revenue'], 2) }}</td>
                                <td class="px-4 py-4 align-middle text-right tabular-nums text-indigo-600">AED {{ number_format($m['commission_earned'], 2) }}</td>
                                <td class="px-4 py-4 align-middle"><x-admin.vendor.status-badge :status="$vendor->status" /></td>
                                <td class="px-4 py-4 align-middle"><x-admin.vendor.verification-badge :verified="$verified" /></td>
                                <td class="px-4 py-4 align-middle text-xs text-gray-500">{{ $lastActive?->diffForHumans() ?? '—' }}</td>
                                <td class="px-4 py-4 align-middle">
                                    <x-admin.vendor.action-menu>
                                        <x-admin.vendor.menu-link :href="route('admin.vendors.show', $vendor)">View dashboard</x-admin.vendor.menu-link>
                                        <x-admin.vendor.menu-link :href="route('admin.vendors.products', $vendor)">Products</x-admin.vendor.menu-link>
                                        <x-admin.vendor.menu-link :href="route('admin.vendors.orders', $vendor)">Orders</x-admin.vendor.menu-link>
                                        <x-admin.vendor.menu-link :href="route('admin.vendors.edit', $vendor)">Edit vendor</x-admin.vendor.menu-link>
                                    </x-admin.vendor.action-menu>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="13" class="p-0">
                                    <x-admin.vendor.empty-state
                                        title="No vendors found"
                                        description="No vendors match your current filters. Try adjusting your search or filter criteria." />
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($vendors->hasPages())
                <div class="border-t border-gray-200 px-4 py-3 dark:border-gray-800">
                    {{ $vendors->links() }}
                </div>
            @endif
        </x-admin.vendor.card>
    </x-admin.vendor.shell>
</x-admin-layout>
