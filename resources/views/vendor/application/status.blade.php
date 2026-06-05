<x-vendor-layout>
    <x-dashboard.page-header title="Application Status" subtitle="Track your vendor application progress and approval." />

    @php
        $pct = (int) ($application['completion_percent'] ?? 0);
        $status = $vendor->status;
        $statusStyles = [
            'approved'     => 'bg-green-100 text-green-700',
            'under_review' => 'bg-blue-100 text-blue-700',
            'pending'      => 'bg-amber-100 text-amber-700',
            'rejected'     => 'bg-red-100 text-red-700',
            'suspended'    => 'bg-red-100 text-red-700',
            'disabled'     => 'bg-gray-200 text-gray-700',
        ];
        $statusPill = $statusStyles[$status] ?? 'bg-gray-100 text-gray-700';
    @endphp

    <!-- Hero -->
    <div class="relative overflow-hidden rounded-2xl p-6 text-white shadow-lg sm:p-8" style="background-image: linear-gradient(135deg, #4f46e5 0%, #7c3aed 50%, #c026d3 100%);">
        <div class="pointer-events-none absolute -right-12 -top-12 h-44 w-44 rounded-full bg-white/10 blur-2xl"></div>
        <div class="pointer-events-none absolute -bottom-16 -left-10 h-44 w-44 rounded-full bg-white/10 blur-2xl"></div>
        <div class="relative flex flex-col gap-6 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <span class="inline-flex items-center gap-1.5 rounded-full bg-white/20 px-3 py-1 text-xs font-semibold backdrop-blur">
                    <span class="h-1.5 w-1.5 rounded-full bg-white"></span>
                    {{ $application['status_label'] }}
                </span>
                <p class="mt-4 text-sm font-medium text-indigo-100">Onboarding progress</p>
                <div class="mt-1 flex items-end gap-2">
                    <span class="text-4xl font-bold leading-none">{{ $pct }}%</span>
                    <span class="mb-0.5 text-sm text-indigo-100">complete</span>
                </div>
            </div>
            <div class="w-full sm:w-72">
                <div class="h-2.5 w-full overflow-hidden rounded-full bg-white/25">
                    <div class="h-full rounded-full bg-white transition-all duration-500" style="width: {{ $pct }}%"></div>
                </div>
                <p class="mt-2 text-xs text-indigo-100">Complete all steps to be reviewed by our team.</p>
            </div>
        </div>
    </div>

    @if($vendor->rejection_reason)
        <div class="mt-6 flex items-start gap-3 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">
            <svg class="mt-0.5 h-5 w-5 flex-shrink-0 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
            <div>
                <p class="font-semibold">Application rejected</p>
                <p class="mt-1">{{ $vendor->rejection_reason }}</p>
            </div>
        </div>
    @endif

    <!-- Step status -->
    <div class="mt-6 grid gap-5 sm:grid-cols-3">
        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
            <div class="flex items-center gap-3">
                <span class="flex h-11 w-11 items-center justify-center rounded-xl text-white shadow-md" style="background-image: linear-gradient(135deg, #6366f1, #9333ea);">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                </span>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Profile</p>
                    <span class="mt-1 inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-medium {{ $application['profile_complete'] ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-700' }}">
                        <span class="h-1.5 w-1.5 rounded-full {{ $application['profile_complete'] ? 'bg-green-500' : 'bg-amber-500' }}"></span>
                        {{ $application['profile_complete'] ? 'Complete' : 'Incomplete' }}
                    </span>
                </div>
            </div>
        </div>
        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
            <div class="flex items-center gap-3">
                <span class="flex h-11 w-11 items-center justify-center rounded-xl text-white shadow-md" style="background-image: linear-gradient(135deg, #0ea5e9, #2563eb);">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/></svg>
                </span>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Categories</p>
                    <span class="mt-1 inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-medium {{ $application['categories_complete'] ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-700' }}">
                        <span class="h-1.5 w-1.5 rounded-full {{ $application['categories_complete'] ? 'bg-green-500' : 'bg-amber-500' }}"></span>
                        {{ $application['categories_complete'] ? 'Selected' : 'Required' }}
                    </span>
                </div>
            </div>
        </div>
        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
            <div class="flex items-center gap-3">
                <span class="flex h-11 w-11 items-center justify-center rounded-xl text-white shadow-md" style="background-image: linear-gradient(135deg, #f59e0b, #ea580c);">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                </span>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Documents</p>
                    <span class="mt-1 inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-medium {{ $application['documents_complete'] ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-700' }}">
                        <span class="h-1.5 w-1.5 rounded-full {{ $application['documents_complete'] ? 'bg-green-500' : 'bg-amber-500' }}"></span>
                        {{ $application['documents_complete'] ? 'Complete' : 'Incomplete' }}
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- Actions -->
    <div class="mt-6 flex flex-wrap gap-3">
        <a href="{{ route('vendor.onboarding.index') }}" class="inline-flex items-center rounded-lg px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:opacity-90" style="background-image: linear-gradient(135deg, #4f46e5, #7c3aed);">Continue onboarding</a>
        <a href="{{ route('vendor.documents.index') }}" class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50">Manage documents</a>
        @if($application['can_resubmit'])
            <form method="POST" action="{{ route('vendor.application.resubmit') }}">@csrf
                <button type="submit" class="inline-flex items-center rounded-lg px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:opacity-90" style="background-image: linear-gradient(135deg, #f59e0b, #ea580c);">Resubmit application</button>
            </form>
        @elseif($application['onboarding_complete'] && in_array($vendor->status, ['pending', 'under_review']))
            <form method="POST" action="{{ route('vendor.application.submit') }}">@csrf
                <button type="submit" class="inline-flex items-center rounded-lg bg-gray-900 px-4 py-2 text-sm font-medium text-white transition hover:bg-gray-800">Submit for review</button>
            </form>
        @endif
    </div>

    <!-- Documents + Timeline -->
    <div class="mt-6 grid gap-6 lg:grid-cols-2">
        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
            <h2 class="mb-4 flex items-center gap-2 text-base font-semibold text-gray-900">
                <svg class="h-5 w-5 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                Required documents
            </h2>
            <ul class="space-y-2.5">
                @foreach($application['required_documents'] as $doc)
                    <li class="flex items-center justify-between rounded-lg border border-gray-100 bg-gray-50 px-3 py-2.5 text-sm">
                        <span class="font-medium text-gray-700">{{ $doc['label'] }}</span>
                        <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium {{ $doc['uploaded'] ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-700' }}">
                            <span class="h-1.5 w-1.5 rounded-full {{ $doc['uploaded'] ? 'bg-green-500' : 'bg-amber-500' }}"></span>
                            {{ $doc['uploaded'] ? ucfirst($doc['verification_status'] ?? 'uploaded') : 'Missing' }}
                        </span>
                    </li>
                @endforeach
            </ul>
        </div>
        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
            <h2 class="mb-4 flex items-center gap-2 text-base font-semibold text-gray-900">
                <svg class="h-5 w-5 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                Status timeline
            </h2>
            <ol class="relative max-h-80 space-y-5 overflow-y-auto border-l-2 border-gray-100 pl-5">
                @forelse($application['approval_logs'] as $log)
                    <li class="relative">
                        <span class="absolute -left-[27px] top-1 h-3 w-3 rounded-full border-2 border-white shadow" style="background-image: linear-gradient(135deg, #6366f1, #9333ea);"></span>
                        <p class="text-sm font-semibold text-gray-900">{{ ucfirst(str_replace('_', ' ', $log['action'])) }}</p>
                        <p class="text-xs text-gray-400">{{ $log['created_at'] ? \Carbon\Carbon::parse($log['created_at'])->format('M d, Y H:i') : '' }}</p>
                        @if($log['notes'])<p class="mt-1 text-xs text-gray-600">{{ $log['notes'] }}</p>@endif
                    </li>
                @empty
                    <li class="text-sm text-gray-500">No activity yet.</li>
                @endforelse
            </ol>
        </div>
    </div>
</x-vendor-layout>
