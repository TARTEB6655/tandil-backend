<x-vendor-layout>
    <x-dashboard.page-header title="Business Profile" subtitle="Your store information visible to customers and admins." />

    @php $profile = $vendor?->profile; @endphp

    <form method="POST" action="{{ route('vendor.profile.update') }}" enctype="multipart/form-data" class="max-w-2xl rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
        @csrf
        @method('PUT')
        <div class="grid gap-4 sm:grid-cols-2">
            <div class="sm:col-span-2">
                <label class="text-sm font-medium text-gray-700">Business name</label>
                <input type="text" name="business_name" value="{{ old('business_name', $profile?->business_name) }}" class="mt-1 w-full rounded-lg border-gray-300" required />
            </div>
            <div>
                <label class="text-sm font-medium text-gray-700">Owner name</label>
                <input type="text" name="owner_name" value="{{ old('owner_name', $profile?->owner_name) }}" class="mt-1 w-full rounded-lg border-gray-300" />
            </div>
            <div>
                <label class="text-sm font-medium text-gray-700">Business email</label>
                <input type="email" name="email" value="{{ old('email', $profile?->email) }}" class="mt-1 w-full rounded-lg border-gray-300" />
            </div>
            <div>
                <label class="text-sm font-medium text-gray-700">Phone</label>
                <input type="text" name="phone" value="{{ old('phone', $profile?->phone) }}" class="mt-1 w-full rounded-lg border-gray-300" />
            </div>
            <div>
                <label class="text-sm font-medium text-gray-700">Tax / VAT number</label>
                <input type="text" name="tax_vat_number" value="{{ old('tax_vat_number', $profile?->tax_vat_number) }}" class="mt-1 w-full rounded-lg border-gray-300" />
            </div>
            <div class="sm:col-span-2">
                <label class="text-sm font-medium text-gray-700">Address</label>
                <textarea name="address" rows="2" class="mt-1 w-full rounded-lg border-gray-300">{{ old('address', $profile?->address) }}</textarea>
            </div>
            <div class="sm:col-span-2">
                <label class="text-sm font-medium text-gray-700">Description</label>
                <textarea name="description" rows="4" class="mt-1 w-full rounded-lg border-gray-300">{{ old('description', $profile?->description) }}</textarea>
            </div>
            <div class="sm:col-span-2">
                <label class="text-sm font-medium text-gray-700">Logo</label>
                @if($vendor?->logo_url)
                    <img src="{{ $vendor->logo_url }}" alt="Logo" class="mb-2 h-16 w-16 rounded-lg border object-cover" />
                @endif
                <input type="file" name="logo" accept="image/*" class="mt-1 w-full text-sm" />
            </div>
        </div>
        <div class="mt-6">
            <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Save Profile</button>
        </div>
    </form>
</x-vendor-layout>
