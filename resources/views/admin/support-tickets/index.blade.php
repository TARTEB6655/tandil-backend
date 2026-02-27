<x-admin-layout>
    <div class="space-y-6">
        <div class="flex items-center justify-between gap-3">
            <h1 class="text-xl font-medium text-gray-900 dark:text-gray-100">Support Tickets</h1>
        </div>

        <div>
            @if(session('success'))
                <div class="mb-4 bg-green-50 border border-green-200 rounded-md p-3 text-sm text-green-700">
                    {{ session('success') }}
                </div>
            @endif

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg border border-gray-200">
                <div class="p-4 border-b border-gray-200">
                    <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-3">
                        <input type="text" name="search" value="{{ request('search') }}" placeholder="Search ticket no, subject, email, client"
                               class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                        <select name="status" class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                            <option value="">All statuses</option>
                            @foreach(['open','in_progress','resolved','closed'] as $status)
                                <option value="{{ $status }}" @selected(request('status') === $status)>{{ strtoupper(str_replace('_', ' ', $status)) }}</option>
                            @endforeach
                        </select>
                        <select name="priority" class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                            <option value="">All priorities</option>
                            @foreach(['low','medium','high','urgent'] as $priority)
                                <option value="{{ $priority }}" @selected(request('priority') === $priority)>{{ strtoupper($priority) }}</option>
                            @endforeach
                        </select>
                        <button class="px-4 py-2 bg-indigo-600 text-white rounded-md text-sm hover:bg-indigo-700">Filter</button>
                    </form>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Ticket</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Client</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Email</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Created</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Action</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @forelse($tickets as $ticket)
                                <tr>
                                    <td class="px-4 py-3">
                                        <p class="text-sm font-medium text-gray-900">{{ $ticket->ticket_number }}</p>
                                        <p class="text-xs text-gray-500">{{ $ticket->subject }}</p>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-700">{{ $ticket->user->name ?? 'N/A' }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-700">{{ $ticket->email }}</td>
                                    <td class="px-4 py-3">
                                        <span class="px-2 py-1 rounded-full text-xs font-medium
                                            {{ $ticket->status === 'open' ? 'bg-yellow-100 text-yellow-800' : '' }}
                                            {{ $ticket->status === 'in_progress' ? 'bg-blue-100 text-blue-800' : '' }}
                                            {{ $ticket->status === 'resolved' ? 'bg-green-100 text-green-800' : '' }}
                                            {{ $ticket->status === 'closed' ? 'bg-gray-100 text-gray-700' : '' }}
                                        ">{{ strtoupper(str_replace('_', ' ', $ticket->status)) }}</span>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-500">{{ $ticket->created_at?->format('d M Y, h:i A') }}</td>
                                    <td class="px-4 py-3 text-right">
                                        <a href="{{ route('admin.support-tickets.show', $ticket->id) }}"
                                           class="inline-flex px-3 py-1.5 text-xs font-medium rounded-md bg-indigo-50 text-indigo-700 hover:bg-indigo-100">
                                            Open
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-4 py-6 text-sm text-gray-500 text-center">No support tickets found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="p-4 border-t border-gray-200">
                    {{ $tickets->links() }}
                </div>
            </div>
        </div>
    </div>
</x-admin-layout>

