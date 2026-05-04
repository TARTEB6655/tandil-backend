<x-admin-layout>
    <div class="space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <nav class="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400 mb-1">
                    <a href="{{ route('admin.dashboard') }}" class="hover:text-gray-700 dark:hover:text-gray-300">Dashboard</a>
                    <span>/</span>
                    <a href="{{ route('admin.notifications.index') }}" class="hover:text-gray-700 dark:hover:text-gray-300">Notifications</a>
                    <span>/</span>
                    <span class="text-gray-900 dark:text-gray-100 font-medium">Broadcast log</span>
                </nav>
                <h1 class="text-xl font-semibold text-gray-900 dark:text-gray-100">Notification broadcasts</h1>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Delivery history and how many users received each broadcast by role.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('admin.notifications.delivery-stats') }}" class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-800">Delivery analytics</a>
                <a href="{{ route('admin.notifications.create') }}" class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700">Send notification</a>
                <a href="{{ route('admin.notifications.index') }}" class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-800">Inbox</a>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-900/50">
                        <tr>
                            <th class="px-4 py-3 text-left font-medium text-gray-700 dark:text-gray-300">When</th>
                            <th class="px-4 py-3 text-left font-medium text-gray-700 dark:text-gray-300">Title</th>
                            <th class="px-4 py-3 text-left font-medium text-gray-700 dark:text-gray-300">Scope</th>
                            <th class="px-4 py-3 text-right font-medium text-gray-700 dark:text-gray-300">Total</th>
                            <th class="px-4 py-3 text-right font-medium text-gray-700 dark:text-gray-300"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @forelse($items as $row)
                            @php $c = $row->recipientCountsForApi(); @endphp
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-900/30">
                                <td class="px-4 py-3 text-gray-600 dark:text-gray-400 whitespace-nowrap">{{ $row->created_at?->format('M j, Y g:i A') }}</td>
                                <td class="px-4 py-3 text-gray-900 dark:text-gray-100 font-medium max-w-xs truncate">{{ $row->title }}</td>
                                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">
                                    <span class="capitalize">{{ $row->scope_type }}</span>
                                    @if($row->scope_role)
                                        <span class="text-gray-400">·</span> {{ $row->scope_role }}
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right font-medium text-gray-900 dark:text-gray-100">{{ $row->total_recipients }}</td>
                                <td class="px-4 py-3 text-right">
                                    <a href="{{ route('admin.notifications.broadcasts.show', $row) }}" class="text-indigo-600 hover:text-indigo-800 text-sm font-medium">Details</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-12 text-center text-gray-500 dark:text-gray-400">No broadcasts yet. Send one from Notifications → Send notification.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($items->hasPages())
                <div class="px-4 py-3 border-t border-gray-200 dark:border-gray-700">{{ $items->links() }}</div>
            @endif
        </div>
    </div>
</x-admin-layout>
