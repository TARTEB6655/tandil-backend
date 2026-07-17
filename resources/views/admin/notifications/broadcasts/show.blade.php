<x-admin-layout>
    @php
        $counts = $broadcast->recipientCountsForApi();
    @endphp
    <div class="space-y-6 max-w-4xl">
        <div>
            <a href="{{ route('admin.notifications.broadcasts.index') }}" class="text-sm text-indigo-600 hover:text-indigo-800">&larr; Back to broadcast log</a>
            <h1 class="mt-4 text-xl font-semibold text-gray-900 dark:text-gray-100">Broadcast #{{ $broadcast->id }}</h1>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Sent {{ $broadcast->created_at?->format('M j, Y g:i A') }}
                @if($broadcast->sentBy)
                    by {{ $broadcast->sentBy->name }} ({{ $broadcast->sentBy->email }})
                @endif
            </p>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6 shadow-sm">
            <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-2">Message</h2>
            <p class="text-lg font-medium text-gray-900 dark:text-gray-100">{{ $broadcast->title }}</p>
            <p class="mt-3 text-sm text-gray-700 dark:text-gray-300 whitespace-pre-wrap">{{ $broadcast->message }}</p>
            <p class="mt-4 text-xs text-gray-500">Scope: <span class="capitalize">{{ $broadcast->scope_type }}</span>
                @if($broadcast->scope_role) · role: {{ $broadcast->scope_role }} @endif
            </p>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6 shadow-sm">
            <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-4">Recipients by role</h2>
            <dl class="grid grid-cols-2 sm:grid-cols-3 gap-4">
                <div class="rounded-lg bg-gray-50 dark:bg-gray-900/50 p-4 border border-gray-100 dark:border-gray-700">
                    <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">Customers</dt>
                    <dd class="text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ $counts['customers'] }}</dd>
                </div>
                <div class="rounded-lg bg-gray-50 dark:bg-gray-900/50 p-4 border border-gray-100 dark:border-gray-700">
                    <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">Technicians</dt>
                    <dd class="text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ $counts['technicians'] }}</dd>
                </div>
                <div class="rounded-lg bg-gray-50 dark:bg-gray-900/50 p-4 border border-gray-100 dark:border-gray-700">
                    <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">Supervisors</dt>
                    <dd class="text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ $counts['supervisors'] }}</dd>
                </div>
                <div class="rounded-lg bg-gray-50 dark:bg-gray-900/50 p-4 border border-gray-100 dark:border-gray-700">
                    <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">Area managers</dt>
                    <dd class="text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ $counts['area_managers'] }}</dd>
                </div>
                <div class="rounded-lg bg-gray-50 dark:bg-gray-900/50 p-4 border border-gray-100 dark:border-gray-700">
                    <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">HR</dt>
                    <dd class="text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ $counts['hr'] }}</dd>
                </div>
                <div class="rounded-lg bg-gray-50 dark:bg-gray-900/50 p-4 border border-gray-100 dark:border-gray-700">
                    <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">Vendors</dt>
                    <dd class="text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ $counts['vendors'] }}</dd>
                </div>
                <div class="rounded-lg bg-gray-50 dark:bg-gray-900/50 p-4 border border-gray-100 dark:border-gray-700">
                    <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">Admins</dt>
                    <dd class="text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ $counts['admins'] }}</dd>
                </div>
                <div class="rounded-lg bg-gray-50 dark:bg-gray-900/50 p-4 border border-gray-100 dark:border-gray-700">
                    <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">Other</dt>
                    <dd class="text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ $counts['other'] }}</dd>
                </div>
                <div class="rounded-lg bg-indigo-50 dark:bg-indigo-900/20 p-4 border border-indigo-100 dark:border-indigo-800 col-span-2 sm:col-span-3">
                    <dt class="text-xs font-medium text-indigo-800 dark:text-indigo-300">Total recipients</dt>
                    <dd class="text-2xl font-semibold text-indigo-900 dark:text-indigo-100">{{ $counts['total'] }}</dd>
                </div>
            </dl>
        </div>

        @if($broadcast->messages_by_role && count($broadcast->messages_by_role) > 0)
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6 shadow-sm">
                <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-3">Per-role overrides used</h2>
                <pre class="text-xs bg-gray-50 dark:bg-gray-900 p-4 rounded-lg overflow-x-auto text-gray-800 dark:text-gray-200">{{ json_encode($broadcast->messages_by_role, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
            </div>
        @endif
    </div>
</x-admin-layout>
