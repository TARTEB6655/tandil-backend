<x-admin-layout>
    <div class="max-w-2xl space-y-6">
        <x-admin.marketplace-nav />
        <h1 class="text-xl font-semibold">Edit vendor</h1>
        @if($errors->any())
            <div class="p-3 bg-red-50 text-red-800 text-sm rounded-lg">
                <ul class="list-disc pl-4">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
            </div>
        @endif
        @php $p = $vendor->profile; @endphp
        <form method="POST" action="{{ route('admin.vendors.update', $vendor) }}" enctype="multipart/form-data" class="space-y-4 bg-white dark:bg-gray-800 rounded-xl border p-6">
            @csrf @method('PUT')
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2"><label class="text-sm font-medium">Company Name</label><input name="business_name" value="{{ old('business_name', $p?->business_name) }}" required class="mt-1 w-full rounded-lg border-gray-300" /></div>
                <div><label class="text-sm font-medium">Authorized Person</label><input name="owner_name" value="{{ old('owner_name', $p?->owner_name) }}" required class="mt-1 w-full rounded-lg border-gray-300" /></div>
                <div><label class="text-sm font-medium">Email</label><input type="email" name="email" value="{{ old('email', $p?->email) }}" required class="mt-1 w-full rounded-lg border-gray-300" /></div>
                <div><label class="text-sm font-medium">Phone</label><input name="phone" value="{{ old('phone', $p?->phone) }}" class="mt-1 w-full rounded-lg border-gray-300" /></div>
                <div><label class="text-sm font-medium">Vendor Type</label>
                    <select name="vendor_type" class="mt-1 w-full rounded-lg border-gray-300">
                        <option value="">—</option>
                        @foreach(\App\Enums\VendorType::options() as $value => $label)
                            <option value="{{ $value }}" @selected(old('vendor_type', $p?->vendor_type) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div><label class="text-sm font-medium">Trade License No.</label><input name="trade_license_number" value="{{ old('trade_license_number', $p?->trade_license_number) }}" class="mt-1 w-full rounded-lg border-gray-300" /></div>
                <div><label class="text-sm font-medium">VAT Number</label><input name="tax_vat_number" value="{{ old('tax_vat_number', $p?->tax_vat_number) }}" class="mt-1 w-full rounded-lg border-gray-300" /></div>
                <div><label class="text-sm font-medium">Emirate</label>
                    <select name="emirate" class="mt-1 w-full rounded-lg border-gray-300">
                        <option value="">—</option>
                        @foreach(\App\Models\VendorProfile::emirates() as $emirate)
                            <option value="{{ $emirate }}" @selected(old('emirate', $p?->emirate) === $emirate)>{{ $emirate }}</option>
                        @endforeach
                    </select>
                </div>
                <div><label class="text-sm font-medium">City</label><input name="city" value="{{ old('city', $p?->city) }}" class="mt-1 w-full rounded-lg border-gray-300" /></div>
                <div class="sm:col-span-2"><label class="text-sm font-medium">Address</label><textarea name="address" class="mt-1 w-full rounded-lg border-gray-300" rows="2">{{ old('address', $p?->address) }}</textarea></div>
                <div class="sm:col-span-2"><label class="text-sm font-medium">Google Maps Location</label><input name="google_maps_location" value="{{ old('google_maps_location', $p?->google_maps_location) }}" class="mt-1 w-full rounded-lg border-gray-300" /></div>
                <div><label class="text-sm font-medium">Bank Name</label><input name="bank_name" value="{{ old('bank_name', $p?->bank_name) }}" class="mt-1 w-full rounded-lg border-gray-300" /></div>
                <div><label class="text-sm font-medium">IBAN</label><input name="iban" value="{{ old('iban', $p?->iban) }}" class="mt-1 w-full rounded-lg border-gray-300" /></div>
                <div class="sm:col-span-2"><label class="text-sm font-medium">Account Holder Name</label><input name="account_holder_name" value="{{ old('account_holder_name', $p?->account_holder_name) }}" class="mt-1 w-full rounded-lg border-gray-300" /></div>
                <div><label class="text-sm font-medium">Delivery Radius (km)</label><input type="number" step="0.1" min="0" name="delivery_radius" value="{{ old('delivery_radius', $p?->delivery_radius) }}" class="mt-1 w-full rounded-lg border-gray-300" /></div>
                <div><label class="text-sm font-medium">Minimum Order Amount</label><input type="number" step="0.01" min="0" name="minimum_order_amount" value="{{ old('minimum_order_amount', $p?->minimum_order_amount) }}" class="mt-1 w-full rounded-lg border-gray-300" /></div>
                <div class="sm:col-span-2"><label class="text-sm font-medium">Operating Hours</label><input name="operating_hours" value="{{ old('operating_hours', $p?->operating_hours) }}" class="mt-1 w-full rounded-lg border-gray-300" /></div>
                <div class="sm:col-span-2"><label class="text-sm font-medium">Description</label><textarea name="description" class="mt-1 w-full rounded-lg border-gray-300" rows="3">{{ old('description', $p?->description) }}</textarea></div>
                <div><label class="text-sm font-medium">Custom commission % (blank = default)</label><input type="number" name="commission_rate" step="0.01" min="0" max="100" value="{{ old('commission_rate', $vendor->commission_rate) }}" class="mt-1 w-full rounded-lg border-gray-300" /></div>
                <div><label class="text-sm font-medium">Logo</label><input type="file" name="logo" accept="image/*" class="mt-1 w-full text-sm" /></div>
            </div>
            <button class="px-4 py-2 bg-gray-900 text-white text-sm rounded-lg">Save</button>
        </form>
    </div>
</x-admin-layout>
