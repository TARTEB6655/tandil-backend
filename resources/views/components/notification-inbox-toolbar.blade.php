@props([
    'routeName',
    'showAudienceFilter' => false,
])
@php
    use App\Support\NotificationInboxWebFilters;
    use App\Support\UserNotificationAudience;
    $activeFilter = request('filter', 'all');
    if (! in_array($activeFilter, ['all', 'unread', 'read'], true)) {
        $activeFilter = 'all';
    }
    $activeKind = request('kind', NotificationInboxWebFilters::KIND_ALL) ?: NotificationInboxWebFilters::KIND_ALL;
    if (! in_array($activeKind, NotificationInboxWebFilters::allowedKinds(), true)) {
        $activeKind = NotificationInboxWebFilters::KIND_ALL;
    }
    $activeAudience = (string) (request('audience_role', '') ?? '');
    $formId = 'notification-inbox-filters-' . str_replace(['.', '\\'], '-', $routeName);
    $selectClass = 'mt-1.5 block w-full rounded-lg border border-slate-300 bg-white py-2 pl-3 pr-9 text-sm text-slate-900 shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 dark:border-slate-600 dark:bg-gray-900 dark:text-slate-100';
    $labelClass = 'block text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400';
@endphp
<div class="mb-6 rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-gray-800 dark:shadow-none">
    <form id="{{ $formId }}" method="GET" action="{{ route($routeName) }}" class="px-4 py-4 sm:px-5 sm:py-4">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between sm:gap-4 border-b border-slate-100 pb-3 dark:border-slate-700/80">
            <div>
                <h2 class="text-sm font-semibold text-slate-900 dark:text-slate-100">Filters</h2>
                <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">Set options below, then apply. URL updates so you can bookmark or share.</p>
            </div>
            <a href="{{ route($routeName) }}"
               class="inline-flex shrink-0 items-center justify-center rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-100 dark:border-slate-600 dark:bg-slate-900/60 dark:text-slate-200 dark:hover:bg-slate-800">
                Reset all
            </a>
        </div>

        <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-12 lg:items-end">
            <div class="sm:col-span-1 lg:col-span-2">
                <label for="{{ $formId }}-status" class="{{ $labelClass }}">Status</label>
                <select id="{{ $formId }}-status" name="filter" class="{{ $selectClass }}">
                    <option value="all" @selected($activeFilter === 'all')>All</option>
                    <option value="unread" @selected($activeFilter === 'unread')>Unread only</option>
                    <option value="read" @selected($activeFilter === 'read')>Read only</option>
                </select>
            </div>

            <div class="sm:col-span-1 lg:col-span-2">
                <label for="{{ $formId }}-kind" class="{{ $labelClass }}">Type</label>
                <select id="{{ $formId }}-kind" name="kind" class="{{ $selectClass }}">
                    @foreach(NotificationInboxWebFilters::kindOptions() as $kindKey => $label)
                        <option value="{{ $kindKey === NotificationInboxWebFilters::KIND_ALL ? '' : $kindKey }}" @selected($activeKind === $kindKey)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            @if($showAudienceFilter)
                <div class="sm:col-span-2 lg:col-span-3">
                    <label for="{{ $formId }}-audience" class="{{ $labelClass }}">Audience <span class="font-normal normal-case text-slate-400">(admin review)</span></label>
                    <select id="{{ $formId }}-audience" name="audience_role" class="{{ $selectClass }}">
                        <option value="" @selected($activeAudience === '')>All roles</option>
                        @foreach(UserNotificationAudience::PRIORITY_ROLES as $roleKey)
                            @php $lbl = UserNotificationAudience::labels()[$roleKey] ?? $roleKey; @endphp
                            <option value="{{ $roleKey }}" @selected($activeAudience === $roleKey)>{{ $lbl }}</option>
                        @endforeach
                    </select>
                </div>
            @endif

            <div class="sm:col-span-2 {{ $showAudienceFilter ? 'lg:col-span-3' : 'lg:col-span-6' }}">
                <label for="{{ $formId }}-q" class="{{ $labelClass }}">Search in message</label>
                <input id="{{ $formId }}-q" type="search" name="q" value="{{ request('q') }}" placeholder="Keywords…" autocomplete="off"
                       class="mt-1.5 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 placeholder:text-slate-400 shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 dark:border-slate-600 dark:bg-gray-900 dark:text-slate-100 dark:placeholder:text-slate-500" />
            </div>

            <div class="flex flex-col gap-2 sm:col-span-2 lg:col-span-2 lg:flex-row lg:items-end">
                <button type="submit"
                        class="inline-flex w-full items-center justify-center rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 lg:flex-1">
                    Apply filters
                </button>
            </div>
        </div>
    </form>
</div>
