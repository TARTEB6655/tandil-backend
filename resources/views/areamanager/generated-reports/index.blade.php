<x-areamanager-layout>
    <div class="max-w-4xl mx-auto">
        {{-- Page header --}}
        <header class="mb-8">
            <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Generate Reports</h1>
            <p class="mt-1 text-sm text-gray-500">Create PDF or CSV reports by type and date range. Reports are ready right after generation.</p>
        </header>

        @if(session('success'))
            <div class="mb-6 flex items-center gap-3 rounded-xl bg-emerald-50 border border-emerald-200 px-4 py-3 text-sm text-emerald-800" role="alert">
                <svg class="w-5 h-5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                {{ session('success') }}
            </div>
        @endif
        @if(session('error'))
            <div class="mb-6 flex items-center gap-3 rounded-xl bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-800" role="alert">
                <svg class="w-5 h-5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/></svg>
                {{ session('error') }}
            </div>
        @endif

        {{-- Create report card --}}
        <section class="bg-white rounded-2xl border border-gray-200 shadow-sm mb-8 overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100 bg-gray-50/80">
                <h2 class="text-base font-semibold text-gray-900">Create new report</h2>
                <p class="text-xs text-gray-500 mt-0.5">Choose report type(s) and date range, then generate.</p>
            </div>
            <form action="{{ route('areamanager.generated-reports.store') }}" method="POST" class="p-5 sm:p-6" id="generate-form" onsubmit="return document.querySelectorAll('#generate-form input[name=\'types[]\']:checked').length > 0 || (alert('Please select at least one report type.'), false)">
                @csrf
                <div class="mb-5">
                    <p class="text-sm font-medium text-gray-700 mb-3">Report type</p>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <label class="flex items-center gap-3 p-3 rounded-xl border-2 border-gray-200 hover:border-indigo-300 hover:bg-indigo-50/30 cursor-pointer transition-colors has-[:checked]:border-indigo-500 has-[:checked]:bg-indigo-50/50">
                            <input type="checkbox" name="types[]" value="weekly_summary" class="w-4 h-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                            <span class="text-sm font-medium text-gray-800">Weekly Summary</span>
                        </label>
                        <label class="flex items-center gap-3 p-3 rounded-xl border-2 border-gray-200 hover:border-indigo-300 hover:bg-indigo-50/30 cursor-pointer transition-colors has-[:checked]:border-indigo-500 has-[:checked]:bg-indigo-50/50">
                            <input type="checkbox" name="types[]" value="team_performance" class="w-4 h-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                            <span class="text-sm font-medium text-gray-800">Team Performance</span>
                        </label>
                        <label class="flex items-center gap-3 p-3 rounded-xl border-2 border-gray-200 hover:border-indigo-300 hover:bg-indigo-50/30 cursor-pointer transition-colors has-[:checked]:border-indigo-500 has-[:checked]:bg-indigo-50/50">
                            <input type="checkbox" name="types[]" value="customer_satisfaction" class="w-4 h-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                            <span class="text-sm font-medium text-gray-800">Customer Satisfaction</span>
                        </label>
                    </div>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-5">
                    <div>
                        <label for="date_from" class="block text-sm font-medium text-gray-700 mb-1">From date</label>
                        <input type="date" name="date_from" id="date_from" value="{{ \Carbon\Carbon::now()->startOfMonth()->format('Y-m-d') }}" class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                    </div>
                    <div>
                        <label for="date_to" class="block text-sm font-medium text-gray-700 mb-1">To date</label>
                        <input type="date" name="date_to" id="date_to" value="{{ \Carbon\Carbon::now()->endOfMonth()->format('Y-m-d') }}" class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                    </div>
                </div>
                <div class="flex flex-wrap gap-3">
                    <button type="submit" class="min-h-[44px] min-w-[44px] inline-flex items-center justify-center gap-2 rounded-xl bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        Generate report
                    </button>
                    <a href="{{ route('areamanager.reports.index') }}" class="min-h-[44px] inline-flex items-center justify-center rounded-xl border border-gray-300 bg-white px-5 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50 focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">Visit reports</a>
                </div>
            </form>
        </section>

        {{-- Generated reports list --}}
        <section class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100">
                <h2 class="text-base font-semibold text-gray-900">Generated reports</h2>
                <p class="text-xs text-gray-500 mt-0.5">When status is Ready, use View or Download. Delete works anytime.</p>
            </div>
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
                    $fileAvailable = $r->file_path && \Illuminate\Support\Facades\Storage::disk('local')->exists($r->file_path);
                @endphp
                <div class="flex flex-col sm:flex-row sm:items-center gap-3 px-5 py-4 border-b border-gray-100 last:border-0 hover:bg-gray-50/50 transition-colors">
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-gray-900 truncate">{{ $typeLabel }}</p>
                        <p class="text-xs text-gray-500 mt-0.5">{{ $period }}</p>
                        @if($r->status === 'failed' && $r->failure_reason)
                            <p class="text-xs text-red-600 mt-1" title="{{ $r->failure_reason }}">Reason: {{ \Illuminate\Support\Str::limit($r->failure_reason, 80) }}</p>
                        @endif
                    </div>
                    <div class="flex items-center gap-2 flex-shrink-0">
                        @if($r->status === 'generated')
                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-emerald-100 text-emerald-800">Ready</span>
                        @elseif($r->status === 'pending')
                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-amber-100 text-amber-800">Pending</span>
                        @else
                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-red-100 text-red-800">Failed</span>
                        @endif
                    </div>
                    <div class="flex items-center gap-1 sm:gap-2 flex-wrap">
                        @if($fileAvailable)
                            <a href="{{ route('areamanager.generated-reports.view', ['id' => $r->id]) }}" target="_blank" rel="noopener noreferrer" class="min-h-[44px] min-w-[44px] inline-flex items-center justify-center rounded-lg border border-gray-200 bg-white text-gray-600 hover:bg-gray-50 hover:border-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-1" title="View in new tab" aria-label="View report">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                            </a>
                            <a href="{{ route('areamanager.generated-reports.download', ['id' => $r->id]) }}" class="min-h-[44px] min-w-[44px] inline-flex items-center justify-center rounded-lg border border-indigo-200 bg-indigo-50 text-indigo-700 hover:bg-indigo-100 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-1" title="Download PDF" aria-label="Download PDF">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                            </a>
                            <a href="{{ route('areamanager.generated-reports.download', ['id' => $r->id]) }}?format=csv" class="min-h-[44px] min-w-[44px] inline-flex items-center justify-center rounded-lg border border-gray-200 bg-white text-gray-600 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-400 focus:ring-offset-1" title="Download CSV" aria-label="Download CSV">
                                <span class="text-xs font-medium px-2">CSV</span>
                            </a>
                        @else
                            <span class="min-h-[44px] min-w-[44px] inline-flex items-center justify-center rounded-lg bg-gray-100 text-gray-400 cursor-not-allowed" aria-hidden="true" title="Available when Ready"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg></span>
                            <span class="min-h-[44px] min-w-[44px] inline-flex items-center justify-center rounded-lg bg-gray-100 text-gray-400 cursor-not-allowed" aria-hidden="true" title="Available when Ready"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg></span>
                        @endif
                        <form action="{{ route('areamanager.generated-reports.destroy', ['id' => $r->id]) }}" method="POST" class="inline" onsubmit="return confirm('Delete this report?');">
                            @csrf
                            <button type="submit" class="min-h-[44px] min-w-[44px] inline-flex items-center justify-center rounded-lg border border-red-200 bg-red-50 text-red-600 hover:bg-red-100 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-1" title="Delete" aria-label="Delete report">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                            </button>
                        </form>
                    </div>
                </div>
            @empty
                <div class="px-5 py-12 text-center">
                    <p class="text-sm text-gray-500">No reports yet. Select type(s) above and click Generate report.</p>
                </div>
            @endforelse
            @if($reports->hasPages())
                <div class="px-5 py-4 border-t border-gray-100">
                    {{ $reports->links() }}
                </div>
            @endif
        </section>
    </div>
</x-areamanager-layout>
