@props([
    'routeName',
    'showAudienceFilter' => false,
])
@php
    use App\Support\NotificationInboxWebFilters;
    use App\Support\UserNotificationAudience;
    $activeFilter = request('filter', 'all');
    $activeKind = request('kind', NotificationInboxWebFilters::KIND_ALL) ?: NotificationInboxWebFilters::KIND_ALL;
    $activeAudience = request('audience_role', '') ?? '';
    $base = array_filter([
        'q' => request('q'),
    ], fn ($v) => $v !== null && $v !== '');
    $link = function (array $overrides) use ($routeName, $base) {
        return route($routeName, array_merge($base, array_filter($overrides, fn ($v) => $v !== null && $v !== '')));
    };
    $pillInactive = 'border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-slate-600 dark:text-slate-300 hover:border-indigo-200 dark:hover:border-indigo-600 hover:bg-indigo-50/60 dark:hover:bg-indigo-950/30';
    $pillActive = 'border border-indigo-600 bg-indigo-600 text-white shadow-sm';
    $segInactive = 'px-3 py-2 text-sm font-medium rounded-md text-slate-600 dark:text-slate-400 hover:text-indigo-700 dark:hover:text-indigo-300 transition-colors';
    $segActive = 'px-3 py-2 text-sm font-semibold rounded-md bg-white dark:bg-gray-800 text-indigo-700 dark:text-indigo-300 shadow-sm ring-1 ring-slate-200/80 dark:ring-slate-600';
@endphp
<div class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800 shadow-sm overflow-hidden mb-6">
    <div class="px-4 py-4 sm:px-5 sm:py-5 space-y-5">
        {{-- Status + search --}}
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div class="min-w-0">
                <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-2">Status</p>
                <div class="inline-flex rounded-lg bg-slate-100 dark:bg-slate-900/70 p-1 gap-0.5">
                    <a href="{{ $link(['filter' => 'all', 'kind' => $activeKind !== NotificationInboxWebFilters::KIND_ALL ? $activeKind : null, 'audience_role' => $activeAudience ?: null]) }}"
                       class="{{ $activeFilter === 'all' ? $segActive : $segInactive }}">All</a>
                    <a href="{{ $link(['filter' => 'unread', 'kind' => $activeKind !== NotificationInboxWebFilters::KIND_ALL ? $activeKind : null, 'audience_role' => $activeAudience ?: null]) }}"
                       class="{{ $activeFilter === 'unread' ? $segActive : $segInactive }}">Unread</a>
                    <a href="{{ $link(['filter' => 'read', 'kind' => $activeKind !== NotificationInboxWebFilters::KIND_ALL ? $activeKind : null, 'audience_role' => $activeAudience ?: null]) }}"
                       class="{{ $activeFilter === 'read' ? $segActive : $segInactive }}">Read</a>
                </div>
            </div>
            <form method="GET" action="{{ route($routeName) }}" class="flex flex-col sm:flex-row sm:items-end gap-2 w-full lg:max-w-md lg:flex-1 lg:justify-end">
                <input type="hidden" name="filter" value="{{ $activeFilter }}" />
                @if($activeKind !== NotificationInboxWebFilters::KIND_ALL)
                    <input type="hidden" name="kind" value="{{ $activeKind }}" />
                @endif
                @if($showAudienceFilter && $activeAudience !== '')
                    <input type="hidden" name="audience_role" value="{{ $activeAudience }}" />
                @endif
                <div class="flex-1 min-w-0 w-full">
                    <label class="block text-[11px] font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-2">Search</label>
                    <div class="flex gap-2">
                        <input type="text" name="q" value="{{ request('q') }}" placeholder="Search notifications…"
                               class="min-w-0 flex-1 rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-gray-900 px-3 py-2 text-sm text-slate-900 dark:text-slate-100 placeholder:text-slate-400 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20" />
                        <button type="submit" class="inline-flex shrink-0 items-center justify-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition-colors">
                            Search
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <div class="h-px bg-slate-100 dark:bg-slate-700/80" aria-hidden="true"></div>

        {{-- Type + audience in a tighter grid --}}
        <div class="grid gap-5 {{ $showAudienceFilter ? 'lg:grid-cols-2 lg:gap-8' : '' }}">
            <div>
                <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-2">Type</p>
                <div class="flex flex-wrap gap-2">
                    @foreach(NotificationInboxWebFilters::kindOptions() as $kindKey => $label)
                        <a href="{{ $link([
                            'kind' => $kindKey === NotificationInboxWebFilters::KIND_ALL ? null : $kindKey,
                            'filter' => $activeFilter,
                            'audience_role' => $activeAudience ?: null,
                        ]) }}"
                           class="inline-flex items-center px-3 py-1.5 text-xs font-medium rounded-full transition-colors {{ $activeKind === $kindKey ? $pillActive : $pillInactive }}">
                            {{ $label }}
                        </a>
                    @endforeach
                </div>
            </div>
            @if($showAudienceFilter)
                <div class="lg:border-l lg:border-slate-100 dark:lg:border-slate-700 lg:pl-8">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-1">Audience</p>
                    <p class="text-xs text-slate-400 dark:text-slate-500 mb-2">Filter by intended recipient role (admin review).</p>
                    <div class="flex flex-wrap gap-2">
                        <a href="{{ $link(['audience_role' => null, 'filter' => $activeFilter, 'kind' => $activeKind !== NotificationInboxWebFilters::KIND_ALL ? $activeKind : null]) }}"
                           class="inline-flex items-center px-3 py-1.5 text-xs font-medium rounded-full transition-colors {{ $activeAudience === '' ? $pillActive : $pillInactive }}">All roles</a>
                        @foreach(UserNotificationAudience::PRIORITY_ROLES as $roleKey)
                            @php $lbl = UserNotificationAudience::labels()[$roleKey] ?? $roleKey; @endphp
                            <a href="{{ $link(['audience_role' => $roleKey, 'filter' => $activeFilter, 'kind' => $activeKind !== NotificationInboxWebFilters::KIND_ALL ? $activeKind : null]) }}"
                               class="inline-flex items-center px-3 py-1.5 text-xs font-medium rounded-full transition-colors {{ $activeAudience === $roleKey ? $pillActive : $pillInactive }}">{{ $lbl }}</a>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
