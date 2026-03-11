<x-hr-layout>
    <div class="mb-4 sm:mb-6">
        <h1 class="text-lg sm:text-xl font-medium text-gray-900">Manage Leave Requests</h1>
        <p class="mt-1 text-xs sm:text-sm text-gray-500">Review and approve or reject employee leave requests.</p>
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

    <!-- Status tabs -->
    <div class="flex flex-wrap gap-2 mb-4 sm:mb-6">
        <a href="{{ route('hr.leave-requests.index', ['status' => 'pending']) }}"
           class="px-4 py-2 rounded-lg text-sm font-medium transition-colors {{ ($status ?? 'pending') === 'pending' ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">
            Pending {{ $pendingCount ?? 0 }}
        </a>
        <a href="{{ route('hr.leave-requests.index', ['status' => 'approved']) }}"
           class="px-4 py-2 rounded-lg text-sm font-medium transition-colors {{ ($status ?? '') === 'approved' ? 'bg-green-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">
            Approved {{ $approvedCount ?? 0 }}
        </a>
        <a href="{{ route('hr.leave-requests.index', ['status' => 'rejected']) }}"
           class="px-4 py-2 rounded-lg text-sm font-medium transition-colors {{ ($status ?? '') === 'rejected' ? 'bg-red-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">
            Rejected {{ $rejectedCount ?? 0 }}
        </a>
    </div>

    <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
        <div class="divide-y divide-gray-200">
            @forelse($leaveRequests as $lr)
                @php
                    $applicant = $lr->user;
                    $applicantId = $applicant?->employee?->employee_id ?? ('EMP-' . $applicant?->id);
                    $days = $lr->start_date->diffInDays($lr->end_date) + 1;
                @endphp
                <div class="px-4 sm:px-6 py-4 sm:py-5 hover:bg-gray-50/50 transition-colors">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                        <div class="flex-1 min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="text-sm font-semibold text-gray-900">{{ $applicant?->name ?? 'N/A' }}</p>
                                <span class="px-2 py-0.5 rounded-full text-xs font-medium
                                    {{ $lr->status === 'pending' ? 'bg-amber-100 text-amber-800' : '' }}
                                    {{ $lr->status === 'approved' ? 'bg-green-100 text-green-800' : '' }}
                                    {{ $lr->status === 'rejected' ? 'bg-red-100 text-red-800' : '' }}
                                ">{{ ucfirst($lr->status) }}</span>
                            </div>
                            <p class="text-xs text-gray-500 mt-1">{{ $applicantId }}</p>
                            <p class="text-sm text-gray-600 mt-1">{{ $lr->leave_type }} · {{ $days }} day(s)</p>
                            <p class="text-xs text-gray-500 mt-1">{{ $lr->start_date->format('M d, Y') }} – {{ $lr->end_date->format('M d, Y') }}</p>
                            @if($lr->reason)
                                <p class="text-xs text-gray-600 mt-2"><span class="font-medium">Reason:</span> {{ $lr->reason }}</p>
                            @endif
                        </div>
                        @if($lr->status === 'pending')
                            <div class="flex flex-wrap gap-2 flex-shrink-0">
                                <form action="{{ route('hr.leave-requests.approve', $lr->id) }}" method="POST" class="inline">
                                    @csrf
                                    <button type="submit" class="px-3 py-1.5 text-sm font-medium text-white bg-green-600 rounded-lg hover:bg-green-700">
                                        Approve
                                    </button>
                                </form>
                                <form action="{{ route('hr.leave-requests.reject', $lr->id) }}" method="POST" class="inline" onsubmit="return confirm('Reject this leave request?');">
                                    @csrf
                                    <button type="submit" class="px-3 py-1.5 text-sm font-medium text-white bg-red-600 rounded-lg hover:bg-red-700">
                                        Reject
                                    </button>
                                </form>
                            </div>
                        @endif
                    </div>
                </div>
            @empty
                <div class="px-4 sm:px-6 py-12 text-center">
                    <p class="text-sm text-gray-500">No {{ $status ?? 'pending' }} leave requests.</p>
                    @if(($status ?? 'pending') !== 'pending')
                        <a href="{{ route('hr.leave-requests.index', ['status' => 'pending']) }}" class="mt-2 inline-block text-sm text-indigo-600 hover:text-indigo-900">View pending</a>
                    @endif
                </div>
            @endforelse
        </div>
        @if($leaveRequests->hasPages())
            <div class="px-4 sm:px-6 py-3 border-t border-gray-200">
                {{ $leaveRequests->links() }}
            </div>
        @endif
    </div>
</x-hr-layout>
