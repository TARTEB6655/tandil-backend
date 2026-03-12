<x-hr-layout>
    <div class="mb-4 sm:mb-6">
        <a href="{{ route('hr.leave-requests.index', ['status' => $lr->status]) }}" class="inline-flex items-center text-sm text-indigo-600 hover:text-indigo-900 mb-2">
            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
            Back to leave requests
        </a>
        <h1 class="text-lg sm:text-xl font-semibold text-gray-900">Leave Request Details</h1>
        <p class="mt-1 text-xs sm:text-sm text-gray-500">Complete information for this leave request.</p>
    </div>

    @if(session('success'))
        <div class="mb-4 bg-green-50 border-l-4 border-green-400 p-3 sm:p-4 rounded-md">
            <p class="text-xs sm:text-sm text-green-700">{{ session('success') }}</p>
        </div>
    @endif
    @if(session('error'))
        <div class="mb-4 bg-red-50 border-l-4 border-red-400 p-3 sm:p-4 rounded-md">
            <p class="text-xs sm:text-sm text-red-700">{{ session('error') }}</p>
        </div>
    @endif

    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
        <!-- Applicant header with avatar and role -->
        <div class="px-4 sm:px-6 py-5 sm:py-6 border-b border-gray-200 bg-gray-50/50">
            <div class="flex flex-col sm:flex-row sm:items-center gap-4">
                <div class="flex-shrink-0">
                    <img src="{{ $profilePictureUrl }}" alt="" class="w-16 h-16 sm:w-20 sm:h-20 rounded-full object-cover border-2 border-white shadow ring-1 ring-gray-200">
                </div>
                <div class="flex-1 min-w-0">
                    <h2 class="text-base sm:text-lg font-semibold text-gray-900">{{ $lr->user?->name ?? 'N/A' }}</h2>
                    <p class="text-sm text-gray-500 mt-0.5">{{ $applicantId }}</p>
                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-indigo-100 text-indigo-800 mt-2">{{ $applicantRole }}</span>
                    <span class="inline-flex items-center ml-2 px-2.5 py-1 rounded-full text-xs font-medium
                        {{ $lr->status === 'pending' ? 'bg-amber-100 text-amber-800' : '' }}
                        {{ $lr->status === 'approved' ? 'bg-green-100 text-green-800' : '' }}
                        {{ $lr->status === 'rejected' ? 'bg-red-100 text-red-800' : '' }}
                    ">{{ ucfirst($lr->status) }}</span>
                </div>
            </div>
        </div>

        <!-- Leave details -->
        <dl class="px-4 sm:px-6 py-4 sm:py-5 divide-y divide-gray-200">
            <div class="py-3 sm:py-4 flex flex-col sm:flex-row sm:gap-4">
                <dt class="text-xs font-medium text-gray-500 uppercase tracking-wide sm:w-36 flex-shrink-0">Leave type</dt>
                <dd class="mt-0.5 sm:mt-0 text-sm font-medium text-gray-900">{{ ucfirst($lr->leave_type) }}</dd>
            </div>
            <div class="py-3 sm:py-4 flex flex-col sm:flex-row sm:gap-4">
                <dt class="text-xs font-medium text-gray-500 uppercase tracking-wide sm:w-36 flex-shrink-0">Duration</dt>
                <dd class="mt-0.5 sm:mt-0 text-sm font-medium text-gray-900">{{ $days }} day(s)</dd>
            </div>
            <div class="py-3 sm:py-4 flex flex-col sm:flex-row sm:gap-4">
                <dt class="text-xs font-medium text-gray-500 uppercase tracking-wide sm:w-36 flex-shrink-0">From</dt>
                <dd class="mt-0.5 sm:mt-0 text-sm font-medium text-gray-900">{{ $lr->start_date->format('l, M d, Y') }}</dd>
            </div>
            <div class="py-3 sm:py-4 flex flex-col sm:flex-row sm:gap-4">
                <dt class="text-xs font-medium text-gray-500 uppercase tracking-wide sm:w-36 flex-shrink-0">To</dt>
                <dd class="mt-0.5 sm:mt-0 text-sm font-medium text-gray-900">{{ $lr->end_date->format('l, M d, Y') }}</dd>
            </div>
            @if($lr->reason)
            <div class="py-3 sm:py-4 flex flex-col sm:flex-row sm:gap-4">
                <dt class="text-xs font-medium text-gray-500 uppercase tracking-wide sm:w-36 flex-shrink-0">Reason</dt>
                <dd class="mt-0.5 sm:mt-0 text-sm text-gray-700">{{ $lr->reason }}</dd>
            </div>
            @endif
            @if($lr->reviewed_at)
            <div class="py-3 sm:py-4 flex flex-col sm:flex-row sm:gap-4">
                <dt class="text-xs font-medium text-gray-500 uppercase tracking-wide sm:w-36 flex-shrink-0">Reviewed at</dt>
                <dd class="mt-0.5 sm:mt-0 text-sm text-gray-600">{{ $lr->reviewed_at->format('M d, Y H:i') }}</dd>
            </div>
            @endif
        </dl>

        @if($lr->status === 'pending')
        <div class="px-4 sm:px-6 py-4 border-t border-gray-200 bg-gray-50/30 flex flex-wrap gap-2">
            <form action="{{ route('hr.leave-requests.approve', $lr->id) }}" method="POST" class="inline">
                @csrf
                <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-green-600 rounded-lg hover:bg-green-700 shadow-sm">Approve</button>
            </form>
            <form action="{{ route('hr.leave-requests.reject', $lr->id) }}" method="POST" class="inline" onsubmit="return confirm('Reject this leave request?');">
                @csrf
                <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-red-600 rounded-lg hover:bg-red-700 shadow-sm">Reject</button>
            </form>
        </div>
        @endif
    </div>
</x-hr-layout>
