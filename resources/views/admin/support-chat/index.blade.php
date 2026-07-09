<x-admin-layout>
    <div class="space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-xl font-semibold text-gray-900 dark:text-gray-100">Vendor Live Chat</h1>
                <p class="text-sm text-gray-500 mt-1">Real-time support conversations with marketplace vendors.</p>
            </div>
            <a href="{{ route('admin.vendors.index') }}" class="text-sm text-indigo-600 hover:underline">Vendor management →</a>
        </div>
        <x-admin.marketplace-nav />

        @if(session('success'))
            <div class="rounded-lg bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">{{ session('success') }}</div>
        @endif

        <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
            @foreach([
                ['Open', $stats['open'], 'open'],
                ['In progress', $stats['in_progress'], 'in_progress'],
                ['Resolved', $stats['resolved'], 'resolved'],
                ['Active vendor chats', $stats['vendor_active'], null],
            ] as [$label, $val, $filter])
                <div class="bg-white dark:bg-gray-800 rounded-xl border p-4">
                    <p class="text-xs text-gray-500 uppercase">{{ $label }}</p>
                    <p class="text-2xl font-semibold mt-1">
                        @if($filter)
                            <a href="{{ route('admin.support-chat.index', array_merge(request()->except('page'), ['status' => $filter, 'user_role' => 'vendor'])) }}" class="hover:text-indigo-600">{{ $val }}</a>
                        @else
                            {{ $val }}
                        @endif
                    </p>
                </div>
            @endforeach
        </div>

        <form method="GET" class="flex flex-wrap gap-2 bg-white dark:bg-gray-800 rounded-xl border p-4">
            <input name="search" value="{{ request('search') }}" placeholder="Search vendor name, email, token…"
                   class="rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 text-sm min-w-[200px] flex-1" />
            <select name="status" class="rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 text-sm">
                <option value="">All statuses</option>
                @foreach(['open', 'in_progress', 'resolved', 'closed'] as $status)
                    <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst(str_replace('_', ' ', $status)) }}</option>
                @endforeach
            </select>
            <input type="hidden" name="user_role" value="{{ request('user_role', 'vendor') }}" />
            <button type="submit" class="px-4 py-2 bg-gray-900 dark:bg-indigo-600 text-white text-sm rounded-lg">Filter</button>
            <a href="{{ route('admin.support-chat.index') }}" class="px-4 py-2 border text-sm rounded-lg text-gray-600 dark:text-gray-300">Reset</a>
        </form>

        <div class="bg-white dark:bg-gray-800 rounded-xl border overflow-hidden">
            <table class="min-w-full text-sm divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-700/50">
                    <tr>
                        <th class="px-4 py-3 text-left">Vendor</th>
                        <th class="px-4 py-3 text-left">Subject</th>
                        <th class="px-4 py-3 text-left">Status</th>
                        <th class="px-4 py-3 text-left">Messages</th>
                        <th class="px-4 py-3 text-left">Last activity</th>
                        <th class="px-4 py-3 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($sessions as $session)
                        @php
                            $statusColors = [
                                'open' => 'bg-yellow-100 text-yellow-800',
                                'in_progress' => 'bg-blue-100 text-blue-800',
                                'resolved' => 'bg-green-100 text-green-800',
                                'closed' => 'bg-gray-100 text-gray-700',
                            ];
                        @endphp
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/30">
                            <td class="px-4 py-3">
                                <p class="font-medium text-gray-900 dark:text-gray-100">{{ $session->user?->name ?? 'Unknown' }}</p>
                                <p class="text-xs text-gray-500">{{ $session->user?->email }}</p>
                            </td>
                            <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $session->subject }}</td>
                            <td class="px-4 py-3">
                                <span class="px-2 py-0.5 rounded text-xs font-medium {{ $statusColors[$session->status] ?? 'bg-gray-100 text-gray-700' }}">
                                    {{ ucfirst(str_replace('_', ' ', $session->status)) }}
                                </span>
                            </td>
                            <td class="px-4 py-3">{{ $session->messages_count }}</td>
                            <td class="px-4 py-3 text-gray-500">{{ $session->updated_at?->diffForHumans() }}</td>
                            <td class="px-4 py-3 text-right space-x-2">
                                @if($session->status === 'open')
                                    <form method="POST" action="{{ route('admin.support-chat.accept', $session) }}" class="inline">
                                        @csrf
                                        <button type="submit" class="text-green-600 hover:underline font-medium text-sm">Accept</button>
                                    </form>
                                @endif
                                <a href="{{ route('admin.support-chat.show', $session) }}" class="text-indigo-600 hover:underline font-medium">Open chat</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-10 text-center text-gray-500">No live chat sessions found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $sessions->links() }}
    </div>
</x-admin-layout>
