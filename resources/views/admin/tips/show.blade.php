<x-admin-layout>
    <div class="mb-6">
        <a href="{{ route('admin.dashboard') }}" class="inline-flex items-center text-sm text-gray-500 hover:text-gray-700 mb-2">
            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
            Dashboard
        </a>
        <a href="{{ route('admin.tips.index') }}" class="inline-flex items-center text-sm text-gray-500 hover:text-gray-700">
            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
            Tips
        </a>
    </div>
    <h1 class="text-xl font-semibold text-gray-900 mb-6">Tip Details</h1>

    <div class="space-y-6">
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6 space-y-6">
            <div>
                <h2 class="text-lg font-medium text-gray-900 mb-2">{{ $tip->title }}</h2>
                <div class="flex gap-4 mb-4">
                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">
                        {{ ucfirst($tip->type) }}
                    </span>
                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                        {{ $tip->status == 'published' ? 'bg-green-100 text-green-800' : 
                           ($tip->status == 'draft' ? 'bg-yellow-100 text-yellow-800' : 'bg-gray-100 text-gray-800') }}">
                        {{ ucfirst($tip->status) }}
                    </span>
                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-indigo-100 text-indigo-800">
                        {{ strtoupper($tip->language) }}
                    </span>
                </div>
                <div class="prose max-w-none">
                    <p class="text-gray-700 whitespace-pre-wrap">{{ $tip->content }}</p>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4 pt-4 border-t">
                <div>
                    <p class="text-sm text-gray-500">Created By</p>
                    <p class="text-sm font-medium text-gray-900">{{ $tip->creator->name ?? 'N/A' }}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Scheduled At</p>
                    <p class="text-sm font-medium text-gray-900">{{ $tip->scheduled_at ? $tip->scheduled_at->format('M d, Y H:i') : 'Not scheduled' }}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Created At</p>
                    <p class="text-sm font-medium text-gray-900">{{ $tip->created_at->format('M d, Y H:i') }}</p>
                </div>
            </div>

            <div class="pt-4 flex flex-wrap gap-3">
                <a href="{{ route('admin.tips.edit', $tip) }}" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 font-medium text-sm">Edit</a>
                <a href="{{ route('admin.tips.index') }}" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 font-medium text-sm">Back to Tips</a>
                <a href="{{ route('admin.dashboard') }}" class="px-4 py-2 text-gray-600 hover:text-gray-900 font-medium text-sm">Dashboard</a>
            </div>
        </div>
    </div>
</x-admin-layout>

