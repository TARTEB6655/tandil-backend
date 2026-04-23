<x-hr-layout>
    <div class="mb-4 sm:mb-6">
        <h1 class="text-lg sm:text-xl font-medium text-gray-900">Assign Visits</h1>
        <p class="mt-1 text-xs sm:text-sm text-gray-500">Manage unassigned jobs and quickly offer them to technicians.</p>
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

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 sm:gap-4 mb-4 sm:mb-6">
        <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm">
            <p class="text-xs text-gray-500 uppercase tracking-wide">Total Jobs</p>
            <p class="mt-1 text-xl font-semibold text-gray-900">{{ $total ?? 0 }}</p>
        </div>
        <div class="bg-white rounded-xl border border-red-100 p-4 shadow-sm">
            <p class="text-xs text-red-500 uppercase tracking-wide">Unassigned</p>
            <p class="mt-1 text-xl font-semibold text-red-600">{{ $unassigned ?? 0 }}</p>
        </div>
        <div class="bg-white rounded-xl border border-amber-100 p-4 shadow-sm">
            <p class="text-xs text-amber-500 uppercase tracking-wide">Pending Acceptance</p>
            <p class="mt-1 text-xl font-semibold text-amber-600">{{ $pendingAcceptance ?? 0 }}</p>
        </div>
    </div>

    <form method="get" action="{{ route('hr.visit-assignments.index') }}" class="mb-4 sm:mb-6 bg-white rounded-xl border border-gray-200 p-3 sm:p-4">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Scope</label>
                <select name="scope" class="w-full rounded-lg border-gray-300 text-sm">
                    <option value="assignable" @selected($scope === 'assignable')>Assignable</option>
                    <option value="all" @selected($scope === 'all')>All Visits</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">From</label>
                <input type="date" name="date_from" value="{{ request('date_from') }}" class="w-full rounded-lg border-gray-300 text-sm" />
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">To</label>
                <input type="date" name="date_to" value="{{ request('date_to') }}" class="w-full rounded-lg border-gray-300 text-sm" />
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Per Page</label>
                <select name="per_page" class="w-full rounded-lg border-gray-300 text-sm">
                    <option value="6" @selected(($perPage ?? 12) == 6)>6</option>
                    <option value="12" @selected(($perPage ?? 12) == 12)>12</option>
                    <option value="18" @selected(($perPage ?? 12) == 18)>18</option>
                    <option value="24" @selected(($perPage ?? 12) == 24)>24</option>
                </select>
            </div>
            <div class="flex items-end justify-end">
                <button type="submit" class="w-auto min-w-[150px] px-5 py-2 rounded-lg bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-700">Apply Filters</button>
            </div>
        </div>
    </form>

    @if($visits->count() > 0)
        <div class="mb-4 sm:mb-6 bg-white rounded-xl border border-gray-200 p-3 sm:p-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
            <p class="text-xs sm:text-sm text-gray-600">
                Page <span class="font-medium text-gray-900">{{ $visits->currentPage() }}</span> / <span class="font-medium text-gray-900">{{ $visits->lastPage() }}</span>
                · Showing <span class="font-medium text-gray-900">{{ $visits->firstItem() }}</span> to <span class="font-medium text-gray-900">{{ $visits->lastItem() }}</span> of <span class="font-medium text-gray-900">{{ $visits->total() }}</span>
            </p>
            <div>{{ $visits->withQueryString()->onEachSide(1)->links() }}</div>
        </div>
    @endif

    <div class="space-y-4">
        @forelse($visits as $visit)
            @php
                $clientName = $visit->subscription?->client?->name ?? 'N/A';
                $areaName = $visit->area?->name ?? 'N/A';
                $assignedName = $visit->technician?->name ?? 'Unassigned';
            @endphp
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 sm:p-5">
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                    <div class="lg:col-span-2">
                        <div class="flex flex-wrap items-center gap-2 mb-2">
                            <span class="text-xs font-medium px-2 py-1 rounded-md bg-gray-100 text-gray-700">#{{ $visit->id }}</span>
                            <span class="text-xs font-medium px-2 py-1 rounded-md bg-indigo-50 text-indigo-700">{{ $visit->status }}</span>
                            @if($visit->technician_id === null)
                                <span class="text-xs font-medium px-2 py-1 rounded-md bg-red-50 text-red-700">Unassigned</span>
                            @endif
                        </div>
                        <h3 class="text-sm sm:text-base font-medium text-gray-900">{{ $clientName }}</h3>
                        <div class="mt-2 grid grid-cols-1 sm:grid-cols-2 gap-2 text-xs sm:text-sm">
                            <p class="text-gray-600"><span class="font-medium text-gray-800">Area:</span> {{ $areaName }}</p>
                            <p class="text-gray-600"><span class="font-medium text-gray-800">Scheduled:</span> {{ $visit->scheduled_date?->format('Y-m-d') ?? '—' }}</p>
                            <p class="text-gray-600"><span class="font-medium text-gray-800">Current Tech:</span> {{ $assignedName }}</p>
                            <p class="text-gray-600"><span class="font-medium text-gray-800">Price:</span> {{ $visit->price !== null ? 'AED '.number_format((float) $visit->price, 2) : '—' }}</p>
                        </div>
                    </div>
                    <div class="lg:border-l lg:border-gray-100 lg:pl-4">
                        <form method="post" action="{{ route('hr.visit-assignments.assign', $visit->id) }}" class="space-y-2">
                            @csrf
                            <div>
                                <label class="block text-xs font-medium text-gray-500 mb-1">Assign Technician</label>
                                <select name="technician_id" class="w-full rounded-lg border-gray-300 text-sm" required>
                                    <option value="">Select technician</option>
                                    @foreach($technicians as $t)
                                        <option value="{{ $t->id }}">{{ $t->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-500 mb-1">Scheduled Date</label>
                                <input type="date" name="scheduled_date" value="{{ $visit->scheduled_date?->format('Y-m-d') }}" class="w-full rounded-lg border-gray-300 text-sm" />
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-500 mb-1">Note</label>
                                <input type="text" name="note" placeholder="Optional note" class="w-full rounded-lg border-gray-300 text-sm" maxlength="1000" />
                            </div>
                            <button type="submit" class="w-full px-3 py-2 rounded-lg bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-700">Assign Now</button>
                        </form>
                    </div>
                </div>
            </div>
        @empty
            <div class="bg-white rounded-xl border border-gray-200 p-10 text-center text-xs sm:text-sm text-gray-500">
                No visits found for current filter.
            </div>
        @endforelse
    </div>

    @if($visits->count() > 0)
        <div class="mt-4 sm:mt-6 bg-white rounded-xl border border-gray-200 p-3 sm:p-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
            <p class="text-xs sm:text-sm text-gray-600">
                Page <span class="font-medium text-gray-900">{{ $visits->currentPage() }}</span> / <span class="font-medium text-gray-900">{{ $visits->lastPage() }}</span>
            </p>
            <div>{{ $visits->withQueryString()->onEachSide(1)->links() }}</div>
        </div>
    @endif
</x-hr-layout>
