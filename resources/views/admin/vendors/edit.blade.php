<x-admin-layout>
    <div class="max-w-xl space-y-6">
        <x-admin.marketplace-nav />
        <h1 class="text-xl font-semibold">Edit vendor</h1>
        <form method="POST" action="{{ route('admin.vendors.update', $vendor) }}" enctype="multipart/form-data" class="space-y-4 bg-white dark:bg-gray-800 rounded-xl border p-6">
            @csrf @method('PUT')
            <div><label class="text-sm font-medium">Business name</label><input name="business_name" value="{{ old('business_name', $vendor->profile?->business_name) }}" required class="mt-1 w-full rounded-lg border-gray-300" /></div>
            <div><label class="text-sm font-medium">Owner name</label><input name="owner_name" value="{{ old('owner_name', $vendor->profile?->owner_name) }}" required class="mt-1 w-full rounded-lg border-gray-300" /></div>
            <div><label class="text-sm font-medium">Email</label><input type="email" name="email" value="{{ old('email', $vendor->profile?->email) }}" required class="mt-1 w-full rounded-lg border-gray-300" /></div>
            <div><label class="text-sm font-medium">Phone</label><input name="phone" value="{{ old('phone', $vendor->profile?->phone) }}" class="mt-1 w-full rounded-lg border-gray-300" /></div>
            <div><label class="text-sm font-medium">Address</label><textarea name="address" class="mt-1 w-full rounded-lg border-gray-300" rows="2">{{ old('address', $vendor->profile?->address) }}</textarea></div>
            <div><label class="text-sm font-medium">Tax/VAT</label><input name="tax_vat_number" value="{{ old('tax_vat_number', $vendor->profile?->tax_vat_number) }}" class="mt-1 w-full rounded-lg border-gray-300" /></div>
            <div><label class="text-sm font-medium">Description</label><textarea name="description" class="mt-1 w-full rounded-lg border-gray-300" rows="3">{{ old('description', $vendor->profile?->description) }}</textarea></div>
            <div><label class="text-sm font-medium">Custom commission % (blank = platform default)</label><input type="number" name="commission_rate" step="0.01" min="0" max="100" value="{{ old('commission_rate', $vendor->commission_rate) }}" class="mt-1 w-full rounded-lg border-gray-300" /></div>
            <div><label class="text-sm font-medium">Logo</label><input type="file" name="logo" accept="image/*" class="mt-1 w-full text-sm" /></div>
            <button class="px-4 py-2 bg-gray-900 text-white text-sm rounded-lg">Save</button>
        </form>
    </div>
</x-admin-layout>
