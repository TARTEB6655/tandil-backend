<x-hr-layout>
    <div class="mb-4 sm:mb-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 sm:gap-0">
            <div>
                <h1 class="text-lg sm:text-xl font-medium text-gray-900">Assign Visits</h1>
                <p class="mt-1 text-xs sm:text-sm text-gray-500">Company-wide list. Assign a technician to offer the job (same accept window as supervisor flow).</p>
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
    @if ($errors->has('assign'))
        <div class="mb-4 bg-red-50 border-l-4 border-red-400 p-3 sm:p-4 rounded-md">
            <p class="text-xs sm:text-sm text-red-700">{{ $errors->first('assign') }}</p>
        </div>
    @endif

    <form method="get" action="{{ route('hr.visit-assignments.index') }}" class="mb-4 sm:mb-6 flex flex-wrap items-end gap-3 bg-white rounded-xl border border-gray-200 p-3 sm:p-4">
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Scope</label>
                <select name="scope" class="rounded-lg border-gray-300 text-sm">
                    <option value="assignable" @selected($scope === 'assignable')>Assignable</option>
                    <option value="all" @selected($scope === 'all')>All visits</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">From</label>
                <input type="date" name="date_from" value="{{ request('date_from') }}" class="rounded-lg border-gray-300 text-sm" />
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">To</label>
                <input type="date" name="date_to" value="{{ request('date_to') }}" class="rounded-lg border-gray-300 text-sm" />
            </div>
        <button type="submit" class="inline-flex items-center px-4 py-2 rounded-lg bg-indigo-600 text-white text-xs sm:text-sm font-medium hover:bg-indigo-700">Filter</button>
    </form>

    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full divide-y divide-gray-200 min-w-[900px]">
                <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left font-medium text-gray-600">ID</th>
                            <th class="px-4 py-3 text-left font-medium text-gray-600">Scheduled</th>
                            <th class="px-4 py-3 text-left font-medium text-gray-600">Status</th>
                            <th class="px-4 py-3 text-left font-medium text-gray-600">Client / Area</th>
                            <th class="px-4 py-3 text-left font-medium text-gray-600">Technician</th>
                            <th class="px-4 py-3 text-left font-medium text-gray-600">Assign</th>
                        </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                        @forelse($visits as $visit)
                            @php
                                $clientName = $visit->subscription?->client?->name ?? '—';
                                $areaName = $visit->area?->name ?? '—';
                            @endphp
                            <tr class="hover:bg-gray-50/80">
                                <td class="px-4 py-3 font-mono text-xs">{{ $visit->id }}</td>
                                <td class="px-4 py-3 whitespace-nowrap text-xs sm:text-sm">{{ $visit->scheduled_date?->format('Y-m-d') ?? '—' }}</td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">{{ $visit->status }}</span>
                                </td>
                                <td class="px-4 py-3 max-w-[200px]">
                                    <div class="truncate text-gray-900">{{ $clientName }}</div>
                                    <div class="text-xs text-gray-500 truncate">{{ $areaName }}</div>
                                </td>
                                <td class="px-4 py-3 text-xs sm:text-sm">{{ $visit->technician?->name ?? '—' }}</td>
                                <td class="px-4 py-3">
                                    <form method="post" action="{{ route('hr.visit-assignments.assign', $visit->id) }}" class="flex flex-col gap-2 min-w-[200px]">
                                        @csrf
                                        <select name="technician_id" class="rounded-lg border-gray-300 text-xs" required>
                                            <option value="">Technician…</option>
                                            @foreach($technicians as $t)
                                                <option value="{{ $t->id }}">{{ $t->name }}</option>
                                            @endforeach
                                        </select>
                                        <input type="date" name="scheduled_date" value="{{ $visit->scheduled_date?->format('Y-m-d') }}" class="rounded-lg border-gray-300 text-xs" />
                                        <input type="text" name="note" placeholder="Note (optional)" class="rounded-lg border-gray-300 text-xs" maxlength="1000" />
                                        <button type="submit" class="px-3 py-1.5 rounded-lg bg-indigo-600 text-white text-xs font-medium hover:bg-indigo-700">Offer job</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-10 text-center text-xs sm:text-sm text-gray-500">No visits for this filter.</td>
                            </tr>
                        @endforelse
                </tbody>
            </table>
        </div>
        @if($visits->hasPages())
            <div class="px-4 sm:px-6 py-3 sm:py-4 border-t border-gray-200 bg-gray-50">
                <div class="flex flex-col sm:flex-row items-center justify-between gap-3 sm:gap-0">
                    <div class="text-xs sm:text-sm text-gray-600">
                        Showing
                        <span class="font-medium text-gray-900">{{ $visits->firstItem() ?? 0 }}</span>
                        to
                        <span class="font-medium text-gray-900">{{ $visits->lastItem() ?? 0 }}</span>
                        of
                        <span class="font-medium text-gray-900">{{ $visits->total() }}</span>
                        results
                    </div>
                    <div class="flex items-center justify-center">
                        {{ $visits->withQueryString()->links() }}
                    </div>
                </div>
            </div>
        @elseif($visits->count() > 0)
            <div class="px-4 sm:px-6 py-3 sm:py-4 border-t border-gray-200 bg-gray-50">
                <div class="text-xs sm:text-sm text-gray-600 text-center">
                    Showing all
                    <span class="font-medium text-gray-900">{{ $visits->count() }}</span>
                    {{ $visits->count() === 1 ? 'result' : 'results' }}
                </div>
            </div>
        @endif
    </div>
</x-hr-layout>
