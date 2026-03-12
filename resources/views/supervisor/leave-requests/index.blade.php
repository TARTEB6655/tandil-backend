<x-supervisor-layout>
    <div class="mb-4 sm:mb-6">
        <h1 class="text-lg sm:text-xl font-medium text-gray-900">My Leave Requests</h1>
        <p class="mt-1 text-xs sm:text-sm text-gray-500">Apply for leave or view your submitted requests. HR will approve or reject.</p>
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

    <div class="mb-4 flex flex-wrap items-center gap-3">
        <a href="{{ route('supervisor.leave-requests.create') }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium bg-indigo-600 text-white hover:bg-indigo-700">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
            Apply for Leave
        </a>
    </div>

    <!-- Status tabs -->
    <div class="flex flex-wrap gap-2 mb-4 sm:mb-6">
        <a href="{{ route('supervisor.leave-requests.index') }}"
           class="px-4 py-2 rounded-lg text-sm font-medium transition-colors {{ ($status ?? '') === '' ? 'bg-gray-700 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">
            All
        </a>
        <a href="{{ route('supervisor.leave-requests.index', ['status' => 'pending']) }}"
           class="px-4 py-2 rounded-lg text-sm font-medium transition-colors {{ ($status ?? '') === 'pending' ? 'bg-amber-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">
            Pending {{ $pendingCount ?? 0 }}
        </a>
        <a href="{{ route('supervisor.leave-requests.index', ['status' => 'approved']) }}"
           class="px-4 py-2 rounded-lg text-sm font-medium transition-colors {{ ($status ?? '') === 'approved' ? 'bg-green-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">
            Approved {{ $approvedCount ?? 0 }}
        </a>
        <a href="{{ route('supervisor.leave-requests.index', ['status' => 'rejected']) }}"
           class="px-4 py-2 rounded-lg text-sm font-medium transition-colors {{ ($status ?? '') === 'rejected' ? 'bg-red-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">
            Rejected {{ $rejectedCount ?? 0 }}
        </a>
    </div>

    <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
        <div class="divide-y divide-gray-200">
            @forelse($leaveRequests as $lr)
                @php
                    $days = $lr->start_date->diffInDays($lr->end_date) + 1;
                @endphp
                <div class="px-4 sm:px-6 py-4 sm:py-5 hover:bg-gray-50/50 transition-colors">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="text-sm font-semibold text-gray-900">{{ ucfirst($lr->leave_type) }}</p>
                                <span class="px-2 py-0.5 rounded-full text-xs font-medium
                                    {{ $lr->status === 'pending' ? 'bg-amber-100 text-amber-800' : '' }}
                                    {{ $lr->status === 'approved' ? 'bg-green-100 text-green-800' : '' }}
                                    {{ $lr->status === 'rejected' ? 'bg-red-100 text-red-800' : '' }}
                                ">{{ ucfirst($lr->status) }}</span>
                            </div>
                            <p class="text-sm text-gray-600 mt-1">{{ $days }} day(s) · {{ $lr->start_date->format('M d, Y') }} – {{ $lr->end_date->format('M d, Y') }}</p>
                            @if($lr->reason)
                                <p class="text-xs text-gray-500 mt-2">{{ \Illuminate\Support\Str::limit($lr->reason, 100) }}</p>
                            @endif
                            @if($lr->reviewed_at)
                                <p class="text-xs text-gray-400 mt-1">Reviewed {{ $lr->reviewed_at->format('M d, Y H:i') }}</p>
                            @endif
                        </div>
                    </div>
                </div>
            @empty
                <div class="px-4 sm:px-6 py-12 text-center">
                    <p class="text-sm text-gray-500">No leave requests found.</p>
                    <a href="{{ route('supervisor.leave-requests.create') }}" class="mt-2 inline-block text-sm text-indigo-600 hover:text-indigo-900 font-medium">Apply for leave</a>
                </div>
            @endforelse
        </div>
        @if($leaveRequests->hasPages())
            <div class="px-4 sm:px-6 py-3 border-t border-gray-200">
                {{ $leaveRequests->links() }}
            </div>
        @endif
    </div>
</x-supervisor-layout>
