<x-admin-layout>
    <div class="space-y-6" x-data="{ bulkOpen: false, selected: [] }">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight text-gray-900 dark:text-gray-100">{{ $pageTitle ?? 'Vendor Management' }}</h1>
                <p class="mt-1 text-sm text-gray-500">{{ $pageSubtitle ?? 'Professional multi-vendor marketplace administration.' }}</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('admin.vendors.export', array_filter(['preset' => $listPreset ?? null] + request()->only(['search','status','sort','verified']))) }}"
                   class="inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200">
                    Export CSV
                </a>
                <button type="button" @click="bulkOpen = !bulkOpen"
                        class="inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-indigo-700">
                    Bulk Actions
                </button>
            </div>
        </div>

        <x-admin.vendor.nav />

        @if(session('success'))
            <div class="rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ session('error') }}</div>
        @endif

        <div class="grid grid-cols-2 gap-3 md:grid-cols-4 lg:grid-cols-7">
            @foreach([
                'total' => ['Total', 'text-gray-900'],
                'pending' => ['Pending', 'text-amber-600'],
                'under_review' => ['Under Review', 'text-sky-600'],
                'approved' => ['Approved', 'text-emerald-600'],
                'suspended' => ['Suspended', 'text-orange-600'],
                'rejected' => ['Rejected', 'text-rose-600'],
                'disabled' => ['Disabled', 'text-gray-500'],
            ] as $key => [$label, $color])
                <x-admin.vendor.kpi-card :label="$label" :value="number_format($stats[$key] ?? 0)" :accent="$color" />
            @endforeach
        </div>

        @if(($listPreset ?? null) === null && isset($recentRequests) && $recentRequests->isNotEmpty())
            <div class="rounded-2xl border border-amber-200/80 bg-amber-50/80 p-5 backdrop-blur dark:border-amber-900/40 dark:bg-amber-900/10">
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="font-semibold text-amber-900 dark:text-amber-200">Recent vendor requests</h2>
                    <a href="{{ route('admin.vendors.pending') }}" class="text-sm font-medium text-amber-800 underline">View all</a>
                </div>
                <div class="space-y-2">
                    @foreach($recentRequests as $req)
                        <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl bg-white/70 px-3 py-2 dark:bg-gray-900/40">
                            <span class="text-sm font-medium">{{ $req->profile?->business_name }}</span>
                            <a href="{{ route('admin.vendors.show', $req) }}" class="rounded-lg bg-indigo-600 px-3 py-1 text-xs font-medium text-white">Review</a>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        <div x-show="bulkOpen" x-cloak class="rounded-2xl border border-indigo-200 bg-indigo-50/70 p-4 dark:border-indigo-900 dark:bg-indigo-900/20">
            <form method="POST" action="{{ route('admin.vendors.bulk') }}" class="flex flex-wrap items-end gap-3">
                @csrf
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-600">Action</label>
                    <select name="action" class="rounded-lg border-gray-300 text-sm" required>
                        <option value="approve">Approve</option>
                        <option value="suspend">Suspend</option>
                        <option value="activate">Activate</option>
                        <option value="disable">Disable</option>
                        <option value="delete">Delete permanently</option>
                    </select>
                </div>
                <div class="min-w-[240px] flex-1">
                    <label class="mb-1 block text-xs font-medium text-gray-600">Vendor IDs (comma-separated)</label>
                    <input type="text" name="vendor_ids" placeholder="1,2,3" class="w-full rounded-lg border-gray-300 text-sm"
                           x-bind:value="selected.join(',')" required />
                </div>
                <button class="rounded-lg bg-gray-900 px-4 py-2 text-sm font-medium text-white">Apply</button>
            </form>
        </div>

        <form method="GET" class="grid gap-3 rounded-2xl border border-gray-200/80 bg-white/70 p-4 backdrop-blur md:grid-cols-5 dark:border-gray-700 dark:bg-gray-900/60">
            <input name="search" value="{{ request('search') }}" placeholder="Search name, email, phone..." class="rounded-xl border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-800 md:col-span-2" />
            @if(empty($listPreset))
                <select name="status" class="rounded-xl border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-800">
                    <option value="">All statuses</option>
                    @foreach(\App\Enums\VendorStatus::cases() as $status)
                        <option value="{{ $status->value }}" @selected(request('status')===$status->value)>{{ $status->label() }}</option>
                    @endforeach
                </select>
            @endif
            <select name="verified" class="rounded-xl border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-800">
                <option value="">Verification</option>
                <option value="yes" @selected(request('verified')==='yes')>Verified</option>
                <option value="no" @selected(request('verified')==='no')>Not verified</option>
            </select>
            <select name="sort" class="rounded-xl border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-800">
                <option value="newest" @selected(($sort ?? 'newest') === 'newest')>Newest</option>
                <option value="oldest" @selected(($sort ?? '') === 'oldest')>Oldest</option>
                <option value="business" @selected(($sort ?? '') === 'business')>Business name</option>
                <option value="revenue" @selected(($sort ?? '') === 'revenue')>Highest revenue</option>
            </select>
            <button class="rounded-xl bg-gray-900 px-4 py-2 text-sm font-medium text-white md:col-span-5 md:max-w-[160px]">Apply filters</button>
        </form>

        <div class="overflow-hidden rounded-2xl border border-gray-200/80 bg-white/80 shadow-sm backdrop-blur dark:border-gray-700 dark:bg-gray-900/70">
            <div class="overflow-x-auto">
                <table class="min-w-[1200px] w-full divide-y divide-gray-200 text-sm dark:divide-gray-700">
                    <thead class="bg-gray-50/90 dark:bg-gray-900/60">
                        <tr>
                            <th class="px-4 py-3 text-left"><input type="checkbox" @change="selected = $event.target.checked ? {{ $vendors->pluck('id')->toJson() }} : []" /></th>
                            <th class="px-4 py-3 text-left font-semibold text-gray-500">Vendor</th>
                            <th class="px-4 py-3 text-left font-semibold text-gray-500">Contact</th>
                            <th class="px-4 py-3 text-left font-semibold text-gray-500">Store</th>
                            <th class="px-4 py-3 text-right font-semibold text-gray-500">Products</th>
                            <th class="px-4 py-3 text-right font-semibold text-gray-500">Orders</th>
                            <th class="px-4 py-3 text-right font-semibold text-gray-500">Revenue</th>
                            <th class="px-4 py-3 text-right font-semibold text-gray-500">Commission</th>
                            <th class="px-4 py-3 text-left font-semibold text-gray-500">Registered</th>
                            <th class="px-4 py-3 text-left font-semibold text-gray-500">Status</th>
                            <th class="px-4 py-3 text-right font-semibold text-gray-500">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse($vendors as $vendor)
                            @php
                                $m = $metricsMap[$vendor->id] ?? ['total_products'=>0,'active_products'=>0,'total_orders'=>0,'revenue'=>0,'commission_earned'=>0];
                                $verified = ($listService ?? null)?->isVerified($vendor) ?? false;
                            @endphp
                            <tr class="transition hover:bg-indigo-50/40 dark:hover:bg-indigo-900/10">
                                <td class="px-4 py-3"><input type="checkbox" value="{{ $vendor->id }}" x-model.number="selected" /></td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-3">
                                        @if($vendor->logo_url)
                                            <img src="{{ $vendor->logo_url }}" class="h-10 w-10 rounded-xl border object-cover" alt="" />
                                        @else
                                            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-indigo-100 text-sm font-bold text-indigo-600">{{ strtoupper(substr($vendor->profile?->business_name ?? 'V', 0, 1)) }}</div>
                                        @endif
                                        <div>
                                            <p class="font-semibold text-gray-900 dark:text-gray-100">{{ $vendor->profile?->owner_name ?? '—' }}</p>
                                            <p class="text-xs text-gray-500">ID #{{ $vendor->id }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <p>{{ $vendor->profile?->email }}</p>
                                    <p class="text-xs text-gray-500">{{ $vendor->profile?->phone ?? '—' }}</p>
                                </td>
                                <td class="px-4 py-3 font-medium">{{ $vendor->profile?->business_name }}</td>
                                <td class="px-4 py-3 text-right">
                                    <span class="font-semibold">{{ number_format($m['total_products']) }}</span>
                                    <span class="block text-xs text-gray-500">{{ $m['active_products'] }} active</span>
                                </td>
                                <td class="px-4 py-3 text-right font-semibold">{{ number_format($m['total_orders']) }}</td>
                                <td class="px-4 py-3 text-right font-semibold">AED {{ number_format($m['revenue'], 2) }}</td>
                                <td class="px-4 py-3 text-right">AED {{ number_format($m['commission_earned'], 2) }}</td>
                                <td class="px-4 py-3 text-gray-600">{{ $vendor->created_at?->format('M j, Y') }}</td>
                                <td class="px-4 py-3">
                                    <div class="flex flex-col gap-1">
                                        <x-admin.vendor.status-badge :status="$vendor->status" />
                                        @if($verified)
                                            <span class="inline-flex w-fit rounded-full bg-emerald-50 px-2 py-0.5 text-[10px] font-semibold uppercase text-emerald-700 ring-1 ring-emerald-600/20">Verified</span>
                                        @else
                                            <span class="inline-flex w-fit rounded-full bg-gray-100 px-2 py-0.5 text-[10px] font-semibold uppercase text-gray-600">Unverified</span>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <a href="{{ route('admin.vendors.show', $vendor) }}" class="font-medium text-indigo-600 hover:text-indigo-800">View</a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="11" class="px-4 py-16 text-center text-gray-500">No vendors found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{ $vendors->links() }}
    </div>
</x-admin-layout>
