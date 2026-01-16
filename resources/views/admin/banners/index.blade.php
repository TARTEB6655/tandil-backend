<x-admin-layout>
    <div class="space-y-6">
        <div class="flex justify-between items-center mb-6">
            <div>
                <h1 class="text-xl font-medium text-gray-900">Banners Management</h1>
                <p class="mt-1 text-sm text-gray-500">Manage home screen banners for customer app</p>
            </div>
            <a href="{{ route('admin.banners.create') }}" class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 transition-colors">
                + Create New Banner
            </a>
        </div>

        @if(session('success'))
            <div class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg flex items-center gap-2">
                <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span>{{ session('success') }}</span>
            </div>
        @endif

        @if($banners->count() > 0)
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
                <div class="px-6 py-4 bg-gray-50 border-b border-gray-200">
                    <p class="text-sm text-gray-600">Drag to reorder banners (lower priority = shown first)</p>
                </div>
                <div id="banners-list" class="divide-y divide-gray-200">
                    @foreach($banners as $banner)
                        <div class="banner-item px-6 py-4 hover:bg-gray-50 transition-colors" data-id="{{ $banner->id }}" data-priority="{{ $banner->priority }}">
                            <div class="flex items-center gap-4">
                                <!-- Drag Handle -->
                                <div class="cursor-move text-gray-400 hover:text-gray-600">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8h16M4 16h16" />
                                    </svg>
                                </div>

                                <!-- Image Preview -->
                                <div class="flex-shrink-0">
                                    <img src="{{ $banner->image_url }}" alt="{{ $banner->title }}" class="w-24 h-16 object-cover rounded-lg border border-gray-200">
                                </div>

                                <!-- Banner Info -->
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-3">
                                        <h3 class="text-sm font-medium text-gray-900">{{ $banner->title ?: 'Untitled Banner' }}</h3>
                                        <span class="px-2 py-1 text-xs font-medium rounded-full {{ $banner->is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' }}">
                                            {{ $banner->is_active ? 'Active' : 'Inactive' }}
                                        </span>
                                    </div>
                                    <div class="mt-1 text-xs text-gray-500">
                                        <span>Priority: {{ $banner->priority }}</span>
                                        @if($banner->link || $banner->action_value)
                                            <span class="mx-2">•</span>
                                            <span>Link: {{ $banner->action_value ?: $banner->link ?: 'None' }}</span>
                                        @endif
                                    </div>
                                </div>

                                <!-- Actions -->
                                <div class="flex items-center gap-2">
                                    <button 
                                        onclick="toggleStatus({{ $banner->id }})"
                                        class="px-3 py-1.5 text-xs font-medium rounded-md transition-colors {{ $banner->is_active ? 'bg-yellow-100 text-yellow-800 hover:bg-yellow-200' : 'bg-green-100 text-green-800 hover:bg-green-200' }}"
                                    >
                                        {{ $banner->is_active ? 'Disable' : 'Enable' }}
                                    </button>
                                    <a href="{{ route('admin.banners.edit', $banner->id) }}" class="px-3 py-1.5 text-xs font-medium text-indigo-600 hover:text-indigo-800 hover:bg-indigo-50 rounded-md transition-colors">
                                        Edit
                                    </a>
                                    <form action="{{ route('admin.banners.destroy', $banner->id) }}" method="POST" class="inline" onsubmit="return confirm('Are you sure you want to delete this banner?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="px-3 py-1.5 text-xs font-medium text-red-600 hover:text-red-800 hover:bg-red-50 rounded-md transition-colors">
                                            Delete
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @else
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-12 text-center">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
                <h3 class="mt-2 text-sm font-medium text-gray-900">No banners</h3>
                <p class="mt-1 text-sm text-gray-500">Get started by creating a new banner.</p>
                <div class="mt-6">
                    <a href="{{ route('admin.banners.create') }}" class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700">
                        + Create Banner
                    </a>
                </div>
            </div>
        @endif
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>
    <script>
        let sortable = null;
        
        document.addEventListener('DOMContentLoaded', function() {
            const list = document.getElementById('banners-list');
            if (list) {
                sortable = Sortable.create(list, {
                    handle: '.cursor-move',
                    animation: 150,
                    onEnd: function(evt) {
                        updateOrder();
                    }
                });
            }
        });

        function updateOrder() {
            const items = document.querySelectorAll('.banner-item');
            const banners = Array.from(items).map((item, index) => ({
                id: parseInt(item.dataset.id),
                priority: index
            }));

            fetch('{{ route("admin.banners.update-order") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({ banners })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update priority in DOM
                    items.forEach((item, index) => {
                        item.dataset.priority = index;
                        const prioritySpan = item.querySelector('.text-gray-500 span');
                        if (prioritySpan) {
                            prioritySpan.textContent = `Priority: ${index}`;
                        }
                    });
                    
                    // Show success message
                    showToast('Banner order updated successfully', 'success');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Failed to update banner order', 'error');
            });
        }

        function toggleStatus(id) {
            fetch(`/admin/banners/${id}/toggle-status`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    showToast('Failed to update banner status', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Failed to update banner status', 'error');
            });
        }

        function showToast(message, type) {
            // Use existing toast system if available
            if (typeof toast === 'function') {
                toast(message, type);
            } else {
                alert(message);
            }
        }
    </script>
</x-admin-layout>
