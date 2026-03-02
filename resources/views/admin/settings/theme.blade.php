<x-admin-layout>
    <div class="space-y-6 max-w-2xl">
        <div class="flex items-center gap-4">
            <a href="{{ route('admin.settings.index') }}" class="text-gray-500 hover:text-gray-700 transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
            </a>
            <div>
                <h1 class="text-2xl font-semibold text-gray-900">Appearance</h1>
                <p class="mt-1 text-sm text-gray-500">Admin dashboard display</p>
            </div>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
            <p class="text-gray-700">
                The admin dashboard uses a <strong>light theme only</strong>. Dark mode has been disabled for better compatibility across all browsers.
            </p>
            <p class="mt-3 text-sm text-gray-500">
                If you need to change appearance in the future, contact your development team.
            </p>
        </div>
    </div>
</x-admin-layout>
