@php
    $isAdmin = $message->is_admin;
@endphp
<div class="flex {{ $isAdmin ? 'justify-end' : 'justify-start' }}">
    <div class="max-w-[85%] sm:max-w-md rounded-2xl px-4 py-3 shadow-sm {{ $isAdmin ? 'rounded-br-md bg-gradient-to-br from-indigo-500 to-indigo-600 text-white' : 'rounded-bl-md bg-gray-100 dark:bg-gray-700 text-gray-900 dark:text-gray-100 border border-gray-200 dark:border-gray-600' }}">
        <div class="flex items-center justify-between gap-3 mb-1">
            <p class="text-xs font-semibold {{ $isAdmin ? 'text-indigo-100' : 'text-gray-600 dark:text-gray-300' }}">
                {{ $isAdmin ? 'You (Admin)' : ($message->user?->name ?? 'Vendor') }}
            </p>
            <p class="text-xs {{ $isAdmin ? 'text-indigo-200' : 'text-gray-500' }}">{{ $message->created_at?->format('d M, h:i A') }}</p>
        </div>
        <p class="text-sm whitespace-pre-wrap leading-relaxed {{ $isAdmin ? 'text-white' : '' }}">{{ $message->message }}</p>
    </div>
</div>
