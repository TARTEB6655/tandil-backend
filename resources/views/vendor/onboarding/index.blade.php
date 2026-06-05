<x-vendor-layout>
    <x-dashboard.page-header title="Vendor Onboarding" subtitle="Complete your profile, categories, and documents before selling." />

    @php $pct = (int) ($application['completion_percent'] ?? 0); @endphp

    <!-- Progress hero -->
    <div class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-indigo-600 via-purple-600 to-fuchsia-600 p-6 text-white shadow-lg sm:p-8">
        <div class="pointer-events-none absolute -right-12 -top-12 h-44 w-44 rounded-full bg-white/10 blur-2xl"></div>
        <div class="pointer-events-none absolute -bottom-16 -left-10 h-44 w-44 rounded-full bg-white/10 blur-2xl"></div>
        <div class="relative">
            <p class="text-sm font-medium text-indigo-100">Overall progress</p>
            <div class="mt-1 flex items-end gap-2">
                <span class="text-4xl font-bold leading-none">{{ $pct }}%</span>
                <span class="mb-0.5 text-sm text-indigo-100">complete</span>
            </div>
            <div class="mt-4 h-2.5 w-full overflow-hidden rounded-full bg-white/25">
                <div class="h-full rounded-full bg-white transition-all duration-500" style="width: {{ $pct }}%"></div>
            </div>
            <p class="mt-3 text-sm text-indigo-100">Finish all three steps below to submit your application for admin approval.</p>
        </div>
    </div>

    <!-- Steps -->
    <div class="mt-6 grid gap-4 md:grid-cols-3">
        <a href="{{ route('vendor.onboarding.profile') }}"
           class="group relative overflow-hidden rounded-2xl border border-gray-200 bg-white p-5 shadow-sm transition duration-200 hover:-translate-y-0.5 hover:border-indigo-300 hover:shadow-md">
            <div class="absolute inset-x-0 top-0 h-1 bg-gradient-to-r from-indigo-500 to-purple-600"></div>
            <div class="flex items-center gap-3">
                <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-gradient-to-br from-indigo-500 to-purple-600 text-white shadow-md">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                </span>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Step 1</p>
                    <h2 class="text-base font-semibold text-gray-900">Business profile</h2>
                </div>
            </div>
            <div class="mt-4">
                <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium {{ $application['profile_complete'] ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-700' }}">
                    <span class="h-1.5 w-1.5 rounded-full {{ $application['profile_complete'] ? 'bg-green-500' : 'bg-amber-500' }}"></span>
                    {{ $application['profile_complete'] ? 'Complete' : 'Required fields missing' }}
                </span>
            </div>
            @if(!empty($application['missing_profile_fields']))
                <p class="mt-2 text-xs text-gray-500">Missing: {{ implode(', ', array_slice($application['missing_profile_fields'], 0, 4)) }}{{ count($application['missing_profile_fields']) > 4 ? '…' : '' }}</p>
            @endif
        </a>

        <a href="{{ route('vendor.onboarding.categories') }}"
           class="group relative overflow-hidden rounded-2xl border border-gray-200 bg-white p-5 shadow-sm transition duration-200 hover:-translate-y-0.5 hover:border-sky-300 hover:shadow-md">
            <div class="absolute inset-x-0 top-0 h-1 bg-gradient-to-r from-sky-500 to-blue-600"></div>
            <div class="flex items-center gap-3">
                <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-gradient-to-br from-sky-500 to-blue-600 text-white shadow-md">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/></svg>
                </span>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Step 2</p>
                    <h2 class="text-base font-semibold text-gray-900">Categories</h2>
                </div>
            </div>
            <div class="mt-4">
                <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium {{ $application['categories_complete'] ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-700' }}">
                    <span class="h-1.5 w-1.5 rounded-full {{ $application['categories_complete'] ? 'bg-green-500' : 'bg-amber-500' }}"></span>
                    {{ $application['categories_complete'] ? count($vendor->categories).' selected' : 'Select at least one' }}
                </span>
            </div>
        </a>

        <a href="{{ route('vendor.documents.index') }}"
           class="group relative overflow-hidden rounded-2xl border border-gray-200 bg-white p-5 shadow-sm transition duration-200 hover:-translate-y-0.5 hover:border-amber-300 hover:shadow-md">
            <div class="absolute inset-x-0 top-0 h-1 bg-gradient-to-r from-amber-500 to-orange-600"></div>
            <div class="flex items-center gap-3">
                <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-gradient-to-br from-amber-500 to-orange-600 text-white shadow-md">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                </span>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Step 3</p>
                    <h2 class="text-base font-semibold text-gray-900">Documents</h2>
                </div>
            </div>
            <div class="mt-4">
                <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium {{ $application['documents_complete'] ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-700' }}">
                    <span class="h-1.5 w-1.5 rounded-full {{ $application['documents_complete'] ? 'bg-green-500' : 'bg-amber-500' }}"></span>
                    {{ $application['documents_complete'] ? 'All required uploaded' : 'Upload required files' }}
                </span>
            </div>
        </a>
    </div>

    <div class="mt-6 flex flex-wrap gap-3">
        <a href="{{ route('vendor.application.status') }}" class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50">View application status</a>
        @if($application['onboarding_complete'] && in_array($vendor->status, ['pending', 'under_review']))
            <form method="POST" action="{{ route('vendor.application.submit') }}">@csrf
                <button type="submit" class="inline-flex items-center rounded-lg bg-gradient-to-r from-indigo-600 to-purple-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:from-indigo-700 hover:to-purple-700">Submit for review</button>
            </form>
        @endif
    </div>
</x-vendor-layout>
