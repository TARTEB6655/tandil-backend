<x-vendor-layout>
    <x-dashboard.page-header title="Vendor Onboarding" subtitle="Complete your profile, categories, and documents before selling." />

    @if(session('success'))
        <div class="mb-4 rounded-lg border border-green-200 bg-green-50 p-3 text-sm text-green-800">{{ session('success') }}</div>
    @endif

    <div class="mb-6 rounded-xl border border-indigo-200 bg-indigo-50 p-4 sm:p-6">
        <p class="text-sm font-medium text-indigo-900">Overall progress</p>
        <p class="mt-1 text-3xl font-semibold text-indigo-700">{{ $application['completion_percent'] }}%</p>
        <div class="mt-3 h-2 overflow-hidden rounded-full bg-indigo-200">
            <div class="h-full rounded-full bg-indigo-600" style="width: {{ $application['completion_percent'] }}%"></div>
        </div>
    </div>

    <div class="grid gap-4 md:grid-cols-3">
        <a href="{{ route('vendor.onboarding.profile') }}" class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm transition hover:border-indigo-300">
            <p class="text-xs font-medium uppercase text-gray-500">Step 1</p>
            <h2 class="mt-1 text-base font-medium text-gray-900">Business profile</h2>
            <p class="mt-2 text-sm {{ $application['profile_complete'] ? 'text-green-600' : 'text-amber-600' }}">
                {{ $application['profile_complete'] ? 'Complete' : 'Required fields missing' }}
            </p>
            @if(!empty($application['missing_profile_fields']))
                <p class="mt-1 text-xs text-gray-500">Missing: {{ implode(', ', array_slice($application['missing_profile_fields'], 0, 4)) }}{{ count($application['missing_profile_fields']) > 4 ? '…' : '' }}</p>
            @endif
        </a>
        <a href="{{ route('vendor.onboarding.categories') }}" class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm transition hover:border-indigo-300">
            <p class="text-xs font-medium uppercase text-gray-500">Step 2</p>
            <h2 class="mt-1 text-base font-medium text-gray-900">Categories</h2>
            <p class="mt-2 text-sm {{ $application['categories_complete'] ? 'text-green-600' : 'text-amber-600' }}">
                {{ $application['categories_complete'] ? count($vendor->categories).' selected' : 'Select at least one' }}
            </p>
        </a>
        <a href="{{ route('vendor.documents.index') }}" class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm transition hover:border-indigo-300">
            <p class="text-xs font-medium uppercase text-gray-500">Step 3</p>
            <h2 class="mt-1 text-base font-medium text-gray-900">Documents</h2>
            <p class="mt-2 text-sm {{ $application['documents_complete'] ? 'text-green-600' : 'text-amber-600' }}">
                {{ $application['documents_complete'] ? 'All required uploaded' : 'Upload required files' }}
            </p>
        </a>
    </div>

    <div class="mt-6 flex flex-wrap gap-3">
        <a href="{{ route('vendor.application.status') }}" class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">View application status</a>
        @if($application['onboarding_complete'] && in_array($vendor->status, ['pending', 'under_review']))
            <form method="POST" action="{{ route('vendor.application.submit') }}">@csrf
                <button type="submit" class="inline-flex items-center rounded-lg bg-gray-900 px-4 py-2 text-sm font-medium text-white hover:bg-gray-800">Submit for review</button>
            </form>
        @endif
    </div>
</x-vendor-layout>
