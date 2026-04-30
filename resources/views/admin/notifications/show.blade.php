<x-admin-layout>
    @php
        $data = $notification->data ?? [];
        $title = $data['title'] ?? class_basename($notification->type ?? 'Notification');
        $message = $data['message'] ?? 'No message';
    @endphp

    <div class="mb-4 flex items-center justify-between">
        <a href="{{ route('admin.notifications.index') }}" class="text-sm text-indigo-600 hover:text-indigo-800">&larr; Back to notifications</a>
        <form action="{{ route('admin.notifications.destroy', $notification->id) }}" method="POST">
            @csrf
            @method('DELETE')
            <button type="submit" class="inline-flex items-center gap-2 rounded-lg border border-red-200 bg-red-50 px-3 py-1.5 text-sm text-red-700 hover:bg-red-100" title="Delete">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M9 7V4h6v3M4 7h16" />
                </svg>
                Delete
            </button>
        </form>
    </div>

    <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
        <div class="mb-3 flex items-start justify-between gap-4">
            <h1 class="text-lg font-semibold text-gray-900">{{ $title }}</h1>
            <div class="text-right text-xs text-gray-500">
                <div>{{ $notification->created_at?->format('d M Y') }}</div>
                <div>{{ $notification->created_at?->format('h:i A') }}</div>
            </div>
        </div>
        <div class="text-sm leading-6 text-gray-700">
            {{ $message }}
        </div>
    </div>
</x-admin-layout>
