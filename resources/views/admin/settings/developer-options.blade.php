<x-admin-layout>
    <div class="space-y-6 max-w-2xl">
        <div class="flex items-center gap-4">
            <a href="{{ route('admin.settings.index') }}" class="text-gray-500 hover:text-gray-700 transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
            </a>
            <div>
                <h1 class="text-2xl font-semibold text-gray-900">Developer Options</h1>
                <p class="mt-1 text-sm text-gray-500">Advanced developer settings</p>
            </div>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6 space-y-4">
            <div class="flex justify-between items-center py-2 border-b border-gray-100">
                <span class="text-sm font-medium text-gray-700">Debug mode</span>
                <span class="px-2 py-1 rounded text-sm font-medium {{ $debug ? 'bg-amber-100 text-amber-800' : 'bg-gray-100 text-gray-600' }}">{{ $debug ? 'On' : 'Off' }}</span>
            </div>
            <div class="flex justify-between items-center py-2 border-b border-gray-100">
                <span class="text-sm font-medium text-gray-700">Environment</span>
                <span class="px-2 py-1 rounded text-sm font-medium bg-gray-100 text-gray-600">{{ $env }}</span>
            </div>
            <div class="pt-2">
                <p class="text-sm text-gray-500">Debug mode and environment are set in <code class="bg-gray-100 px-1 rounded">.env</code>. Use Debug Logs to view system logs.</p>
            </div>
        </div>
    </div>
</x-admin-layout>
