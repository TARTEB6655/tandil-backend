<x-hr-layout>
    <div class="max-w-5xl mx-auto px-4 sm:px-6 pb-10">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
            <div>
                <h1 class="text-xl sm:text-2xl font-semibold text-gray-900">Technician monthly report</h1>
                <p class="text-sm text-gray-500 mt-1">Preview on screen or queue a PDF (same engine as admin generated reports).</p>
            </div>
            <a href="{{ route('hr.dashboard') }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-800">← Dashboard</a>
        </div>

        @if (session('status'))
            <div class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('status') }}</div>
        @endif

        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 sm:p-6 mb-8">
            <h2 class="text-base font-semibold text-gray-900 mb-4">Select technician & month</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <form method="post" action="{{ route('hr.reports.technician-monthly.preview') }}" class="space-y-3">
                    @csrf
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Technician</label>
                        <select name="technician_id" class="w-full rounded-lg border-gray-300 text-sm" required>
                            <option value="">Choose…</option>
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
                    <button type="submit" class="w-full sm:w-auto px-4 py-2 rounded-lg bg-gray-800 text-white text-sm font-medium hover:bg-gray-900">Preview JSON</button>
                </form>

                <form method="post" action="{{ route('hr.reports.technician-monthly.generate') }}" class="space-y-3 border-t sm:border-t-0 sm:border-l border-gray-100 sm:pl-6 pt-4 sm:pt-0">
                    @csrf
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Technician</label>
                        <select name="technician_id" class="w-full rounded-lg border-gray-300 text-sm" required>
                            <option value="">Choose…</option>
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
                    <button type="submit" class="w-full sm:w-auto px-4 py-2 rounded-lg bg-pink-600 text-white text-sm font-medium hover:bg-pink-700">Queue PDF</button>
                </form>
            </div>
        </div>

        @if($preview)
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 sm:p-6 mb-8">
                <h2 class="text-base font-semibold text-gray-900 mb-2">Preview — {{ $preview['technician']['name'] ?? '' }} ({{ $preview['period']['month_label'] ?? '' }})</h2>
                <dl class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-sm mb-4">
                    <div class="rounded-lg bg-gray-50 p-3"><dt class="text-gray-500 text-xs">Leave days (approved)</dt><dd class="font-semibold text-gray-900">{{ $preview['summary']['approved_leave_days_in_month'] ?? 0 }}</dd></div>
                    <div class="rounded-lg bg-gray-50 p-3"><dt class="text-gray-500 text-xs">Days w/ completed job</dt><dd class="font-semibold text-gray-900">{{ $preview['summary']['days_with_completed_job'] ?? 0 }}</dd></div>
                    <div class="rounded-lg bg-gray-50 p-3"><dt class="text-gray-500 text-xs">Jobs completed</dt><dd class="font-semibold text-gray-900">{{ $preview['summary']['jobs_completed_in_month'] ?? 0 }}</dd></div>
                    <div class="rounded-lg bg-gray-50 p-3"><dt class="text-gray-500 text-xs">Visits in scope</dt><dd class="font-semibold text-gray-900">{{ $preview['summary']['jobs_scheduled_in_month'] ?? 0 }}</dd></div>
                </dl>
                <h3 class="text-sm font-semibold text-gray-800 mb-2">Completed jobs ({{ count($preview['visits'] ?? []) }})</h3>
                <ul class="text-sm text-gray-700 space-y-1 max-h-64 overflow-y-auto">
                    @foreach($preview['visits'] ?? [] as $v)
                        <li class="border-b border-gray-100 py-1">
                            #{{ $v['id'] }} — {{ $v['title'] ?? '' }} — {{ $v['status'] }} @if(!empty($v['client']['name'])) — {{ $v['client']['name'] }} @endif
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 sm:p-6">
            <h2 class="text-base font-semibold text-gray-900 mb-3">Your generated PDFs</h2>
            @if($myReports->isEmpty())
                <p class="text-sm text-gray-500">None yet. Use “Queue PDF” above.</p>
            @else
                <ul class="divide-y divide-gray-100 text-sm">
                    @foreach($myReports as $r)
                        <li class="py-3 flex flex-wrap items-center justify-between gap-2">
                            <div>
                                <p class="font-medium text-gray-900">{{ $r->title }}</p>
                                <p class="text-xs text-gray-500">{{ $r->created_at->format('Y-m-d H:i') }} · {{ $r->status }}</p>
                            </div>
                            @if($r->status === 'generated')
                                <a href="{{ route('hr.reports.generated.download', $r->id) }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-800">Download</a>
                            @else
                                <span class="text-xs text-gray-400">{{ ucfirst($r->status) }}</span>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>
</x-hr-layout>
