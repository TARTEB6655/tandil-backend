<x-vendor-layout>
    <x-dashboard.page-header title="Business Profile" subtitle="Step 1 of onboarding — tell us about your business." />

    @php $profile = $vendor->profile; @endphp

    @if($errors->any())
        <div class="mb-4 max-w-3xl rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-800">
            <ul class="list-disc pl-4">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    <form method="POST" action="{{ route('vendor.onboarding.profile.update') }}" enctype="multipart/form-data" class="max-w-3xl rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
        @csrf
        @method('PUT')

        @include('vendor.partials.business-profile-fields', ['profile' => $profile, 'vendorTypes' => $vendorTypes, 'emirates' => $emirates])

        <div class="mt-4 rounded-lg border border-gray-200 bg-gray-50 p-4">
            <label class="flex items-start gap-2 text-sm text-gray-700">
                <input type="checkbox" name="terms_accepted" value="1" class="mt-0.5 rounded border-gray-300 text-indigo-600" @checked(old('terms_accepted', $profile?->terms_accepted_at !== null)) />
                <span>I accept the <a href="{{ route('legal.privacy-policy') }}" target="_blank" class="text-indigo-600 underline">Terms &amp; Conditions</a> of the marketplace. *</span>
            </label>
        </div>

        <div class="mt-6 flex gap-3">
            <a href="{{ route('vendor.onboarding.index') }}" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Back</a>
            <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Save &amp; continue</button>
        </div>
    </form>
</x-vendor-layout>
