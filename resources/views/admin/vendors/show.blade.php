<x-admin-layout>
    <div class="space-y-6 max-w-5xl">
        @if(session('success'))<div class="p-4 bg-green-50 text-green-800 text-sm rounded-lg">{{ session('success') }}</div>@endif
        <x-admin.marketplace-nav />
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1 class="text-xl font-semibold text-gray-900 dark:text-gray-100">{{ $vendor->profile?->business_name }}</h1>
                <p class="text-sm text-gray-500 mt-1">Status: <strong>{{ ucfirst($vendor->status) }}</strong> · Commission: {{ $vendor->commission_rate !== null ? $vendor->commission_rate.'%' : 'Platform default' }}</p>
            </div>
            <a href="{{ route('admin.vendors.edit', $vendor) }}" class="px-4 py-2 text-sm border rounded-lg">Edit profile</a>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-xl border p-6 grid sm:grid-cols-2 gap-4 text-sm">
            <div><span class="text-gray-500">Owner</span><p class="font-medium">{{ $vendor->profile?->owner_name }}</p></div>
            <div><span class="text-gray-500">Email</span><p>{{ $vendor->profile?->email }}</p></div>
            <div><span class="text-gray-500">Phone</span><p>{{ $vendor->profile?->phone ?? '—' }}</p></div>
            <div><span class="text-gray-500">Tax/VAT</span><p>{{ $vendor->profile?->tax_vat_number ?? '—' }}</p></div>
            <div class="sm:col-span-2"><span class="text-gray-500">Address</span><p>{{ $vendor->profile?->address ?? '—' }}</p></div>
        </div>

        <div class="flex flex-wrap gap-2">
            @if($vendor->status === 'pending')
                <form method="POST" action="{{ route('admin.vendors.approve', $vendor) }}">@csrf<button class="px-4 py-2 bg-green-600 text-white text-sm rounded-lg">Approve</button></form>
                <form method="POST" action="{{ route('admin.vendors.reject', $vendor) }}" class="inline-flex gap-2">@csrf
                    <input name="reason" required placeholder="Rejection reason" class="rounded-lg border-gray-300 text-sm" />
                    <button class="px-4 py-2 bg-red-600 text-white text-sm rounded-lg">Reject</button>
                </form>
            @endif
            @if($vendor->status === 'approved')
                <form method="POST" action="{{ route('admin.vendors.suspend', $vendor) }}">@csrf<button class="px-4 py-2 bg-amber-600 text-white text-sm rounded-lg">Suspend</button></form>
            @endif
            @if(in_array($vendor->status, ['suspended', 'rejected']))
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
