<x-admin-layout>
    <div class="space-y-4"
         x-data="{
            bulkOpen: false,
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
            isSelected(id) {
                return this.selected.includes(Number(id));
            },
            allSelected() {
                return this.pageIds.length > 0 && this.selected.length === this.pageIds.length;
            },
            clearSelection() {
                this.selected = [];
                this.bulkOpen = false;
            }
         }">

        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <h1 class="text-lg font-semibold text-gray-900 dark:text-gray-100">{{ $pageTitle ?? 'Vendor Management' }}</h1>
                <p class="mt-0.5 text-xs text-gray-500">{{ $pageSubtitle ?? 'Each row shows one vendor — products, orders & revenue belong to that vendor only.' }}</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('admin.vendors.export', array_filter(['preset' => $listPreset ?? null] + request()->only(['search','status','sort','verified']))) }}"
                   class="rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-xs font-medium text-gray-700 shadow-sm hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800">
                    Export CSV
                </a>
                <button type="button" @click="bulkOpen = !bulkOpen"
                        class="rounded-lg bg-indigo-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-indigo-700">
                    Bulk Actions
                </button>
            </div>
        </div>

        <x-admin.vendor.nav />

        @if(session('success'))
            <div class="rounded-lg border border-green-200 bg-green-50 px-3 py-2 text-xs text-green-800">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-800">{{ session('error') }}</div>
        @endif

        <div class="grid grid-cols-2 gap-2 md:grid-cols-4 lg:grid-cols-7">
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

        {{-- Selection toolbar --}}
        <div x-show="selected.length > 0" x-cloak
             class="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-indigo-200 bg-indigo-50 px-3 py-2 text-xs dark:border-indigo-800 dark:bg-indigo-900/30">
            <span class="font-medium text-indigo-900 dark:text-indigo-200">
                <span x-text="selected.length"></span> vendor(s) selected on this page
            </span>
            <div class="flex gap-2">
                <button type="button" @click="bulkOpen = true" class="rounded-md bg-indigo-600 px-2.5 py-1 text-[11px] font-medium text-white">Open bulk actions</button>
                <button type="button" @click="clearSelection()" class="rounded-md border border-gray-300 bg-white px-2.5 py-1 text-[11px] font-medium text-gray-700">Clear</button>
            </div>
        </div>

        @if(($listPreset ?? null) === null && isset($recentRequests) && $recentRequests->isNotEmpty())
            <div class="rounded-xl border border-amber-200 bg-amber-50/80 p-3 text-xs dark:border-amber-900/40 dark:bg-amber-900/10">
                <div class="mb-2 flex items-center justify-between">
                    <h2 class="font-semibold text-amber-900">Recent vendor requests</h2>
                    <a href="{{ route('admin.vendors.pending') }}" class="font-medium text-amber-800 underline">View all</a>
                </div>
                <div class="space-y-1.5">
                    @foreach($recentRequests as $req)
                        <div class="flex items-center justify-between gap-2 rounded-lg bg-white/70 px-2.5 py-1.5 dark:bg-gray-900/40">
                            <span class="font-medium">{{ $req->profile?->business_name }}</span>
                            <a href="{{ route('admin.vendors.show', $req) }}" class="rounded bg-indigo-600 px-2 py-0.5 text-[10px] font-medium text-white">Review</a>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        <div x-show="bulkOpen" x-cloak class="rounded-xl border border-indigo-200 bg-indigo-50/70 p-3 dark:border-indigo-900 dark:bg-indigo-900/20">
            <form method="POST" action="{{ route('admin.vendors.bulk') }}" class="flex flex-wrap items-end gap-2">
                @csrf
                <template x-for="id in selected" :key="id">
                    <input type="hidden" name="vendor_ids[]" :value="id" />
                </template>
                <div>
                    <label class="mb-0.5 block text-[10px] font-medium text-gray-600">Action</label>
                    <select name="action" class="rounded-lg border-gray-300 text-xs" required>
                        <option value="approve">Approve</option>
                        <option value="suspend">Suspend</option>
                        <option value="activate">Activate</option>
                        <option value="disable">Disable</option>
                        <option value="delete">Delete permanently</option>
                    </select>
                </div>
                <p class="text-[11px] text-gray-600" x-show="selected.length === 0">Select vendors using checkboxes first.</p>
                <button type="submit" class="rounded-lg bg-gray-900 px-3 py-1.5 text-xs font-medium text-white"
                        :disabled="selected.length === 0"
                        :class="selected.length === 0 ? 'opacity-50 cursor-not-allowed' : ''">
                    Apply to <span x-text="selected.length"></span> vendor(s)
                </button>
            </form>
        </div>

        <form method="GET" class="grid gap-2 rounded-xl border border-gray-200/80 bg-white/70 p-3 md:grid-cols-5 dark:border-gray-700 dark:bg-gray-900/60">
            <input name="search" value="{{ request('search') }}" placeholder="Search name, email, phone..." class="rounded-lg border-gray-300 text-xs dark:border-gray-600 dark:bg-gray-800 md:col-span-2" />
            @if(empty($listPreset))
                <select name="status" class="rounded-lg border-gray-300 text-xs dark:border-gray-600 dark:bg-gray-800">
                    <option value="">All statuses</option>
                    @foreach(\App\Enums\VendorStatus::cases() as $status)
                        <option value="{{ $status->value }}" @selected(request('status')===$status->value)>{{ $status->label() }}</option>
                    @endforeach
                </select>
            @endif
            <select name="verified" class="rounded-lg border-gray-300 text-xs dark:border-gray-600 dark:bg-gray-800">
                <option value="">Verification</option>
                <option value="yes" @selected(request('verified')==='yes')>Verified</option>
                <option value="no" @selected(request('verified')==='no')>Not verified</option>
            </select>
            <select name="sort" class="rounded-lg border-gray-300 text-xs dark:border-gray-600 dark:bg-gray-800">
                <option value="newest" @selected(($sort ?? 'newest') === 'newest')>Newest</option>
                <option value="oldest" @selected(($sort ?? '') === 'oldest')>Oldest</option>
                <option value="business" @selected(($sort ?? '') === 'business')>Business name</option>
                <option value="revenue" @selected(($sort ?? '') === 'revenue')>Highest revenue</option>
            </select>
            <button class="rounded-lg bg-gray-900 px-3 py-1.5 text-xs font-medium text-white md:col-span-5 md:max-w-[140px]">Apply filters</button>
        </form>

        <div class="overflow-hidden rounded-xl border border-gray-200/80 bg-white/80 shadow-sm dark:border-gray-700 dark:bg-gray-900/70">
            <div class="overflow-x-auto">
                <table class="min-w-[980px] w-full divide-y divide-gray-200 text-xs dark:divide-gray-700">
                    <thead class="bg-gray-50/90 text-[10px] uppercase tracking-wide text-gray-500 dark:bg-gray-900/60">
                        <tr>
                            <th class="w-10 px-3 py-2 text-left">
                                <input type="checkbox" class="rounded border-gray-300"
                                       :checked="allSelected()"
                                       @change="toggleAll($event.target.checked)" />
                            </th>
                            <th class="px-3 py-2 text-left">Vendor / Store</th>
                            <th class="px-3 py-2 text-left">Contact</th>
                            <th class="px-3 py-2 text-left">Performance (this vendor)</th>
                            <th class="px-3 py-2 text-left">Status</th>
                            <th class="px-3 py-2 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse($vendors as $vendor)
                            @php
                                $m = $metricsMap[$vendor->id] ?? ['total_products'=>0,'active_products'=>0,'total_orders'=>0,'revenue'=>0,'commission_earned'=>0];
                                $verified = ($listService ?? null)?->isVerified($vendor) ?? false;
                                $businessName = $vendor->profile?->business_name ?? '—';
                            @endphp
                            <tr class="transition"
                                :class="isSelected({{ $vendor->id }}) ? 'bg-indigo-50/80 ring-1 ring-inset ring-indigo-200 dark:bg-indigo-900/20 dark:ring-indigo-800' : 'hover:bg-gray-50/80 dark:hover:bg-gray-800/40'">
                                <td class="px-3 py-2.5 align-top">
                                    <input type="checkbox" class="rounded border-gray-300"
                                           :checked="isSelected({{ $vendor->id }})"
                                           @change="toggleOne({{ $vendor->id }}, $event.target.checked)" />
                                </td>
                                <td class="px-3 py-2.5 align-top">
                                    <div class="flex items-start gap-2.5">
                                        @if($vendor->logo_url)
                                            <img src="{{ $vendor->logo_url }}" class="h-8 w-8 rounded-lg border object-cover" alt="" />
                                        @else
                                            <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-indigo-100 text-[11px] font-bold text-indigo-600">{{ strtoupper(substr($businessName, 0, 1)) }}</div>
                                        @endif
                                        <div class="min-w-0">
                                            <p class="truncate font-semibold text-gray-900 dark:text-gray-100">{{ $businessName }}</p>
                                            <p class="text-[11px] text-gray-600">{{ $vendor->profile?->owner_name ?? '—' }}</p>
                                            <p class="text-[10px] text-gray-400">Vendor ID #{{ $vendor->id }} · Joined {{ $vendor->created_at?->format('M j, Y') }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-3 py-2.5 align-top text-[11px]">
                                    <p class="text-gray-800 dark:text-gray-200">{{ $vendor->profile?->email }}</p>
                                    <p class="text-gray-500">{{ $vendor->profile?->phone ?? '—' }}</p>
                                </td>
                                <td class="px-3 py-2.5 align-top">
                                    <div class="inline-grid min-w-[200px] grid-cols-2 gap-x-4 gap-y-1 rounded-lg border border-gray-100 bg-gray-50/80 px-2.5 py-2 text-[11px] dark:border-gray-700 dark:bg-gray-900/40">
                                        <div>
                                            <span class="text-[10px] uppercase text-gray-400">Products</span>
                                            <p class="font-semibold text-gray-900 dark:text-gray-100">{{ number_format($m['total_products']) }} <span class="font-normal text-gray-500">({{ $m['active_products'] }} active)</span></p>
                                        </div>
                                        <div>
                                            <span class="text-[10px] uppercase text-gray-400">Orders</span>
                                            <p class="font-semibold text-gray-900 dark:text-gray-100">{{ number_format($m['total_orders']) }}</p>
                                        </div>
                                        <div>
                                            <span class="text-[10px] uppercase text-gray-400">Revenue</span>
                                            <p class="font-semibold text-emerald-700 dark:text-emerald-400">AED {{ number_format($m['revenue'], 2) }}</p>
                                        </div>
                                        <div>
                                            <span class="text-[10px] uppercase text-gray-400">Commission</span>
                                            <p class="font-semibold text-indigo-700 dark:text-indigo-400">AED {{ number_format($m['commission_earned'], 2) }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-3 py-2.5 align-top">
                                    <div class="flex flex-col items-start gap-1">
                                        <x-admin.vendor.status-badge :status="$vendor->status" />
                                        @if($verified)
                                            <span class="rounded-full bg-emerald-50 px-1.5 py-0.5 text-[9px] font-medium uppercase text-emerald-700 ring-1 ring-emerald-600/20">Verified</span>
                                        @else
                                            <span class="rounded-full bg-gray-100 px-1.5 py-0.5 text-[9px] font-medium uppercase text-gray-500">Unverified</span>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-3 py-2.5 align-top text-right">
                                    <div class="flex flex-col items-end gap-1">
                                        <a href="{{ route('admin.vendors.show', $vendor) }}" class="text-[11px] font-medium text-indigo-600 hover:text-indigo-800">Dashboard</a>
                                        <a href="{{ route('admin.vendors.products', $vendor) }}" class="text-[10px] text-gray-500 hover:text-indigo-600">Products</a>
                                        <a href="{{ route('admin.vendors.orders', $vendor) }}" class="text-[10px] text-gray-500 hover:text-indigo-600">Orders</a>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-4 py-12 text-center text-xs text-gray-500">No vendors found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <p class="text-[10px] text-gray-400">Tip: Each table row is one vendor. The performance box shows only that vendor's products, orders, and revenue.</p>

        {{ $vendors->links() }}
    </div>
</x-admin-layout>
