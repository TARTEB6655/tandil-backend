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
    $controlH = 'h-11 min-h-[2.75rem]';
    $fieldBase = "{$controlH} box-border w-full rounded-xl border border-slate-200 bg-white text-sm font-medium leading-normal text-slate-800 shadow-sm transition-colors placeholder:text-slate-400 focus:border-indigo-400 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100 dark:placeholder:text-slate-500 dark:focus:border-indigo-500";
    $selectBase = "{$fieldBase} appearance-none cursor-pointer bg-[length:1rem_1rem] bg-[right_0.75rem_center] bg-no-repeat pr-10";
    $selectChevron = "url('data:image/svg+xml,%3Csvg xmlns=%27http://www.w3.org/2000/svg%27 fill=%27none%27 viewBox=%270 0 24 24%27 stroke=%27%2364748b%27%3E%3Cpath stroke-linecap=%27round%27 stroke-linejoin=%27round%27 stroke-width=%272%27 d=%27M19 9l-7 7-7-7%27/%3E%3C/svg%3E')";
@endphp
<div class="mb-6">
    <div class="mb-3 flex min-h-[1.25rem] flex-wrap items-center justify-between gap-2">
        <h2 class="text-xs font-semibold uppercase tracking-wider text-slate-600 dark:text-slate-300">Filters</h2>
        <a href="{{ route($routeName) }}"
           class="text-xs font-semibold text-slate-600 underline-offset-2 hover:text-indigo-600 hover:underline dark:text-slate-400 dark:hover:text-indigo-400">
            Reset all
        </a>
    </div>

    <div class="rounded-xl border border-slate-200 bg-white p-3 shadow-sm ring-1 ring-slate-900/5 dark:border-slate-600 dark:bg-slate-900 dark:ring-white/10 sm:p-4">
        <form id="{{ $formId }}" method="GET" action="{{ route($routeName) }}" class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center">
            {{-- Search (widest) --}}
            <div class="relative min-w-0 flex-1 sm:min-w-[12rem] lg:min-w-[14rem]">
                <label for="{{ $formId }}-q" class="sr-only">Search notifications</label>
                <span class="pointer-events-none absolute inset-y-0 left-0 z-10 flex w-10 items-center justify-center text-slate-400 dark:text-slate-500" aria-hidden="true">
                    <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                </span>
                <input id="{{ $formId }}-q" type="search" name="q" value="{{ request('q') }}" placeholder="Search…" autocomplete="off"
                       class="{{ $fieldBase }} w-full pl-10 pr-3" />
            </div>

            {{-- Status --}}
            <div class="relative min-w-0 shrink-0 sm:w-[10.5rem] lg:w-[11rem]">
                <span class="pointer-events-none absolute inset-y-0 left-0 z-10 flex w-10 items-center justify-center text-slate-400 dark:text-slate-500" aria-hidden="true">
                    <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 4h13M3 8h9m-9 4h6m4 0l4-4m0 0l4 4m-4-4v12" />
                    </svg>
                </span>
                <label for="{{ $formId }}-status" class="sr-only">Read status</label>
                <select id="{{ $formId }}-status" name="filter" title="Read status"
                        class="{{ $selectBase }} pl-10"
                        style="background-image: {!! $selectChevron !!};"
                        onchange="this.form.submit()">
                    <option value="all" @selected($activeFilter === 'all')>All</option>
                    <option value="unread" @selected($activeFilter === 'unread')>Unread</option>
                    <option value="read" @selected($activeFilter === 'read')>Read</option>
                </select>
            </div>

            {{-- Type --}}
            <div class="relative min-w-0 shrink-0 sm:w-[11.5rem] lg:w-[12rem]">
                <span class="pointer-events-none absolute inset-y-0 left-0 z-10 flex w-10 items-center justify-center text-slate-400 dark:text-slate-500" aria-hidden="true">
                    <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
                    </svg>
                </span>
                <label for="{{ $formId }}-kind" class="sr-only">Notification type</label>
                <select id="{{ $formId }}-kind" name="kind" title="Notification type"
                        class="{{ $selectBase }} pl-10"
                        style="background-image: {!! $selectChevron !!};"
                        onchange="this.form.submit()">
                    @foreach(NotificationInboxWebFilters::kindOptions() as $kindKey => $label)
                        <option value="{{ $kindKey === NotificationInboxWebFilters::KIND_ALL ? '' : $kindKey }}" @selected($activeKind === $kindKey)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            @if($showAudienceFilter)
                <div class="relative min-w-0 shrink-0 sm:w-[12rem] lg:w-[13rem]">
                    <span class="pointer-events-none absolute inset-y-0 left-0 z-10 flex w-10 items-center justify-center text-slate-400 dark:text-slate-500" aria-hidden="true">
                        <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                        </svg>
                    </span>
                    <label for="{{ $formId }}-audience" class="sr-only">Audience role</label>
                    <select id="{{ $formId }}-audience" name="audience_role" title="Audience (admin)"
                            class="{{ $selectBase }} pl-10"
                            style="background-image: {!! $selectChevron !!};"
                            onchange="this.form.submit()">
                        <option value="" @selected($activeAudience === '')>All roles</option>
                        @foreach(UserNotificationAudience::PRIORITY_ROLES as $roleKey)
                            @php $lbl = UserNotificationAudience::labels()[$roleKey] ?? $roleKey; @endphp
                            <option value="{{ $roleKey }}" @selected($activeAudience === $roleKey)>{{ $lbl }}</option>
                        @endforeach
                    </select>
                </div>
            @endif

            <button type="submit"
                    class="{{ $controlH }} inline-flex shrink-0 items-center justify-center rounded-xl border border-indigo-700/20 !bg-indigo-600 px-5 text-sm font-semibold !text-white shadow-sm transition-colors hover:!bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 focus:ring-offset-white dark:border-indigo-400/30 dark:!bg-indigo-500 dark:hover:!bg-indigo-400 dark:focus:ring-indigo-400 dark:focus:ring-offset-slate-900 sm:min-w-[9.5rem]">
                Apply filters
            </button>
        </form>
    </div>
</div>
