<x-admin-layout>
    <div class="space-y-6">
        <div class="flex items-center gap-4">
            <a href="{{ route('admin.settings.index') }}" class="text-gray-500 hover:text-gray-700 transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
            </a>
            <div>
                <h1 class="text-2xl font-semibold text-gray-900 dark:text-gray-100">Debug Logs</h1>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">View system logs (last {{ $lines }} lines). <span class="text-gray-600 dark:text-gray-300">Debug (<code class="rounded bg-gray-100 px-1 dark:bg-gray-800">APP_DEBUG</code>) and environment (<code class="rounded bg-gray-100 px-1 dark:bg-gray-800">APP_ENV</code>) are set in <code class="rounded bg-gray-100 px-1 dark:bg-gray-800">.env</code> on the server.</span></p>
            </div>
        </div>
        <div class="flex flex-wrap items-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-3 dark:border-gray-700 dark:bg-gray-800">
            <span class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Runtime</span>
            <span class="inline-flex items-center rounded-md px-2.5 py-1 text-xs font-medium {{ ($debug ?? false) ? 'bg-amber-100 text-amber-900 dark:bg-amber-900/40 dark:text-amber-100' : 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-200' }}">APP_DEBUG: {{ ($debug ?? false) ? 'true' : 'false' }}</span>
            <span class="inline-flex items-center rounded-md bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-700 dark:bg-gray-700 dark:text-gray-200">APP_ENV: {{ $env ?? app()->environment() }}</span>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden dark:border-gray-700 dark:bg-gray-800">
            <div class="border-b border-gray-100 bg-gray-50 px-4 py-3 flex flex-wrap items-center justify-between gap-2 dark:border-gray-700 dark:bg-gray-900/40">
                <span class="text-sm font-medium text-gray-600">laravel.log</span>
                <a href="{{ route('admin.settings.debug-logs', ['lines' => 50]) }}" class="text-xs text-indigo-600 hover:text-indigo-700">50 lines</a>
                <a href="{{ route('admin.settings.debug-logs', ['lines' => 100]) }}" class="text-xs text-indigo-600 hover:text-indigo-700">100 lines</a>
                <a href="{{ route('admin.settings.debug-logs', ['lines' => 500]) }}" class="text-xs text-indigo-600 hover:text-indigo-700">500 lines</a>
            </div>
            <pre class="p-4 text-xs text-gray-800 overflow-x-auto max-h-[70vh] overflow-y-auto bg-gray-50 font-mono whitespace-pre-wrap break-words dark:bg-gray-900/50 dark:text-gray-100">{{ $log ?: 'No log content.' }}</pre>
        </div>
    </div>
</x-admin-layout>
