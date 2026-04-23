<x-hr-layout>
    <div class="mb-4 sm:mb-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 sm:gap-0">
            <div>
                <h1 class="text-lg sm:text-xl font-medium text-gray-900">Technician Monthly Reports</h1>
                <p class="mt-1 text-xs sm:text-sm text-gray-500">Preview data and generate PDF instantly (no queue wait).</p>
            </div>
            <a href="{{ route('hr.dashboard') }}"
               class="w-full sm:w-auto px-4 py-2 text-xs sm:text-sm font-medium text-white bg-gray-700 rounded-lg hover:bg-gray-800 text-center">
                Back to Dashboard
            </a>
        </div>
    </div>

    @if (session('status'))
        <div class="mb-4 bg-green-50 border-l-4 border-green-400 p-3 sm:p-4 rounded-md">
            <p class="text-xs sm:text-sm text-green-700">{{ session('status') }}</p>
        </div>
    @endif
    @if($errors->any())
        <div class="mb-4 bg-red-50 border-l-4 border-red-400 p-3 sm:p-4 rounded-md">
            <p class="text-xs sm:text-sm text-red-700">{{ $errors->first() }}</p>
        </div>
    @endif

    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 sm:p-6 mb-6">
        <h2 class="text-base font-semibold text-gray-900 mb-4">Select Technician and Month</h2>
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
            <form method="post" action="{{ route('hr.reports.technician-monthly.preview') }}" class="space-y-3">
                @csrf
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">Technician</label>
                    <select name="technician_id" class="w-full rounded-lg border-gray-300 text-sm" required>
                        <option value="">Choose...</option>
                        @foreach($technicians as $t)
                            <option value="{{ $t->id }}" @selected(old('technician_id', $selectedTechId) == $t->id)>{{ $t->name }} ({{ $t->employee?->employee_id ?? 'TECH-'.$t->id }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex gap-2">
                    <div class="flex-1">
                        <label class="block text-xs font-medium text-gray-500 mb-1">Year</label>
                        <input type="number" name="year" min="2000" max="2100" value="{{ old('year', $defaultYear) }}" class="w-full rounded-lg border-gray-300 text-sm" required />
                    </div>
                    <div class="flex-1">
                        <label class="block text-xs font-medium text-gray-500 mb-1">Month</label>
                        <input type="number" name="month" min="1" max="12" value="{{ old('month', $defaultMonth) }}" class="w-full rounded-lg border-gray-300 text-sm" required />
                    </div>
                </div>
                <button type="submit" class="w-full sm:w-auto px-4 py-2 rounded-lg bg-gray-800 text-white text-sm font-medium hover:bg-gray-900">Preview Detail</button>
            </form>

            <form method="post" action="{{ route('hr.reports.technician-monthly.generate') }}" class="space-y-3 border-t lg:border-t-0 lg:border-l border-gray-100 lg:pl-6 pt-4 lg:pt-0">
                @csrf
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">Technician</label>
                    <select name="technician_id" class="w-full rounded-lg border-gray-300 text-sm" required>
                        <option value="">Choose...</option>
                        @foreach($technicians as $t)
                            <option value="{{ $t->id }}" @selected(old('technician_id', $selectedTechId) == $t->id)>{{ $t->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex gap-2">
                    <div class="flex-1">
                        <label class="block text-xs font-medium text-gray-500 mb-1">Year</label>
                        <input type="number" name="year" min="2000" max="2100" value="{{ old('year', $defaultYear) }}" class="w-full rounded-lg border-gray-300 text-sm" required />
                    </div>
                    <div class="flex-1">
                        <label class="block text-xs font-medium text-gray-500 mb-1">Month</label>
                        <input type="number" name="month" min="1" max="12" value="{{ old('month', $defaultMonth) }}" class="w-full rounded-lg border-gray-300 text-sm" required />
                    </div>
                </div>
                <button type="submit" class="w-full sm:w-auto px-4 py-2 rounded-lg bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-700">Generate & Download PDF</button>
            </form>
        </div>
    </div>

    @if($preview)
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 sm:p-6 mb-6">
            <h2 class="text-base font-semibold text-gray-900 mb-2">Preview - {{ $preview['technician']['name'] ?? '' }} ({{ $preview['period']['month_label'] ?? '' }})</h2>
            <dl class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-sm mb-4">
                <div class="rounded-lg bg-gray-50 p-3"><dt class="text-gray-500 text-xs">Leave days (approved)</dt><dd class="font-semibold text-gray-900">{{ $preview['summary']['approved_leave_days_in_month'] ?? 0 }}</dd></div>
                <div class="rounded-lg bg-gray-50 p-3"><dt class="text-gray-500 text-xs">Days with completed job</dt><dd class="font-semibold text-gray-900">{{ $preview['summary']['days_with_completed_job'] ?? 0 }}</dd></div>
                <div class="rounded-lg bg-gray-50 p-3"><dt class="text-gray-500 text-xs">Jobs completed</dt><dd class="font-semibold text-gray-900">{{ $preview['summary']['jobs_completed_in_month'] ?? 0 }}</dd></div>
                <div class="rounded-lg bg-gray-50 p-3"><dt class="text-gray-500 text-xs">Visits in scope</dt><dd class="font-semibold text-gray-900">{{ $preview['summary']['jobs_scheduled_in_month'] ?? 0 }}</dd></div>
            </dl>
            <h3 class="text-sm font-semibold text-gray-800 mb-2">Completed jobs ({{ count($preview['visits'] ?? []) }})</h3>
            <ul class="text-sm text-gray-700 space-y-1 max-h-64 overflow-y-auto">
                @foreach($preview['visits'] ?? [] as $v)
                    <li class="border-b border-gray-100 py-1">
                        #{{ $v['id'] }} - {{ $v['title'] ?? '' }} - {{ $v['status'] }} @if(!empty($v['client']['name'])) - {{ $v['client']['name'] }} @endif
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
        <div class="px-4 sm:px-6 py-3 sm:py-4 border-b border-gray-200">
            <h2 class="text-base sm:text-lg font-medium text-gray-900">Your Generated PDFs</h2>
        </div>
        <div class="p-4 sm:p-6">
            @if($myReports->isEmpty())
                <p class="text-sm text-gray-500">No generated reports yet. Use Generate & Download PDF above.</p>
            @else
                <ul class="divide-y divide-gray-100 text-sm">
                    @foreach($myReports as $r)
                        <li class="py-3 flex flex-wrap items-center justify-between gap-2">
                            <div>
                                <p class="font-medium text-gray-900">{{ $r->title }}</p>
                                <p class="text-xs text-gray-500">{{ $r->created_at->format('Y-m-d H:i') }} - {{ ucfirst($r->status) }}</p>
                            </div>
                            <div class="flex items-center gap-3">
                                <a href="{{ route('hr.reports.generated.download', $r->id) }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-800">Download</a>
                                <form method="post" action="{{ route('hr.reports.generated.destroy', $r->id) }}" onsubmit="return confirm('Delete this report?')" class="inline-flex">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="inline-flex items-center justify-center rounded-md p-1.5 text-red-500 hover:bg-red-50 hover:text-red-700" title="Delete report" aria-label="Delete report">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                            <path d="M3 6h18"></path>
                                            <path d="M8 6V4a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1v2"></path>
                                            <path d="M19 6l-1 14a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1L5 6"></path>
                                            <path d="M10 11v6"></path>
                                            <path d="M14 11v6"></path>
                                        </svg>
                                    </button>
                                </form>
                            </div>
                        </li>
                    @endforeach
                </ul>
                @if($myReports->hasPages())
                    <div class="mt-4 border-t border-gray-200 pt-4">
                        <div class="flex flex-col sm:flex-row items-center justify-between gap-3 sm:gap-0">
                            <div class="text-xs sm:text-sm text-gray-600">
                                Showing
                                <span class="font-medium text-gray-900">{{ $myReports->firstItem() ?? 0 }}</span>
                                to
                                <span class="font-medium text-gray-900">{{ $myReports->lastItem() ?? 0 }}</span>
                                of
                                <span class="font-medium text-gray-900">{{ $myReports->total() }}</span>
                                reports
                            </div>
                            <nav class="flex items-center gap-1" aria-label="Reports pagination">
                                @if($myReports->onFirstPage())
                                    <span class="px-3 py-1.5 text-xs sm:text-sm text-gray-400 bg-gray-100 rounded-md cursor-not-allowed">Prev</span>
                                @else
                                    <a href="{{ $myReports->previousPageUrl() }}" class="px-3 py-1.5 text-xs sm:text-sm text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50">Prev</a>
                                @endif

                                @foreach($myReports->getUrlRange(max(1, $myReports->currentPage() - 1), min($myReports->lastPage(), $myReports->currentPage() + 1)) as $page => $url)
                                    @if($page == $myReports->currentPage())
                                        <span class="px-3 py-1.5 text-xs sm:text-sm text-white bg-indigo-600 rounded-md">{{ $page }}</span>
                                    @else
                                        <a href="{{ $url }}" class="px-3 py-1.5 text-xs sm:text-sm text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50">{{ $page }}</a>
                                    @endif
                                @endforeach

                                @if($myReports->hasMorePages())
                                    <a href="{{ $myReports->nextPageUrl() }}" class="px-3 py-1.5 text-xs sm:text-sm text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50">Next</a>
                                @else
                                    <span class="px-3 py-1.5 text-xs sm:text-sm text-gray-400 bg-gray-100 rounded-md cursor-not-allowed">Next</span>
                                @endif
                            </nav>
                        </div>
                    </div>
                @endif
            @endif
        </div>
    </div>
</x-hr-layout>
