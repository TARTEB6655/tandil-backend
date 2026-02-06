<x-client-layout>
    <div class="mb-4 sm:mb-6">
        <h1 class="text-lg sm:text-xl font-medium text-gray-900">Services</h1>
        <p class="mt-1 text-xs sm:text-sm text-gray-500">Place service orders. Choose a category to view available services.</p>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 sm:gap-6">
        @forelse($categories as $category)
            <a href="{{ route('client.services.category', $category->id) }}" 
               class="block bg-white rounded-xl border border-gray-200 shadow-sm hover:shadow-md hover:border-indigo-200 transition-all p-5">
                <div class="flex items-start justify-between">
                    @if($category->image_url)
                        <img src="{{ $category->image_url }}" alt="{{ $category->name }}" class="w-14 h-14 rounded-lg object-cover border border-gray-200">
                    @else
                        <div class="w-14 h-14 rounded-lg bg-gray-100 flex items-center justify-center">
                            <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                            </svg>
                        </div>
                    @endif
                    <span class="px-2 py-0.5 text-xs font-medium rounded-full bg-green-100 text-green-800">{{ $category->products_count ?? 0 }} services</span>
                </div>
                <h3 class="mt-3 text-base font-semibold text-gray-900">{{ $category->name }}</h3>
                @if($category->description)
                    <p class="mt-1 text-sm text-gray-500 line-clamp-2">{{ Str::limit($category->description, 80) }}</p>
                @endif
                <span class="mt-3 inline-flex items-center text-sm font-medium text-indigo-600">View services</span>
            </a>
        @empty
            <div class="col-span-full bg-white rounded-xl border border-gray-200 shadow-sm p-8 text-center">
                <p class="text-gray-500">No service categories available at the moment.</p>
            </div>
        @endforelse
    </div>
</x-client-layout>
