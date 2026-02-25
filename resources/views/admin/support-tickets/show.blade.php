<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-3">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Support Ticket #{{ $ticket->ticket_number }}</h2>
            <a href="{{ route('admin.support-tickets.index') }}" class="text-sm text-indigo-600 hover:text-indigo-800">Back to tickets</a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if(session('success'))
                <div class="bg-green-50 border border-green-200 rounded-md p-3 text-sm text-green-700">
                    {{ session('success') }}
                </div>
            @endif

            <div class="bg-white shadow-sm sm:rounded-lg border border-gray-200 p-5">
                <div class="grid md:grid-cols-2 gap-4 text-sm">
                    <div>
                        <p class="text-gray-500">Client</p>
                        <p class="font-medium text-gray-900">{{ $ticket->user->name ?? 'N/A' }}</p>
                    </div>
                    <div>
                        <p class="text-gray-500">Email</p>
                        <p class="font-medium text-gray-900">{{ $ticket->email }}</p>
                    </div>
                    <div>
                        <p class="text-gray-500">Subject</p>
                        <p class="font-medium text-gray-900">{{ $ticket->subject }}</p>
                    </div>
                    <div>
                        <p class="text-gray-500">Status</p>
                        <p class="font-medium text-gray-900">{{ strtoupper(str_replace('_', ' ', $ticket->status)) }}</p>
                    </div>
                    <div class="md:col-span-2">
                        <p class="text-gray-500">Description</p>
                        <p class="text-gray-900 mt-1">{{ $ticket->message }}</p>
                    </div>
                </div>
            </div>

            <div class="bg-white shadow-sm sm:rounded-lg border border-gray-200 p-5">
                <h3 class="text-base font-semibold text-gray-900 mb-3">Conversation</h3>
                <div class="space-y-3">
                    @forelse($ticket->replies as $reply)
                        <div class="rounded-lg border p-3 {{ $reply->is_admin ? 'bg-blue-50 border-blue-100' : 'bg-gray-50 border-gray-200' }}">
                            <div class="flex items-center justify-between">
                                <p class="text-sm font-medium text-gray-900">{{ $reply->is_admin ? 'Admin' : ($reply->user->name ?? 'Client') }}</p>
                                <p class="text-xs text-gray-500">{{ $reply->created_at?->format('d M Y, h:i A') }}</p>
                            </div>
                            <p class="mt-1 text-sm text-gray-700">{{ $reply->message }}</p>
                        </div>
                    @empty
                        <p class="text-sm text-gray-500">No replies yet.</p>
                    @endforelse
                </div>
            </div>

            <div class="grid lg:grid-cols-2 gap-6">
                <div class="bg-white shadow-sm sm:rounded-lg border border-gray-200 p-5">
                    @php
                        $gmailSubject = "[Support {$ticket->ticket_number}] {$ticket->subject}";
                        $gmailBody = "Hi,\n\nRegarding your support ticket {$ticket->ticket_number}.\n\n";
                        $gmailBody .= "Original message:\n{$ticket->message}\n\n";
                        $gmailBody .= "Reply:\n";
                        $gmailUrl = 'https://mail.google.com/mail/?view=cm&fs=1'
                            . '&to=' . rawurlencode($ticket->email)
                            . '&su=' . rawurlencode($gmailSubject)
                            . '&body=' . rawurlencode($gmailBody);
                    @endphp

                    <h3 class="text-base font-semibold text-gray-900 mb-2">Reply via Gmail</h3>
                    <p class="text-xs text-gray-500 mb-3">No SMTP required. Clicking below opens Gmail compose with client email and ticket context prefilled.</p>
                    <a href="{{ $gmailUrl }}" target="_blank" rel="noopener noreferrer"
                       class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white rounded-md text-sm hover:bg-indigo-700">
                        Open Gmail Compose
                    </a>

                    <div class="mt-5 pt-4 border-t border-gray-200">
                        <h4 class="text-sm font-semibold text-gray-900 mb-2">Optional in-app reply</h4>
                        <p class="text-xs text-gray-500 mb-3">Use this only if you also want the reply in the app ticket thread.</p>
                    </div>
                    <form method="POST" action="{{ route('admin.support-tickets.reply', $ticket->id) }}" class="space-y-3">
                        @csrf
                        <div>
                            <label for="message" class="block text-sm font-medium text-gray-700">Message</label>
                            <textarea name="message" id="message" rows="5" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">{{ old('message') }}</textarea>
                            @error('message')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-md text-sm hover:bg-indigo-700">Send reply</button>
                    </form>
                </div>

                <div class="bg-white shadow-sm sm:rounded-lg border border-gray-200 p-5 space-y-4">
                    <div>
                        <h3 class="text-base font-semibold text-gray-900 mb-2">Update status</h3>
                        <form method="POST" action="{{ route('admin.support-tickets.update-status', $ticket->id) }}" class="flex items-center gap-2">
                            @csrf
                            @method('PUT')
                            <select name="status" class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                @foreach(['open','in_progress','resolved','closed'] as $status)
                                    <option value="{{ $status }}" @selected($ticket->status === $status)>{{ strtoupper(str_replace('_', ' ', $status)) }}</option>
                                @endforeach
                            </select>
                            <button class="px-3 py-2 bg-gray-800 text-white rounded-md text-sm hover:bg-black">Save</button>
                        </form>
                    </div>

                    <div class="pt-2 border-t border-gray-200">
                        <h3 class="text-base font-semibold text-red-700 mb-2">Delete ticket</h3>
                        <form method="POST" action="{{ route('admin.support-tickets.destroy', $ticket->id) }}" onsubmit="return confirm('Delete this ticket permanently?');">
                            @csrf
                            @method('DELETE')
                            <button class="px-3 py-2 bg-red-600 text-white rounded-md text-sm hover:bg-red-700">Delete ticket</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>

