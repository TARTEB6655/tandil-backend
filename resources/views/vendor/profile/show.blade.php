<x-vendor-layout>
    <x-dashboard.page-header title="Business Profile" subtitle="Your store information visible to customers and admins." />

    @php $profile = $vendor?->profile; @endphp

    @if(session('success'))
        <div class="mb-4 max-w-3xl rounded-lg border border-green-200 bg-green-50 p-3 text-sm text-green-800">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="mb-4 max-w-3xl rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-800">
            <ul class="list-disc pl-4">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    <form method="POST" action="{{ route('vendor.profile.update') }}" enctype="multipart/form-data" class="max-w-3xl rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
        @csrf
        @method('PUT')

        @include('vendor.partials.business-profile-fields', ['profile' => $profile, 'vendorTypes' => $vendorTypes, 'emirates' => $emirates])

        <div class="mt-6">
            <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Save Profile</button>
        </div>
    </form>
</x-vendor-layout>
