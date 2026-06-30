<x-admin-layout>
    <div class="space-y-6">
        <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-4">
            <div>
                <h1 class="text-2xl font-semibold text-gray-900 dark:text-gray-100">Maintenance Photos</h1>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Before/after showcase for the client app home screen.</p>
            </div>
            <a href="{{ route('admin.maintenance-photos.create') }}" class="inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg">
                Add Maintenance Photo
            </a>
        </div>

        @if(session('success'))
            <div class="rounded-lg border border-green-200 bg-green-50 text-green-800 px-4 py-3">{{ session('success') }}</div>
        @endif

        @if($photos->count())
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
                <div class="divide-y divide-gray-200 dark:divide-gray-700">
                    @foreach($photos as $photo)
                        <div class="p-4 flex flex-col sm:flex-row gap-4 sm:items-center">
                            <div class="flex gap-2">
                                <img src="{{ $photo->before_image_url }}" alt="Before" class="w-24 h-20 object-cover rounded-lg border border-gray-200 dark:border-gray-600" loading="lazy">
                                <img src="{{ $photo->after_image_url }}" alt="After" class="w-24 h-20 object-cover rounded-lg border border-gray-200 dark:border-gray-600" loading="lazy">
                            </div>
                            <div class="flex-1 min-w-0 text-sm">
                                <p class="font-medium text-gray-900 dark:text-gray-100">{{ $photo->title ?: 'Untitled' }}</p>
                                <p class="text-gray-500 dark:text-gray-400">Priority: {{ $photo->priority }}</p>
                                <p class="mt-1">
                                    <span class="inline-flex px-2 py-0.5 rounded-full text-xs {{ $photo->is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600' }}">
                                        {{ $photo->is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </p>
                            </div>
                            <div class="flex gap-2">
                                <a href="{{ route('admin.maintenance-photos.edit', $photo->id) }}" class="px-3 py-1.5 text-xs font-medium text-indigo-600 rounded-lg hover:bg-indigo-50">Edit</a>
                                <form method="POST" action="{{ route('admin.maintenance-photos.destroy', $photo->id) }}" onsubmit="return confirm('Delete this photo?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="px-3 py-1.5 text-xs font-medium text-red-600 rounded-lg hover:bg-red-50">Delete</button>
                                </form>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
            <div>{{ $photos->links() }}</div>
        @else
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-10 text-center text-gray-500">
                No maintenance photos yet.
            </div>
        @endif
    </div>
</x-admin-layout>
