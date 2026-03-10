<x-areamanager-layout>
    {{-- Page title with spacing --}}
    <div class="mb-8 sm:mb-10">
        <h1 class="text-xl sm:text-2xl font-bold text-gray-900">Generate PDF Reports</h1>
        <p class="mt-2 text-sm text-gray-500">Select one or more report types and date range, then generate. Download when status is Ready.</p>
    </div>

    @if(session('success'))
        <div class="mb-6 rounded-xl bg-emerald-50 border border-emerald-200 px-4 py-3 text-sm text-emerald-800 flex items-center gap-2">
            <svg class="w-5 h-5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
            {{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div class="mb-6 rounded-xl bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-800 flex items-center gap-2">
            <svg class="w-5 h-5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/></svg>
            {{ session('error') }}
        </div>
    @endif

    {{-- New Report card - proper spacing --}}
    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden mb-10">
        <div class="px-6 py-5 sm:px-8 sm:py-6 border-b border-gray-200 bg-gray-50/50">
            <h2 class="text-lg font-semibold text-gray-900">New Report</h2>
            <p class="text-sm text-gray-500 mt-1">Select report type(s) and date range. You can select one, multiple, or all.</p>
        </div>
        <form action="{{ route('areamanager.generated-reports.store') }}" method="POST" class="p-6 sm:p-8" id="generate-report-form" onsubmit="return document.querySelectorAll('#generate-report-form input[name=\'types[]\']:checked').length > 0 || (alert('Please select at least one report type.'), false)">
            @csrf
            {{-- Report type selection --}}
            <div class="mb-6">
                <div class="flex items-center justify-between gap-2 mb-3">
                    <label class="text-sm font-medium text-gray-700">Report type(s)</label>
                    <button type="button" onclick="document.querySelectorAll('#generate-report-form input[name=\'types[]\']').forEach(c => c.checked = true)" class="text-xs font-medium text-indigo-600 hover:text-indigo-800">Select all</button>
                </div>
                <div class="flex flex-wrap gap-4 sm:gap-6">
                    <label class="inline-flex items-center gap-2.5 cursor-pointer">
                        <input type="checkbox" name="types[]" value="weekly_summary" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                        <span class="text-sm font-medium text-gray-800">Weekly Summary</span>
                    </label>
                    <label class="inline-flex items-center gap-2.5 cursor-pointer">
                        <input type="checkbox" name="types[]" value="team_performance" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                        <span class="text-sm font-medium text-gray-800">Team Performance</span>
                    </label>
                    <label class="inline-flex items-center gap-2.5 cursor-pointer">
                        <input type="checkbox" name="types[]" value="customer_satisfaction" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                        <span class="text-sm font-medium text-gray-800">Customer Satisfaction</span>
                    </label>
                </div>
                <p class="mt-2 text-xs text-gray-500">Select one or more. Each selected type will generate a separate PDF for the same date range.</p>
            </div>

            {{-- Date range --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-5 mb-6">
                <div>
                    <label for="date_from" class="block text-sm font-medium text-gray-700 mb-1.5">From date</label>
                    <input type="date" name="date_from" id="date_from" value="{{ \Carbon\Carbon::now()->startOfMonth()->format('Y-m-d') }}" class="w-full rounded-xl border border-gray-300 bg-white px-4 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                </div>
                <div>
                    <label for="date_to" class="block text-sm font-medium text-gray-700 mb-1.5">To date</label>
                    <input type="date" name="date_to" id="date_to" value="{{ \Carbon\Carbon::now()->endOfMonth()->format('Y-m-d') }}" class="w-full rounded-xl border border-gray-300 bg-white px-4 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                </div>
            </div>

            {{-- Buttons --}}
            <div class="flex flex-wrap gap-3 pt-2">
                <button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    Generate PDF
                </button>
                <a href="{{ route('areamanager.reports.index') }}" class="inline-flex items-center rounded-xl border border-gray-300 bg-white px-5 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">Visit reports</a>
            </div>
        </form>
    </div>

    {{-- Generated reports table - spacing --}}
    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="px-6 py-5 sm:px-8 sm:py-6 border-b border-gray-200 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <h2 class="text-lg font-semibold text-gray-900">Generated reports</h2>
                <p class="text-sm text-gray-500 mt-1">Refresh the page to see updated status. When status is <strong>Ready</strong>, use Download PDF.</p>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[600px] divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 sm:px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Report</th>
                        <th class="px-4 sm:px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider hidden sm:table-cell">Period</th>
                        <th class="px-4 sm:px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-4 sm:px-6 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 bg-white">
                    @forelse($reports as $r)
                        @php
                            $params = $r->parameters ?? [];
                            $start = isset($params['start_date']) ? \Carbon\Carbon::parse($params['start_date']) : null;
                            $end = isset($params['end_date']) ? \Carbon\Carbon::parse($params['end_date']) : null;
                            $period = $start && $end ? $start->format('M j') . ' – ' . $end->format('M j, Y') : ($r->created_at?->format('M j, Y') ?? '–');
                            $typeLabel = match($r->type) {
                                'operational' => 'Weekly Summary',
                                'performance' => 'Team Performance',
                                'customer' => 'Customer Satisfaction',
                                default => $r->title
                            };
                        @endphp
                        <tr class="hover:bg-gray-50/80 transition-colors">
                            <td class="px-4 sm:px-6 py-4">
                                <p class="text-sm font-medium text-gray-900">{{ $typeLabel }}</p>
                                <p class="text-xs text-gray-500 mt-0.5 sm:hidden">{{ $period }}</p>
                            </td>
                            <td class="px-4 sm:px-6 py-4 text-sm text-gray-600 hidden sm:table-cell">{{ $period }}</td>
                            <td class="px-4 sm:px-6 py-4">
                                @if($r->status === 'generated')
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-emerald-100 text-emerald-800">Ready</span>
                                @elseif($r->status === 'pending')
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-amber-100 text-amber-800">Pending</span>
                                @else
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-red-100 text-red-800">Failed</span>
                                @endif
                            </td>
                            <td class="px-4 sm:px-6 py-4 text-right">
                                <div class="flex flex-wrap items-center justify-end gap-2">
                                    @if($r->status === 'generated' && $r->file_path)
                                        <a href="{{ route('areamanager.generated-reports.download', $r->id) }}" class="inline-flex items-center gap-1.5 text-sm font-medium text-indigo-600 hover:text-indigo-800" title="Download PDF">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                            Download
                                        </a>
                                        <a href="{{ route('areamanager.generated-reports.view', $r->id) }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-1.5 text-sm font-medium text-gray-700 hover:text-gray-900" title="View PDF in new tab">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                            View
                                        </a>
                                    @else
                                        <span class="text-sm text-gray-500">Ready to download after generation</span>
                                    @endif
                                    <form action="{{ route('areamanager.generated-reports.destroy', $r->id) }}" method="POST" class="inline" onsubmit="return confirm('Delete this report?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="inline-flex items-center gap-1.5 text-sm font-medium text-red-600 hover:text-red-800" title="Delete report">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                            Delete
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-4 sm:px-6 py-12 text-center">
                                <p class="text-sm text-gray-500">No generated reports yet. Select report type(s) above and click Generate PDF.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($reports->hasPages())
            <div class="px-4 sm:px-6 py-4 border-t border-gray-200">
                {{ $reports->links() }}
            </div>
        @endif
    </div>
</x-areamanager-layout>
