<x-admin-layout>
    <div class="space-y-6 max-w-5xl">
        @if(session('success'))<div class="p-4 bg-green-50 text-green-800 text-sm rounded-lg">{{ session('success') }}</div>@endif
        <x-admin.marketplace-nav />
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1 class="text-xl font-semibold text-gray-900 dark:text-gray-100">{{ $vendor->profile?->business_name }}</h1>
                <p class="text-sm text-gray-500 mt-1">Status: <strong>{{ $vendor->statusEnum()->label() }}</strong> · Onboarding: {{ $application['completion_percent'] ?? 0 }}% · Commission: {{ $vendor->commission_rate !== null ? $vendor->commission_rate.'%' : 'Platform default' }}</p>
            </div>
            <a href="{{ route('admin.vendors.edit', $vendor) }}" class="px-4 py-2 text-sm border rounded-lg">Edit profile</a>
        </div>

        <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
            @foreach([
                ['label' => 'Products', 'value' => $statistics['total_products'] ?? 0],
                ['label' => 'Active products', 'value' => $statistics['active_products'] ?? 0],
                ['label' => 'Orders', 'value' => $statistics['total_orders'] ?? 0],
                ['label' => 'Revenue', 'value' => number_format($statistics['revenue'] ?? 0, 2)],
            ] as $card)
                <div class="bg-white dark:bg-gray-800 rounded-xl border p-4">
                    <p class="text-xs text-gray-500 uppercase">{{ $card['label'] }}</p>
                    <p class="text-xl font-semibold mt-1">{{ $card['value'] }}</p>
                </div>
            @endforeach
        </div>

        @if(!empty($application))
            <div class="bg-white dark:bg-gray-800 rounded-xl border p-6 text-sm">
                <h2 class="font-medium mb-3">Application checklist</h2>
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
        <div class="bg-white dark:bg-gray-800 rounded-xl border p-6 space-y-6 text-sm">
            <div>
                <h2 class="font-medium mb-3">Business information</h2>
                <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
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
            <div class="border-t border-gray-100 dark:border-gray-700 pt-4">
                <h2 class="font-medium mb-3">Bank details</h2>
                <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    <div><span class="text-gray-500">Bank Name</span><p>{{ $p?->bank_name ?? '—' }}</p></div>
                    <div><span class="text-gray-500">IBAN</span><p>{{ $p?->iban ?? '—' }}</p></div>
                    <div><span class="text-gray-500">Account Holder</span><p>{{ $p?->account_holder_name ?? '—' }}</p></div>
                </div>
            </div>
            <div class="border-t border-gray-100 dark:border-gray-700 pt-4">
                <h2 class="font-medium mb-3">Operations</h2>
                <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    <div><span class="text-gray-500">Delivery Radius</span><p>{{ $p?->delivery_radius !== null ? $p->delivery_radius.' km' : '—' }}</p></div>
                    <div><span class="text-gray-500">Min. Order Amount</span><p>{{ $p?->minimum_order_amount !== null ? number_format((float) $p->minimum_order_amount, 2).' AED' : '—' }}</p></div>
                    <div><span class="text-gray-500">Operating Hours</span><p>{{ $p?->operating_hours ?? '—' }}</p></div>
                    <div><span class="text-gray-500">Terms Accepted</span><p>{{ $p?->terms_accepted_at ? $p->terms_accepted_at->format('Y-m-d H:i') : 'No' }}</p></div>
                </div>
            </div>
            @if($p?->description)
                <div class="border-t border-gray-100 dark:border-gray-700 pt-4">
                    <span class="text-gray-500">Description</span><p class="mt-1">{{ $p->description }}</p>
                </div>
            @endif
        </div>

        <div class="flex flex-wrap gap-2">
            @if(in_array($vendor->status, ['pending', 'under_review']))
                <form method="POST" action="{{ route('admin.vendors.approve', $vendor) }}">@csrf<button class="px-4 py-2 bg-green-600 text-white text-sm rounded-lg">Approve</button></form>
                <form method="POST" action="{{ route('admin.vendors.reject', $vendor) }}" class="inline-flex gap-2">@csrf
                    <input name="reason" required placeholder="Rejection reason" class="rounded-lg border-gray-300 text-sm" />
                    <button class="px-4 py-2 bg-red-600 text-white text-sm rounded-lg">Reject</button>
                </form>
            @endif
            @if($vendor->status === 'pending')
                <form method="POST" action="{{ route('admin.vendors.under-review', $vendor) }}">@csrf<button class="px-4 py-2 bg-blue-600 text-white text-sm rounded-lg">Mark under review</button></form>
            @endif
            @if($vendor->status === 'approved')
                <form method="POST" action="{{ route('admin.vendors.suspend', $vendor) }}">@csrf<button class="px-4 py-2 bg-amber-600 text-white text-sm rounded-lg">Suspend</button></form>
                <form method="POST" action="{{ route('admin.vendors.disable', $vendor) }}">@csrf<button class="px-4 py-2 bg-gray-700 text-white text-sm rounded-lg">Disable</button></form>
            @endif
            @if(in_array($vendor->status, ['suspended', 'rejected', 'disabled']))
                <form method="POST" action="{{ route('admin.vendors.activate', $vendor) }}">@csrf<button class="px-4 py-2 bg-indigo-600 text-white text-sm rounded-lg">Reactivate</button></form>
            @endif
            <a href="{{ route('admin.marketplace.products.index', ['vendor_id' => $vendor->id]) }}" class="px-4 py-2 border text-sm rounded-lg">View products</a>
            <a href="{{ route('admin.marketplace.orders.index', ['vendor_id' => $vendor->id]) }}" class="px-4 py-2 border text-sm rounded-lg">View orders</a>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-xl border p-6">
            <h2 class="font-medium mb-4">Business documents</h2>
            @if($vendor->documents->isEmpty())
                <p class="text-sm text-gray-500">No documents uploaded yet.</p>
            @else
                <div class="space-y-4">
                    @foreach($vendor->documents as $doc)
                        <div class="flex flex-wrap items-start justify-between gap-4 border-b border-gray-100 dark:border-gray-700 pb-4">
                            <div>
                                <p class="font-medium text-sm">{{ str_replace('_', ' ', ucfirst($doc->type)) }}</p>
                                <p class="text-xs text-gray-500">{{ $doc->original_name }} · {{ ucfirst($doc->verification_status) }}</p>
                                @if($doc->admin_notes)<p class="text-xs text-gray-600 mt-1">{{ $doc->admin_notes }}</p>@endif
                                <a href="{{ $doc->file_url }}" target="_blank" class="text-sm text-indigo-600 hover:underline mt-1 inline-block">View document</a>
                            </div>
                            <form method="POST" action="{{ route('admin.vendors.documents.verify', [$vendor, $doc->id]) }}" class="flex flex-wrap gap-2 items-end">
                                @csrf
                                <select name="verification_status" class="rounded-lg border-gray-300 text-sm">
                                    <option value="verified">Verified</option>
                                    <option value="rejected">Rejected</option>
                                </select>
                                <input name="admin_notes" placeholder="Notes" class="rounded-lg border-gray-300 text-sm" />
                                <button class="px-3 py-1.5 bg-gray-900 text-white text-sm rounded-lg">Save</button>
                            </form>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <form method="POST" action="{{ route('admin.marketplace.settings.vendor-commission', $vendor) }}" class="bg-white dark:bg-gray-800 rounded-xl border p-4 flex flex-wrap gap-2 items-end">
            @csrf
            <div><label class="text-xs text-gray-500">Custom commission %</label>
                <input type="number" name="commission_rate" step="0.01" min="0" max="100" value="{{ $vendor->commission_rate }}" placeholder="Use platform default" class="rounded-lg border-gray-300 text-sm mt-1" />
            </div>
            <button class="px-4 py-2 bg-indigo-600 text-white text-sm rounded-lg">Update commission</button>
        </form>

        <div class="bg-white dark:bg-gray-800 rounded-xl border p-4">
            <h2 class="font-medium mb-3">Approval history</h2>
            <ul class="text-sm space-y-2">
                @foreach($vendor->approvalLogs as $log)
                    <li>{{ $log->created_at?->format('Y-m-d H:i') }} — {{ $log->action }} ({{ $log->old_status }} → {{ $log->new_status }})</li>
                @endforeach
            </ul>
        </div>

        <div class="border border-red-200 dark:border-red-900 rounded-xl p-6 bg-red-50/50 dark:bg-red-900/10">
            <h2 class="font-medium text-red-800 dark:text-red-300 mb-2">Permanent delete</h2>
            <p class="text-sm text-red-700 dark:text-red-400 mb-4">Removes vendor, user account, and documents. Cannot be undone.</p>
            <form method="POST" action="{{ route('admin.vendors.destroy', $vendor) }}" class="flex flex-wrap gap-2" onsubmit="return confirm('Permanently delete this vendor?')">
                @csrf @method('DELETE')
                <input name="confirm" required placeholder="Type DELETE" class="rounded-lg border-gray-300 text-sm" />
                <button class="px-4 py-2 bg-red-700 text-white text-sm rounded-lg">Delete permanently</button>
            </form>
        </div>
    </div>
</x-admin-layout>
