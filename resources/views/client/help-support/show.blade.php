<x-client-layout>
    <div class="mb-4 sm:mb-6 flex items-start justify-between gap-3">
        <div>
            <h1 class="text-lg sm:text-xl font-medium text-gray-900">Ticket #{{ $ticket->ticket_number }}</h1>
            <p class="mt-1 text-xs sm:text-sm text-gray-500">{{ $ticket->subject }}</p>
        </div>
        <a href="{{ route('client.help-support.index') }}" class="text-sm text-indigo-600 hover:text-indigo-800">Back to Help & Support</a>
    </div>

    @if(session('success'))
        <div class="mb-4 bg-green-50 border-l-4 border-green-400 p-3 rounded-md">
            <p class="text-sm text-green-700">{{ session('success') }}</p>
        </div>
    @endif

    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 sm:p-6 mb-6">
        <div class="grid sm:grid-cols-2 gap-4 text-sm">
            <div>
                <p class="text-gray-500">Email</p>
                <p class="font-medium text-gray-900">{{ $ticket->email }}</p>
            </div>
            <div>
                <p class="text-gray-500">Status</p>
                <p class="font-medium text-gray-900">{{ strtoupper(str_replace('_', ' ', $ticket->status)) }}</p>
            </div>
            <div class="sm:col-span-2">
                <p class="text-gray-500">Description</p>
                <p class="text-gray-900 mt-1">{{ $ticket->message }}</p>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 sm:p-6 mb-6">
        <h2 class="text-base font-medium text-gray-900 mb-3">Conversation</h2>
        <div class="space-y-3">
            @forelse($ticket->replies as $reply)
                <div class="rounded-lg border p-3 {{ $reply->is_admin ? 'bg-blue-50 border-blue-100' : 'bg-gray-50 border-gray-200' }}">
                    <div class="flex items-center justify-between">
                        <p class="text-sm font-medium text-gray-900">
                            {{ $reply->is_admin ? 'Admin' : 'You' }}
                        </p>
                        <p class="text-xs text-gray-500">{{ $reply->created_at?->diffForHumans() }}</p>
                    </div>
                    <p class="mt-1 text-sm text-gray-700">{{ $reply->message }}</p>
                </div>
            @empty
                <p class="text-sm text-gray-500">No replies yet.</p>
            @endforelse
        </div>
    </div>

    @if($ticket->status !== 'closed')
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 sm:p-6">
            <h2 class="text-base font-medium text-gray-900 mb-3">Add reply</h2>
            <form action="{{ route('client.help-support.reply', $ticket->id) }}" method="POST" class="space-y-3 max-w-xl">
                @csrf
                <div>
                    <label for="message" class="block text-sm font-medium text-gray-700">Message</label>
                    <textarea name="message" id="message" rows="4" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">{{ old('message') }}</textarea>
                    @error('message')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                <button type="submit" class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700">Send reply</button>
            </form>
        </div>
    @else
        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 text-sm text-yellow-800">
            This ticket is closed. You cannot add new replies.
        </div>
    @endif
</x-client-layout>

