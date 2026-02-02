<x-admin-layout>
    <div class="space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
            <div>
                <a href="{{ route('admin.dashboard') }}" class="inline-flex items-center text-sm text-gray-500 hover:text-gray-700 mb-2">
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
                    Back to Dashboard
                </a>
                <h1 class="text-xl font-semibold text-gray-900">Report Management</h1>
                <p class="mt-1 text-sm text-gray-500">Generate, schedule, and manage generated reports (financial, performance, etc.).</p>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('admin.report-management.create') }}" class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 font-medium text-sm shadow-sm">
                    Generate Report
                </a>
                <a href="{{ route('admin.report-management.schedule.create') }}" class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 font-medium text-sm">
                    Schedule Report
                </a>
            </div>
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

        <!-- Stats -->
        <div class="grid grid-cols-2 sm:grid-cols-5 gap-3 mb-6">
            <div class="bg-white rounded-lg border border-gray-200 p-4 shadow-sm">
                <p class="text-xs font-medium text-gray-500 uppercase">Total</p>
                <p class="text-lg font-semibold text-gray-900">{{ $statistics['total'] }}</p>
            </div>
            <div class="bg-white rounded-lg border border-gray-200 p-4 shadow-sm">
                <p class="text-xs font-medium text-gray-500 uppercase">Pending</p>
                <p class="text-lg font-semibold text-yellow-600">{{ $statistics['pending'] }}</p>
            </div>
            <div class="bg-white rounded-lg border border-gray-200 p-4 shadow-sm">
                <p class="text-xs font-medium text-gray-500 uppercase">Generated</p>
                <p class="text-lg font-semibold text-green-600">{{ $statistics['generated'] }}</p>
            </div>
            <div class="bg-white rounded-lg border border-gray-200 p-4 shadow-sm">
                <p class="text-xs font-medium text-gray-500 uppercase">Scheduled</p>
                <p class="text-lg font-semibold text-blue-600">{{ $statistics['scheduled'] }}</p>
            </div>
            <div class="bg-white rounded-lg border border-gray-200 p-4 shadow-sm">
                <p class="text-xs font-medium text-gray-500 uppercase">Failed</p>
                <p class="text-lg font-semibold text-red-600">{{ $statistics['failed'] }}</p>
            </div>
        </div>

        <!-- Filters -->
        <div class="bg-white shadow rounded-lg p-4 mb-6">
            <form method="GET" action="{{ route('admin.report-management.index') }}" class="flex flex-wrap gap-4">
                <select name="status" class="rounded-md border-gray-300">
                    <option value="">All statuses</option>
                    @foreach(\App\Models\AdminReport::STATUSES as $s)
                        <option value="{{ $s }}" {{ request('status') == $s ? 'selected' : '' }}>{{ ucfirst($s) }}</option>
                    @endforeach
                </select>
                <select name="type" class="rounded-md border-gray-300">
                    <option value="">All types</option>
                    @foreach(\App\Models\AdminReport::TYPES as $t)
                        <option value="{{ $t }}" {{ request('type') == $t ? 'selected' : '' }}>{{ ucfirst($t) }}</option>
                    @endforeach
                </select>
                <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700">Filter</button>
                <a href="{{ route('admin.report-management.index') }}" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300">Clear</a>
            </form>
        </div>

        <!-- Reports Table -->
        <div class="bg-white shadow rounded-lg overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Title</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($reports as $report)
                        <tr>
                            <td class="px-6 py-4">
                                <div class="text-sm font-medium text-gray-900">{{ $report->title }}</div>
                                @if($report->creator)
                                    <div class="text-xs text-gray-500">by {{ $report->creator->name ?? $report->creator->email }}</div>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ ucfirst($report->type) }}</td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full
                                    {{ $report->status === 'generated' ? 'bg-green-100 text-green-800' : '' }}
                                    {{ $report->status === 'pending' ? 'bg-yellow-100 text-yellow-800' : '' }}
                                    {{ $report->status === 'scheduled' ? 'bg-blue-100 text-blue-800' : '' }}
                                    {{ $report->status === 'failed' ? 'bg-red-100 text-red-800' : '' }}">
                                    {{ ucfirst($report->status) }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                {{ $report->created_at->format('M d, Y H:i') }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <a href="{{ route('admin.report-management.show', $report->id) }}" class="text-indigo-600 hover:text-indigo-900 mr-2">View</a>
                                @if($report->status === 'generated' && $report->file_path)
                                    <a href="{{ route('admin.report-management.download', $report->id) }}" class="text-green-600 hover:text-green-900 mr-2">Download</a>
                                @endif
                                @if($report->status === 'scheduled')
                                    <form action="{{ route('admin.report-management.cancel', $report->id) }}" method="POST" class="inline" onsubmit="return confirm('Cancel this scheduled report?');">
                                        @csrf
                                        <button type="submit" class="text-red-600 hover:text-red-900">Cancel</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-8 text-center text-sm text-gray-500">No reports yet. Generate or schedule one above.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $reports->withQueryString()->links() }}
        </div>
    </div>
</x-admin-layout>
