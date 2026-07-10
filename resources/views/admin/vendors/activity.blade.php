<x-admin-layout>
    <x-admin.vendor.shell>
        <x-admin.vendor.nav :vendor="$vendor" />

        <x-admin.vendor.page-header
            title="Activity timeline"
            :description="$vendor->profile?->business_name.' — audit trail of key account events.'" />

        <x-admin.vendor.card :padding="false">
            <ol class="relative space-y-0 p-6">
                @forelse($timeline as $event)
                    <li class="relative border-l border-gray-200 pb-8 pl-8 last:pb-0 dark:border-gray-700">
                        <span class="absolute -left-2 top-0 flex h-4 w-4 items-center justify-center rounded-full bg-indigo-600 ring-4 ring-white dark:ring-gray-900"></span>
                        <div class="rounded-lg border border-gray-100 bg-gray-50/50 p-4 dark:border-gray-800 dark:bg-gray-900/50">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $event['title'] }}</h3>
                                <time class="text-xs text-gray-500">{{ $event['at_formatted'] }}</time>
                            </div>
                            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">{{ $event['description'] }}</p>
                            @if($event['actor'])<p class="mt-2 text-xs text-gray-500">By {{ $event['actor'] }}</p>@endif
                        </div>
                    </li>
                @empty
                    <x-admin.vendor.empty-state title="No activity yet" description="Account events will appear here as they occur." />
                @endforelse
            </ol>
        </x-admin.vendor.card>
    </x-admin.vendor.shell>
</x-admin-layout>
