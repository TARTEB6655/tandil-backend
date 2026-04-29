<x-admin-layout>
    <div class="space-y-6">
        <div class="mb-6">
            <a href="{{ route('admin.report-management.index') }}" class="inline-flex items-center text-sm text-gray-500 hover:text-gray-700 mb-2">
                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
                Back to Report Management
            </a>
            <h1 class="text-xl font-semibold text-gray-900">Report Details</h1>
        </div>

        @if(session('success'))
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                {{ session('success') }}
            </div>
        @endif
        @if(session('error'))
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                {{ session('error') }}
            </div>
        @endif
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6 space-y-6">
            <div>
                <h2 class="text-lg font-medium text-gray-900 mb-2">{{ $report->title }}</h2>
                <div class="flex flex-wrap gap-2 mb-4">
                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800">{{ ucfirst($report->type) }}</span>
                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full
                        {{ $report->status === 'generated' ? 'bg-green-100 text-green-800' : '' }}
                        {{ $report->status === 'pending' ? 'bg-yellow-100 text-yellow-800' : '' }}
                        {{ $report->status === 'scheduled' ? 'bg-blue-100 text-blue-800' : '' }}
                        {{ $report->status === 'failed' ? 'bg-red-100 text-red-800' : '' }}">
                        {{ ucfirst($report->status) }}
                    </span>
                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-indigo-100 text-indigo-800">{{ strtoupper($report->format ?? 'pdf') }}</span>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-4 border-t border-gray-200">
                <div>
                    <p class="text-sm text-gray-500">Created by</p>
                    <p class="text-sm font-medium text-gray-900">{{ $report->creator->name ?? $report->creator->email ?? 'N/A' }}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Created at</p>
                    <p class="text-sm font-medium text-gray-900">{{ $report->created_at->format('M d, Y H:i') }}</p>
                </div>
                @if($report->scheduled_at)
                    <div>
                        <p class="text-sm text-gray-500">Scheduled at</p>
                        <p class="text-sm font-medium text-gray-900">{{ $report->scheduled_at->format('M d, Y H:i') }}</p>
                    </div>
                    @if($report->recurrence)
                        <div>
                            <p class="text-sm text-gray-500">Recurrence</p>
                            <p class="text-sm font-medium text-gray-900">{{ ucfirst($report->recurrence) }}</p>
                        </div>
                    @endif
                @endif
                @if($report->generated_at)
                    <div>
                        <p class="text-sm text-gray-500">Generated at</p>
                        <p class="text-sm font-medium text-gray-900">{{ $report->generated_at->format('M d, Y H:i') }}</p>
                    </div>
                @endif
                @if($report->status === 'failed' && $report->failure_reason)
                    <div class="sm:col-span-2">
                        <p class="text-sm text-gray-500">Failure reason</p>
                        <p class="text-sm text-red-600">{{ $report->failure_reason }}</p>
                    </div>
                @endif
            </div>

            <div class="pt-4 flex flex-wrap gap-3 border-t border-gray-200">
                @if($report->status === 'generated' && $report->file_path)
                    <a href="{{ route('admin.report-management.download', $report->id) }}" class="inline-flex items-center px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 font-medium text-sm">Download</a>
                @endif
                @if($report->status === 'scheduled')
                    <form action="{{ route('admin.report-management.cancel', $report->id) }}" method="POST" class="inline" onsubmit="return confirm('Cancel this scheduled report?');">
                        @csrf
                        <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 font-medium text-sm">Cancel scheduled</button>
                    </form>
                @endif
                <form action="{{ route('admin.report-management.destroy', $report->id) }}" method="POST" class="inline" onsubmit="return confirm('Delete this report?');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 font-medium text-sm">Delete</button>
                </form>
                <a href="{{ route('admin.report-management.index') }}" class="inline-flex items-center px-4 py-2 text-gray-600 hover:text-gray-900 font-medium text-sm">Back to list</a>
            </div>
        </div>
    </div>
</x-admin-layout>
