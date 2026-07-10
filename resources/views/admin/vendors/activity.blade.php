<x-admin-layout>
    <div class="space-y-6">
        <x-admin.vendor.nav :vendor="$vendor" />
        <div>
            <h1 class="text-2xl font-semibold">Activity Timeline</h1>
            <p class="text-sm text-gray-500">{{ $vendor->profile?->business_name }} — audit trail of key events.</p>
        </div>

        <div class="rounded-2xl border bg-white/80 p-6 backdrop-blur dark:bg-gray-900/70">
            <ol class="relative space-y-6 border-l border-gray-200 pl-6 dark:border-gray-700">
                @forelse($timeline as $event)
                    <li class="relative">
                        <span class="absolute -left-[1.6rem] flex h-8 w-8 items-center justify-center rounded-full bg-indigo-100 text-xs font-bold text-indigo-700 ring-4 ring-white dark:ring-gray-900">{{ strtoupper(substr($event['type'], 0, 1)) }}</span>
                        <div class="rounded-xl border border-gray-100 bg-gray-50/80 p-4 dark:border-gray-800 dark:bg-gray-900/50">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <h3 class="font-semibold text-gray-900 dark:text-gray-100">{{ $event['title'] }}</h3>
                                <time class="text-xs text-gray-500">{{ $event['at_formatted'] }}</time>
                            </div>
                            <p class="mt-1 text-sm text-gray-600">{{ $event['description'] }}</p>
                            @if($event['actor'])<p class="mt-1 text-xs text-gray-500">By {{ $event['actor'] }}</p>@endif
                        </div>
                    </li>
                @empty
                    <li class="text-sm text-gray-500">No activity recorded yet.</li>
                @endforelse
            </ol>
        </div>
    </div>
</x-admin-layout>
