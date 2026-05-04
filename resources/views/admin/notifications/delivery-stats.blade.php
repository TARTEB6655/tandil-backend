<x-admin-layout>
    @php
        $labeled = $stats['by_audience_labeled'] ?? [];
        $byType = $stats['by_notification_type'] ?? [];
    @endphp
    <div class="space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <nav class="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400 mb-1">
                    <a href="{{ route('admin.dashboard') }}" class="hover:text-gray-700 dark:hover:text-gray-300">Dashboard</a>
                    <span>/</span>
                    <a href="{{ route('admin.notifications.index') }}" class="hover:text-gray-700 dark:hover:text-gray-300">Notifications</a>
                    <span>/</span>
                    <span class="text-gray-900 dark:text-gray-100 font-medium">Delivery analytics</span>
                </nav>
                <h1 class="text-xl font-semibold text-gray-900 dark:text-gray-100">Notification delivery analytics</h1>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Counts from all stored notifications (same source as API <code class="text-xs bg-gray-100 dark:bg-gray-800 px-1 rounded">GET /api/admin/notifications/delivery-stats</code>).</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('admin.notifications.index') }}" class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-800">Inbox</a>
                <a href="{{ route('admin.notifications.broadcasts.index') }}" class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-800">Broadcast log</a>
            </div>
        </div>

        <form method="GET" action="{{ route('admin.notifications.delivery-stats') }}" class="flex flex-wrap items-end gap-3 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-4">
            <div>
                <label for="since" class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Since</label>
                <input type="date" name="since" id="since" value="{{ request('since') }}"
                       class="rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 text-sm" />
            </div>
            <div>
                <label for="until" class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Until</label>
                <input type="date" name="until" id="until" value="{{ request('until') }}"
                       class="rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 text-sm" />
            </div>
            <button type="submit" class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700">Apply</button>
            <a href="{{ route('admin.notifications.delivery-stats') }}" class="text-sm text-indigo-600 hover:text-indigo-800 dark:text-indigo-400">Reset</a>
        </form>

        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3">
            <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-4">
                <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">Grand total</p>
                <p class="text-2xl font-bold text-gray-900 dark:text-gray-100 tabular-nums">{{ number_format($stats['grand_total'] ?? 0) }}</p>
            </div>
            @foreach(['customers' => 'Customers', 'technicians' => 'Technicians', 'supervisors' => 'Supervisors', 'area_managers' => 'Area managers', 'hr' => 'HR', 'admins' => 'Admins'] as $key => $label)
                <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-4">
                    <p class="text-xs font-semibold text-gray-500 dark:text-gray-400">{{ $label }}</p>
                    <p class="text-xl font-bold text-indigo-700 dark:text-indigo-300 tabular-nums">{{ number_format($labeled[$key] ?? 0) }}</p>
                </div>
            @endforeach
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden">
            <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700">
                <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">By notification type</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-900/50">
                        <tr>
                            <th class="px-4 py-3 text-left font-medium text-gray-700 dark:text-gray-300">Type</th>
                            <th class="px-4 py-3 text-right font-medium text-gray-700 dark:text-gray-300">Deliveries</th>
                            <th class="px-4 py-3 text-left font-medium text-gray-700 dark:text-gray-300">By audience (sample)</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @forelse($byType as $row)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-900/30">
                                <td class="px-4 py-3 text-gray-900 dark:text-gray-100 font-mono text-xs">{{ $row['notification_type_short'] ?? class_basename($row['notification_type'] ?? '') }}</td>
                                <td class="px-4 py-3 text-right font-medium tabular-nums">{{ number_format($row['total_deliveries'] ?? 0) }}</td>
                                <td class="px-4 py-3 text-gray-600 dark:text-gray-400 text-xs">
                                    @php $ba = $row['by_audience'] ?? []; @endphp
                                    @foreach(array_slice($ba, 0, 8, true) as $aud => $cnt)
                                        @if($cnt > 0)
                                            <span class="inline-block mr-2">{{ $aud }}: {{ $cnt }}</span>
                                        @endif
                                    @endforeach
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="px-4 py-8 text-center text-gray-500">No notification rows in range.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-admin-layout>
