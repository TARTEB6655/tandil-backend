<x-admin-layout>
    <h1 class="text-xl font-medium text-gray-900 mb-6">
            Tip Details
        </h1>

    <div class="space-y-6">
        <div class="bg-white shadow rounded-lg p-6 space-y-6">
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

            <div class="pt-4 flex gap-4">
                <a href="{{ route('admin.tips.edit', $tip) }}" class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700">Edit</a>
                <a href="{{ route('admin.tips.index') }}" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300">Back to Tips</a>
            </div>
        </div>
    </div>
</x-admin-layout>

