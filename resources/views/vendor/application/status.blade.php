<x-vendor-layout>
    <x-dashboard.page-header title="Application Status" :subtitle="'Status: '.$application['status_label']" />

    @if(session('success'))
        <div class="mb-4 rounded-lg border border-green-200 bg-green-50 p-3 text-sm text-green-800">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="mb-4 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-800">{{ session('error') }}</div>
    @endif

    <div class="mb-6 rounded-xl border border-indigo-200 bg-indigo-50 p-4 sm:p-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-sm font-medium text-indigo-900">Onboarding progress</p>
                <p class="mt-1 text-3xl font-semibold text-indigo-700">{{ $application['completion_percent'] }}%</p>
            </div>
            <div class="h-3 w-full max-w-md overflow-hidden rounded-full bg-indigo-200 sm:w-64">
                <div class="h-full rounded-full bg-indigo-600 transition-all" style="width: {{ $application['completion_percent'] }}%"></div>
            </div>
        </div>
    </div>

    @if($vendor->rejection_reason)
        <div class="mb-6 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">
            <p class="font-medium">Rejection reason</p>
            <p class="mt-1">{{ $vendor->rejection_reason }}</p>
        </div>
    @endif

    <div class="mb-6 grid gap-4 sm:grid-cols-3">
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
            <p class="text-xs font-medium uppercase text-gray-500">Profile</p>
            <p class="mt-2 text-sm font-medium {{ $application['profile_complete'] ? 'text-green-600' : 'text-amber-600' }}">
                {{ $application['profile_complete'] ? 'Complete' : 'Incomplete' }}
            </p>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
            <p class="text-xs font-medium uppercase text-gray-500">Categories</p>
            <p class="mt-2 text-sm font-medium {{ $application['categories_complete'] ? 'text-green-600' : 'text-amber-600' }}">
                {{ $application['categories_complete'] ? 'Selected' : 'Required' }}
            </p>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
            <p class="text-xs font-medium uppercase text-gray-500">Documents</p>
            <p class="mt-2 text-sm font-medium {{ $application['documents_complete'] ? 'text-green-600' : 'text-amber-600' }}">
                {{ $application['documents_complete'] ? 'Complete' : 'Incomplete' }}
            </p>
        </div>
    </div>

    <div class="mb-6 flex flex-wrap gap-3">
        <a href="{{ route('vendor.onboarding.index') }}" class="inline-flex items-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Continue onboarding</a>
        <a href="{{ route('vendor.documents.index') }}" class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Manage documents</a>
        @if($application['can_resubmit'])
            <form method="POST" action="{{ route('vendor.application.resubmit') }}">@csrf
                <button type="submit" class="inline-flex items-center rounded-lg bg-amber-600 px-4 py-2 text-sm font-medium text-white hover:bg-amber-700">Resubmit application</button>
            </form>
        @elseif($application['onboarding_complete'] && in_array($vendor->status, ['pending', 'under_review']))
            <form method="POST" action="{{ route('vendor.application.submit') }}">@csrf
                <button type="submit" class="inline-flex items-center rounded-lg bg-gray-900 px-4 py-2 text-sm font-medium text-white hover:bg-gray-800">Submit for review</button>
            </form>
        @endif
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm sm:p-6">
            <h2 class="mb-4 text-base font-medium text-gray-900">Required documents</h2>
            <ul class="space-y-3">
                @foreach($application['required_documents'] as $doc)
                    <li class="flex items-center justify-between text-sm">
                        <span>{{ $doc['label'] }}</span>
                        <span class="rounded-full px-2 py-0.5 text-xs {{ $doc['uploaded'] ? 'bg-green-100 text-green-800' : 'bg-amber-100 text-amber-800' }}">
                            {{ $doc['uploaded'] ? ucfirst($doc['verification_status'] ?? 'uploaded') : 'Missing' }}
                        </span>
                    </li>
                @endforeach
            </ul>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm sm:p-6">
            <h2 class="mb-4 text-base font-medium text-gray-900">Status timeline</h2>
            <ul class="max-h-80 space-y-3 overflow-y-auto text-sm text-gray-600">
                @forelse($application['approval_logs'] as $log)
                    <li class="border-l-2 border-gray-200 pl-3">
                        <p class="font-medium text-gray-900">{{ ucfirst(str_replace('_', ' ', $log['action'])) }}</p>
                        <p class="text-xs">{{ $log['created_at'] ? \Carbon\Carbon::parse($log['created_at'])->format('M d, Y H:i') : '' }}</p>
                        @if($log['notes'])<p class="mt-1 text-xs">{{ $log['notes'] }}</p>@endif
                    </li>
                @empty
                    <li>No activity yet.</li>
                @endforelse
            </ul>
        </div>
    </div>
</x-vendor-layout>
