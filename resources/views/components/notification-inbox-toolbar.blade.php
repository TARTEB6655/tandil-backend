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
@endphp
<div class="mb-4 space-y-3">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div class="inline-flex flex-wrap rounded-lg border border-gray-200 bg-white p-1 gap-0.5">
            <a href="{{ $link(['filter' => 'all', 'kind' => $activeKind !== NotificationInboxWebFilters::KIND_ALL ? $activeKind : null, 'audience_role' => $activeAudience ?: null]) }}"
               class="px-3 py-1.5 text-xs sm:text-sm rounded-md {{ $activeFilter === 'all' ? 'bg-gray-900 text-white' : 'text-gray-600 hover:bg-gray-100' }}">All</a>
            <a href="{{ $link(['filter' => 'unread', 'kind' => $activeKind !== NotificationInboxWebFilters::KIND_ALL ? $activeKind : null, 'audience_role' => $activeAudience ?: null]) }}"
               class="px-3 py-1.5 text-xs sm:text-sm rounded-md {{ $activeFilter === 'unread' ? 'bg-gray-900 text-white' : 'text-gray-600 hover:bg-gray-100' }}">Unread</a>
            <a href="{{ $link(['filter' => 'read', 'kind' => $activeKind !== NotificationInboxWebFilters::KIND_ALL ? $activeKind : null, 'audience_role' => $activeAudience ?: null]) }}"
               class="px-3 py-1.5 text-xs sm:text-sm rounded-md {{ $activeFilter === 'read' ? 'bg-gray-900 text-white' : 'text-gray-600 hover:bg-gray-100' }}">Read</a>
        </div>
        <form method="GET" action="{{ route($routeName) }}" class="flex flex-wrap items-center gap-2">
            <input type="hidden" name="filter" value="{{ $activeFilter }}" />
            @if($activeKind !== NotificationInboxWebFilters::KIND_ALL)
                <input type="hidden" name="kind" value="{{ $activeKind }}" />
            @endif
            @if($showAudienceFilter && $activeAudience !== '')
                <input type="hidden" name="audience_role" value="{{ $activeAudience }}" />
            @endif
            <input type="text" name="q" value="{{ request('q') }}" placeholder="Search notifications" class="w-56 rounded-lg border border-gray-300 px-3 py-1.5 text-sm focus:border-gray-500 focus:outline-none dark:bg-gray-800 dark:border-gray-600 dark:text-gray-100" />
            <button type="submit" class="rounded-lg bg-gray-900 px-3 py-1.5 text-xs text-white">Search</button>
        </form>
    </div>
    <div>
        <p class="text-xs font-medium text-gray-500 mb-2">Type</p>
        <div class="inline-flex flex-wrap gap-1.5">
            @foreach(NotificationInboxWebFilters::kindOptions() as $kindKey => $label)
                <a href="{{ $link([
                    'kind' => $kindKey === NotificationInboxWebFilters::KIND_ALL ? null : $kindKey,
                    'filter' => $activeFilter,
                    'audience_role' => $activeAudience ?: null,
                ]) }}"
                   class="px-2.5 py-1 text-xs rounded-full border {{ $activeKind === $kindKey ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-gray-700 border-gray-200 hover:border-gray-300' }}">
                    {{ $label }}
                </a>
            @endforeach
        </div>
    </div>
    @if($showAudienceFilter)
        <div>
            <p class="text-xs font-medium text-gray-500 mb-2">Audience (for review)</p>
            <div class="inline-flex flex-wrap gap-1.5">
                <a href="{{ $link(['audience_role' => null, 'filter' => $activeFilter, 'kind' => $activeKind !== NotificationInboxWebFilters::KIND_ALL ? $activeKind : null]) }}"
                   class="px-2.5 py-1 text-xs rounded-full border {{ $activeAudience === '' ? 'bg-gray-900 text-white border-gray-900' : 'bg-white text-gray-700 border-gray-200' }}">All</a>
                @foreach(UserNotificationAudience::PRIORITY_ROLES as $roleKey)
                    @php $lbl = UserNotificationAudience::labels()[$roleKey] ?? $roleKey; @endphp
                    <a href="{{ $link(['audience_role' => $roleKey, 'filter' => $activeFilter, 'kind' => $activeKind !== NotificationInboxWebFilters::KIND_ALL ? $activeKind : null]) }}"
                       class="px-2.5 py-1 text-xs rounded-full border {{ $activeAudience === $roleKey ? 'bg-gray-900 text-white border-gray-900' : 'bg-white text-gray-700 border-gray-200 hover:border-gray-300' }}">{{ $lbl }}</a>
                @endforeach
            </div>
        </div>
    @endif
</div>
