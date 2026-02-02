<x-admin-layout>
    <div class="space-y-6">
        <div class="flex items-center gap-4">
            <a href="{{ route('admin.settings.index') }}" class="text-gray-500 hover:text-gray-700 transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
            </a>
            <div>
                <h1 class="text-2xl font-semibold text-gray-900">Debug Logs</h1>
                <p class="mt-1 text-sm text-gray-500">View system logs (last {{ $lines }} lines)</p>
            </div>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
            <div class="border-b border-gray-100 bg-gray-50 px-4 py-3 flex items-center justify-between">
                <span class="text-sm font-medium text-gray-600">laravel.log</span>
                <a href="{{ route('admin.settings.debug-logs', ['lines' => 50]) }}" class="text-xs text-indigo-600 hover:text-indigo-700">50 lines</a>
                <a href="{{ route('admin.settings.debug-logs', ['lines' => 100]) }}" class="text-xs text-indigo-600 hover:text-indigo-700">100 lines</a>
                <a href="{{ route('admin.settings.debug-logs', ['lines' => 500]) }}" class="text-xs text-indigo-600 hover:text-indigo-700">500 lines</a>
            </div>
            <pre class="p-4 text-xs text-gray-800 overflow-x-auto max-h-[70vh] overflow-y-auto bg-gray-50 font-mono whitespace-pre-wrap break-words">{{ $log ?: 'No log content.' }}</pre>
        </div>
    </div>
</x-admin-layout>
