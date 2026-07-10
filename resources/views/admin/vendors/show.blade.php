<x-admin-layout>
    <div class="mx-auto max-w-6xl space-y-6">
        @if(session('success'))
            <div class="rounded-lg border border-green-200 bg-green-50 p-4 text-sm text-green-800">{{ session('success') }}</div>
        @endif

        <x-admin.marketplace-nav />

        {{-- Header --}}
        <div class="flex flex-wrap items-start justify-between gap-4 rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <div class="flex items-start gap-4">
                @if($vendor->logo_url)
                    <img src="{{ $vendor->logo_url }}" alt="" class="h-16 w-16 rounded-xl border object-cover" />
                @else
                    <div class="flex h-16 w-16 items-center justify-center rounded-xl bg-indigo-100 text-xl font-semibold text-indigo-600 dark:bg-indigo-900/40 dark:text-indigo-300">{{ strtoupper(substr($vendor->profile?->business_name ?? 'V', 0, 1)) }}</div>
                @endif
                <div>
                    <h1 class="text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ $vendor->profile?->business_name }}</h1>
                    <p class="mt-1 text-sm text-gray-500">{{ $vendor->profile?->owner_name }} · {{ $vendor->profile?->email }}</p>
                    <div class="mt-2 flex flex-wrap items-center gap-2 text-xs">
                        @php
                            $statusColors = [
                                'approved' => 'bg-green-100 text-green-800',
                                'pending' => 'bg-yellow-100 text-yellow-800',
                                'under_review' => 'bg-blue-100 text-blue-800',
                                'suspended' => 'bg-amber-100 text-amber-800',
                                'rejected' => 'bg-red-100 text-red-800',
                                'disabled' => 'bg-gray-100 text-gray-700',
                            ];
                        @endphp
                        <span class="rounded-full px-2.5 py-0.5 font-medium {{ $statusColors[$vendor->status] ?? 'bg-gray-100 text-gray-700' }}">{{ $vendor->statusEnum()->label() }}</span>
                        <span class="text-gray-500">Onboarding {{ $application['completion_percent'] ?? 0 }}%</span>
                        <span class="text-gray-500">Commission: {{ $vendor->commission_rate !== null ? $vendor->commission_rate.'%' : 'Platform default' }}</span>
                        @if($vendor->approved_at)
                            <span class="text-gray-500">Approved {{ $vendor->approved_at->format('M j, Y') }}</span>
                        @endif
                    </div>
                </div>
            </div>
            <div class="flex flex-wrap gap-2">
                @if(in_array($vendor->status, ['pending', 'under_review']))
                    <form method="POST" action="{{ route('admin.vendors.approve', $vendor) }}">@csrf<button type="submit" class="rounded-lg bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700">Approve vendor</button></form>
                    <form method="POST" action="{{ route('admin.vendors.reject', $vendor) }}" class="inline-flex flex-wrap items-center gap-2">@csrf
                        <input name="reason" required placeholder="Rejection reason" class="min-w-[200px] rounded-lg border-gray-300 text-sm" />
                        <button type="submit" class="rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700">Reject</button>
                    </form>
                @endif
                <a href="{{ route('admin.vendors.edit', $vendor) }}" class="rounded-lg border px-4 py-2 text-sm font-medium hover:bg-gray-50 dark:hover:bg-gray-900">Edit profile</a>
                @if($vendor->isApproved())
                    <a href="{{ route('admin.vendors.analytics', $vendor) }}" class="rounded-lg border border-indigo-200 px-4 py-2 text-sm font-medium text-indigo-600 hover:bg-indigo-50">Analytics</a>
                @endif
            </div>
        </div>

        {{-- KPI cards --}}
        <div class="grid grid-cols-2 gap-3 md:grid-cols-3 lg:grid-cols-6">
            @foreach([
                ['label' => 'Revenue', 'value' => 'AED '.number_format($metrics['revenue'] ?? 0, 2), 'accent' => 'text-amber-600'],
                ['label' => 'Total orders', 'value' => number_format($metrics['total_orders'] ?? 0), 'accent' => 'text-purple-600'],
                ['label' => 'Pending orders', 'value' => number_format($metrics['pending_orders'] ?? 0), 'accent' => 'text-yellow-600'],
                ['label' => 'Products', 'value' => number_format($metrics['total_products'] ?? 0), 'accent' => 'text-blue-600'],
                ['label' => 'Active products', 'value' => number_format($metrics['active_products'] ?? 0), 'accent' => 'text-green-600'],
                ['label' => 'Low stock', 'value' => number_format($metrics['low_stock_products'] ?? 0), 'accent' => 'text-red-600'],
            ] as $card)
                <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-500">{{ $card['label'] }}</p>
                    <p class="mt-1 text-xl font-semibold {{ $card['accent'] }}">{{ $card['value'] }}</p>
                </div>
            @endforeach
        </div>

        {{-- Secondary stats --}}
        <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
            <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
                <p class="text-xs text-gray-500 uppercase">Net earnings</p>
                <p class="mt-1 text-lg font-semibold text-green-600">AED {{ number_format($statistics['net_earnings'] ?? 0, 2) }}</p>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
                <p class="text-xs text-gray-500 uppercase">Delivered</p>
                <p class="mt-1 text-lg font-semibold">{{ number_format($statistics['completed_orders'] ?? 0) }}</p>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
                <p class="text-xs text-gray-500 uppercase">Customers</p>
                <p class="mt-1 text-lg font-semibold">{{ number_format($statistics['unique_customers'] ?? 0) }}</p>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
                <p class="text-xs text-gray-500 uppercase">Avg. order value</p>
                <p class="mt-1 text-lg font-semibold">AED {{ number_format($statistics['average_order_value'] ?? 0, 2) }}</p>
            </div>
        </div>

        {{-- Quick links --}}
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('admin.marketplace.products.index', ['vendor_id' => $vendor->id]) }}" class="rounded-lg border px-4 py-2 text-sm font-medium hover:bg-gray-50 dark:hover:bg-gray-900">All products</a>
            <a href="{{ route('admin.marketplace.orders.index', ['vendor_id' => $vendor->id]) }}" class="rounded-lg border px-4 py-2 text-sm font-medium hover:bg-gray-50 dark:hover:bg-gray-900">All orders</a>
            @if($vendor->status === 'approved')
                <form method="POST" action="{{ route('admin.vendors.suspend', $vendor) }}">@csrf<button class="rounded-lg bg-amber-600 px-4 py-2 text-sm font-medium text-white">Suspend</button></form>
                <form method="POST" action="{{ route('admin.vendors.disable', $vendor) }}">@csrf<button class="rounded-lg bg-gray-700 px-4 py-2 text-sm font-medium text-white">Disable</button></form>
            @endif
            @if(in_array($vendor->status, ['suspended', 'rejected', 'disabled']))
                <form method="POST" action="{{ route('admin.vendors.activate', $vendor) }}">@csrf<button class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white">Reactivate</button></form>
            @endif
            @if($vendor->status === 'pending')
                <form method="POST" action="{{ route('admin.vendors.under-review', $vendor) }}">@csrf<button class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white">Mark under review</button></form>
            @endif
        </div>

        {{-- Recent products & orders --}}
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <div class="flex items-center justify-between border-b border-gray-200 px-4 py-3 dark:border-gray-700">
                    <h2 class="font-semibold text-gray-900 dark:text-gray-100">Recent products</h2>
                    <a href="{{ route('admin.marketplace.products.index', ['vendor_id' => $vendor->id]) }}" class="text-sm text-indigo-600 hover:underline">View all</a>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-900/40">
                            <tr>
                                <th class="px-4 py-2 text-left font-medium text-gray-500">Product</th>
                                <th class="px-4 py-2 text-left font-medium text-gray-500">Status</th>
                                <th class="px-4 py-2 text-right font-medium text-gray-500">Stock</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            @forelse($recentProducts as $vp)
                                <tr>
                                    <td class="px-4 py-3 font-medium text-gray-900 dark:text-gray-100">{{ $vp->product?->name ?? '—' }}</td>
                                    <td class="px-4 py-3 capitalize text-gray-600">{{ $vp->status }} · {{ $vp->approval_status }}</td>
                                    <td class="px-4 py-3 text-right text-gray-600">{{ $vp->inventory?->quantity ?? '—' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="px-4 py-8 text-center text-gray-500">No products yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <div class="flex items-center justify-between border-b border-gray-200 px-4 py-3 dark:border-gray-700">
                    <h2 class="font-semibold text-gray-900 dark:text-gray-100">Recent orders</h2>
                    <a href="{{ route('admin.marketplace.orders.index', ['vendor_id' => $vendor->id]) }}" class="text-sm text-indigo-600 hover:underline">View all</a>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-900/40">
                            <tr>
                                <th class="px-4 py-2 text-left font-medium text-gray-500">Order</th>
                                <th class="px-4 py-2 text-left font-medium text-gray-500">Customer</th>
                                <th class="px-4 py-2 text-left font-medium text-gray-500">Status</th>
                                <th class="px-4 py-2 text-right font-medium text-gray-500">Total</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            @forelse($recentOrders as $order)
                                <tr>
                                    <td class="px-4 py-3 font-medium text-indigo-600">#{{ $order['order_id'] }}</td>
                                    <td class="px-4 py-3 text-gray-600">{{ $order['customer_name'] ?? 'Guest' }}</td>
                                    <td class="px-4 py-3 capitalize text-gray-600">{{ str_replace('_', ' ', $order['status']) }}</td>
                                    <td class="px-4 py-3 text-right font-medium">AED {{ number_format($order['total_amount'], 2) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="px-4 py-8 text-center text-gray-500">No orders yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        @if(!empty($application))
            <div class="rounded-xl border border-gray-200 bg-white p-6 text-sm shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <h2 class="mb-3 font-semibold text-gray-900 dark:text-gray-100">Application checklist</h2>
                <div class="flex flex-wrap gap-4">
                    <span>Profile: {{ ($application['profile_complete'] ?? false) ? '✓' : '—' }}</span>
                    <span>Categories: {{ ($application['categories_complete'] ?? false) ? '✓' : '—' }}</span>
                    <span>Documents: {{ ($application['documents_complete'] ?? false) ? '✓' : '—' }}</span>
                    <span>Terms: {{ ($application['terms_accepted'] ?? false) ? '✓' : '—' }}</span>
                    @if($vendor->categories->isNotEmpty())
                        <span>Selected: {{ $vendor->categories->pluck('name')->join(', ') }}</span>
                    @endif
                </div>
                @if(!empty($application['missing_profile_fields']))
                    <p class="mt-3 text-xs text-amber-600">Missing fields: {{ implode(', ', $application['missing_profile_fields']) }}</p>
                @endif
            </div>
        @endif

        @php $p = $vendor->profile; @endphp
        <div class="space-y-6 rounded-xl border border-gray-200 bg-white p-6 text-sm shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <div>
                <h2 class="mb-3 font-semibold text-gray-900 dark:text-gray-100">Business information</h2>
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <div><span class="text-gray-500">Company Name</span><p class="font-medium">{{ $p?->business_name ?? '—' }}</p></div>
                    <div><span class="text-gray-500">Authorized Person</span><p class="font-medium">{{ $p?->owner_name ?? '—' }}</p></div>
                    <div><span class="text-gray-500">Vendor Type</span><p>{{ $p?->vendor_type_label ?? '—' }}</p></div>
                    <div><span class="text-gray-500">Email</span><p>{{ $p?->email ?? '—' }}</p></div>
                    <div><span class="text-gray-500">Phone</span><p>{{ $p?->phone ?? '—' }}</p></div>
                    <div><span class="text-gray-500">Trade License No.</span><p>{{ $p?->trade_license_number ?? '—' }}</p></div>
                    <div><span class="text-gray-500">VAT Number</span><p>{{ $p?->tax_vat_number ?? '—' }}</p></div>
                    <div><span class="text-gray-500">Emirate</span><p>{{ $p?->emirate ?? '—' }}</p></div>
                    <div><span class="text-gray-500">City</span><p>{{ $p?->city ?? '—' }}</p></div>
                    <div class="sm:col-span-2 lg:col-span-3"><span class="text-gray-500">Address</span><p>{{ $p?->address ?? '—' }}</p></div>
                    <div class="sm:col-span-2 lg:col-span-3"><span class="text-gray-500">Google Maps Location</span>
                        <p>@if($p?->google_maps_location)<a href="{{ \Illuminate\Support\Str::startsWith($p->google_maps_location, 'http') ? $p->google_maps_location : 'https://maps.google.com/?q='.urlencode($p->google_maps_location) }}" target="_blank" class="text-indigo-600 hover:underline">{{ $p->google_maps_location }}</a>@else — @endif</p>
                    </div>
                </div>
            </div>
            <div class="border-t border-gray-100 pt-4 dark:border-gray-700">
                <h2 class="mb-3 font-semibold text-gray-900 dark:text-gray-100">Bank details</h2>
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <div><span class="text-gray-500">Bank Name</span><p>{{ $p?->bank_name ?? '—' }}</p></div>
                    <div><span class="text-gray-500">IBAN</span><p>{{ $p?->iban ?? '—' }}</p></div>
                    <div><span class="text-gray-500">Account Holder</span><p>{{ $p?->account_holder_name ?? '—' }}</p></div>
                </div>
            </div>
            <div class="border-t border-gray-100 pt-4 dark:border-gray-700">
                <h2 class="mb-3 font-semibold text-gray-900 dark:text-gray-100">Operations</h2>
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <div><span class="text-gray-500">Delivery Radius</span><p>{{ $p?->delivery_radius !== null ? $p->delivery_radius.' km' : '—' }}</p></div>
                    <div><span class="text-gray-500">Min. Order Amount</span><p>{{ $p?->minimum_order_amount !== null ? number_format((float) $p->minimum_order_amount, 2).' AED' : '—' }}</p></div>
                    <div><span class="text-gray-500">Operating Hours</span><p>{{ $p?->operating_hours ?? '—' }}</p></div>
                    <div><span class="text-gray-500">Years in Business</span><p>{{ $p?->years_in_business ?? '—' }}</p></div>
                    <div><span class="text-gray-500">Terms Accepted</span><p>{{ $p?->terms_accepted_at ? $p->terms_accepted_at->format('Y-m-d H:i') : 'No' }}</p></div>
                </div>
            </div>
            @if($p?->description)
                <div class="border-t border-gray-100 pt-4 dark:border-gray-700">
                    <span class="text-gray-500">Description</span><p class="mt-1">{{ $p->description }}</p>
                </div>
            @endif
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <h2 class="mb-4 font-semibold text-gray-900 dark:text-gray-100">Business documents</h2>
            @if($vendor->documents->isEmpty())
                <p class="text-sm text-gray-500">No documents uploaded yet.</p>
            @else
                <div class="space-y-4">
                    @foreach($vendor->documents as $doc)
                        <div class="flex flex-wrap items-start justify-between gap-4 border-b border-gray-100 pb-4 dark:border-gray-700">
                            <div>
                                <p class="text-sm font-medium">{{ str_replace('_', ' ', ucfirst($doc->type)) }}</p>
                                <p class="text-xs text-gray-500">{{ $doc->original_name }} · {{ ucfirst($doc->verification_status) }}</p>
                                @if($doc->admin_notes)<p class="mt-1 text-xs text-gray-600">{{ $doc->admin_notes }}</p>@endif
                                <a href="{{ $doc->file_url }}" target="_blank" class="mt-1 inline-block text-sm text-indigo-600 hover:underline">View document</a>
                            </div>
                            <form method="POST" action="{{ route('admin.vendors.documents.verify', [$vendor, $doc->id]) }}" class="flex flex-wrap items-end gap-2">
                                @csrf
                                <select name="verification_status" class="rounded-lg border-gray-300 text-sm">
                                    <option value="verified">Verified</option>
                                    <option value="rejected">Rejected</option>
                                </select>
                                <input name="admin_notes" placeholder="Notes" class="rounded-lg border-gray-300 text-sm" />
                                <button class="rounded-lg bg-gray-900 px-3 py-1.5 text-sm text-white">Save</button>
                            </form>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <form method="POST" action="{{ route('admin.marketplace.settings.vendor-commission', $vendor) }}" class="flex flex-wrap items-end gap-2 rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
            @csrf
            <div>
                <label class="text-xs text-gray-500">Custom commission %</label>
                <input type="number" name="commission_rate" step="0.01" min="0" max="100" value="{{ $vendor->commission_rate }}" placeholder="Use platform default" class="mt-1 rounded-lg border-gray-300 text-sm" />
            </div>
            <button class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white">Update commission</button>
        </form>

        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <h2 class="mb-3 font-semibold text-gray-900 dark:text-gray-100">Approval history</h2>
            <ul class="space-y-2 text-sm">
                @foreach($vendor->approvalLogs as $log)
                    <li class="text-gray-600">{{ $log->created_at?->format('Y-m-d H:i') }} — {{ $log->action }} ({{ $log->old_status }} → {{ $log->new_status }})</li>
                @endforeach
            </ul>
        </div>

        <div class="rounded-xl border border-red-200 bg-red-50/50 p-6 dark:border-red-900 dark:bg-red-900/10">
            <h2 class="mb-2 font-semibold text-red-800 dark:text-red-300">Permanent delete</h2>
            <p class="mb-4 text-sm text-red-700 dark:text-red-400">Removes vendor, user account, and documents. Cannot be undone.</p>
            <form method="POST" action="{{ route('admin.vendors.destroy', $vendor) }}" class="flex flex-wrap gap-2">
                @csrf @method('DELETE')
                <button type="submit" class="rounded-lg bg-red-700 px-4 py-2 text-sm font-medium text-white">Delete permanently</button>
            </form>
        </div>
    </div>
</x-admin-layout>
