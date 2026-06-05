@php
    /** @var \App\Models\VendorProfile|null $profile */
    $profile = $profile ?? null;
    $vendorTypes = $vendorTypes ?? \App\Enums\VendorType::options();
    $emirates = $emirates ?? \App\Models\VendorProfile::emirates();
    $documents = isset($vendor) ? $vendor->documents : collect();
    $tradeLicenseDoc = $documents->firstWhere('type', \App\Enums\VendorDocumentType::TradeLicense->value);
    $emiratesIdDoc = $documents->firstWhere('type', \App\Enums\VendorDocumentType::EmiratesId->value);
@endphp

<div class="grid gap-4 sm:grid-cols-2">
    <div class="sm:col-span-2">
        <label class="text-sm font-medium text-gray-700">Company Name *</label>
        <input type="text" name="business_name" value="{{ old('business_name', $profile?->business_name) }}" class="mt-1 w-full rounded-lg border-gray-300" required />
    </div>
    <div>
        <label class="text-sm font-medium text-gray-700">Authorized Person Name *</label>
        <input type="text" name="owner_name" value="{{ old('owner_name', $profile?->owner_name) }}" class="mt-1 w-full rounded-lg border-gray-300" required />
    </div>
    <div>
        <label class="text-sm font-medium text-gray-700">Email *</label>
        <input type="email" name="email" value="{{ old('email', $profile?->email) }}" class="mt-1 w-full rounded-lg border-gray-300" required />
    </div>
    <div>
        <label class="text-sm font-medium text-gray-700">Phone Number *</label>
        <input type="text" name="phone" value="{{ old('phone', $profile?->phone) }}" class="mt-1 w-full rounded-lg border-gray-300" required />
    </div>
    <div>
        <label class="text-sm font-medium text-gray-700">Vendor Type *</label>
        <select name="vendor_type" class="mt-1 w-full rounded-lg border-gray-300" required>
            <option value="">Select type…</option>
            @foreach($vendorTypes as $value => $label)
                <option value="{{ $value }}" @selected(old('vendor_type', $profile?->vendor_type) === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="text-sm font-medium text-gray-700">Trade License Number *</label>
        <input type="text" name="trade_license_number" value="{{ old('trade_license_number', $profile?->trade_license_number) }}" class="mt-1 w-full rounded-lg border-gray-300" required />
    </div>
    <div>
        <label class="text-sm font-medium text-gray-700">VAT Number <span class="text-gray-400">(optional)</span></label>
        <input type="text" name="vat_number" value="{{ old('vat_number', $profile?->tax_vat_number) }}" class="mt-1 w-full rounded-lg border-gray-300" />
    </div>
    <div>
        <label class="text-sm font-medium text-gray-700">Emirate *</label>
        <select name="emirate" class="mt-1 w-full rounded-lg border-gray-300" required>
            <option value="">Select emirate…</option>
            @foreach($emirates as $emirate)
                <option value="{{ $emirate }}" @selected(old('emirate', $profile?->emirate) === $emirate)>{{ $emirate }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="text-sm font-medium text-gray-700">City *</label>
        <input type="text" name="city" value="{{ old('city', $profile?->city) }}" class="mt-1 w-full rounded-lg border-gray-300" required />
    </div>
    <div class="sm:col-span-2">
        <label class="text-sm font-medium text-gray-700">Address *</label>
        <textarea name="address" rows="2" class="mt-1 w-full rounded-lg border-gray-300" required>{{ old('address', $profile?->address) }}</textarea>
    </div>
    <div class="sm:col-span-2">
        <label class="text-sm font-medium text-gray-700">Google Maps Location *</label>
        <input type="text" name="google_maps_location" value="{{ old('google_maps_location', $profile?->google_maps_location) }}" placeholder="Google Maps URL or latitude,longitude" class="mt-1 w-full rounded-lg border-gray-300" required />
    </div>

    <div class="sm:col-span-2 mt-2 border-t border-gray-100 pt-4">
        <h3 class="text-sm font-semibold text-gray-800">Bank Details</h3>
    </div>
    <div>
        <label class="text-sm font-medium text-gray-700">Bank Name *</label>
        <input type="text" name="bank_name" value="{{ old('bank_name', $profile?->bank_name) }}" class="mt-1 w-full rounded-lg border-gray-300" required />
    </div>
    <div>
        <label class="text-sm font-medium text-gray-700">IBAN *</label>
        <input type="text" name="iban" value="{{ old('iban', $profile?->iban) }}" class="mt-1 w-full rounded-lg border-gray-300" required />
    </div>
    <div class="sm:col-span-2">
        <label class="text-sm font-medium text-gray-700">Account Holder Name *</label>
        <input type="text" name="account_holder_name" value="{{ old('account_holder_name', $profile?->account_holder_name) }}" class="mt-1 w-full rounded-lg border-gray-300" required />
    </div>

    <div class="sm:col-span-2 mt-2 border-t border-gray-100 pt-4">
        <h3 class="text-sm font-semibold text-gray-800">Operations</h3>
    </div>
    <div>
        <label class="text-sm font-medium text-gray-700">Delivery Radius (km) *</label>
        <input type="number" step="0.1" min="0" name="delivery_radius" value="{{ old('delivery_radius', $profile?->delivery_radius) }}" class="mt-1 w-full rounded-lg border-gray-300" required />
    </div>
    <div>
        <label class="text-sm font-medium text-gray-700">Minimum Order Amount (AED) *</label>
        <input type="number" step="0.01" min="0" name="minimum_order_amount" value="{{ old('minimum_order_amount', $profile?->minimum_order_amount) }}" class="mt-1 w-full rounded-lg border-gray-300" required />
    </div>
    <div class="sm:col-span-2">
        <label class="text-sm font-medium text-gray-700">Operating Hours *</label>
        <input type="text" name="operating_hours" value="{{ old('operating_hours', $profile?->operating_hours) }}" placeholder="e.g. Mon–Sun, 9:00 AM – 9:00 PM" class="mt-1 w-full rounded-lg border-gray-300" required />
    </div>

    <div class="sm:col-span-2 mt-2 border-t border-gray-100 pt-4">
        <h3 class="text-sm font-semibold text-gray-800">Branding & Documents</h3>
    </div>
    <div class="sm:col-span-2">
        <label class="text-sm font-medium text-gray-700">Company Logo @if(!isset($vendor) || !$vendor?->logo_url)*@endif</label>
        @if(isset($vendor) && $vendor?->logo_url)
            <img src="{{ $vendor->logo_url }}" alt="Logo" class="mb-2 h-16 w-16 rounded-lg border object-cover" />
        @endif
        <input type="file" name="logo" accept="image/*" class="mt-1 w-full text-sm" @if(!isset($vendor) || !$vendor?->logo_url) {{ ($requireLogo ?? false) ? 'required' : '' }} @endif />
    </div>
    <div>
        <label class="text-sm font-medium text-gray-700">Trade License Upload @if(!$tradeLicenseDoc)*@endif</label>
        @if($tradeLicenseDoc)
            <p class="text-xs text-green-600">Uploaded: <a href="{{ $tradeLicenseDoc->file_url }}" target="_blank" class="underline">{{ $tradeLicenseDoc->original_name }}</a> ({{ ucfirst($tradeLicenseDoc->verification_status) }})</p>
        @endif
        <input type="file" name="trade_license" accept=".pdf,image/*" class="mt-1 w-full text-sm" />
    </div>
    <div>
        <label class="text-sm font-medium text-gray-700">Emirates ID Upload @if(!$emiratesIdDoc)*@endif</label>
        @if($emiratesIdDoc)
            <p class="text-xs text-green-600">Uploaded: <a href="{{ $emiratesIdDoc->file_url }}" target="_blank" class="underline">{{ $emiratesIdDoc->original_name }}</a> ({{ ucfirst($emiratesIdDoc->verification_status) }})</p>
        @endif
        <input type="file" name="emirates_id" accept=".pdf,image/*" class="mt-1 w-full text-sm" />
    </div>
    <div class="sm:col-span-2">
        <label class="text-sm font-medium text-gray-700">Business Description</label>
        <textarea name="description" rows="3" class="mt-1 w-full rounded-lg border-gray-300">{{ old('description', $profile?->description) }}</textarea>
    </div>
</div>
