<x-vendor-layout>
    @php
        $data = $notification->data ?? [];
        $title = $data['title'] ?? class_basename($notification->type ?? 'Notification');
        $message = $data['message'] ?? 'No message';
    @endphp

    <div class="mb-4 flex items-center justify-between">
        <a href="{{ route('vendor.notifications.index') }}" class="text-sm text-indigo-600 hover:text-indigo-800">&larr; Back to notifications</a>
        <form action="{{ route('vendor.notifications.destroy', $notification->id) }}" method="POST">
            @csrf
            @method('DELETE')
            <button type="submit" class="inline-flex items-center gap-2 rounded-lg border border-red-200 bg-red-50 px-3 py-1.5 text-sm text-red-700 hover:bg-red-100">
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
</x-vendor-layout>
