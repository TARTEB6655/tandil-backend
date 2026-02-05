<x-admin-layout>
    <div class="space-y-6" x-data="bannerManager()" x-init="initDeleteModal()" id="banner-manager-root">
        <!-- Page header -->
        <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-4">
            <div>
                <h1 class="text-2xl font-semibold text-gray-900 dark:text-gray-100">Home Screen Banners</h1>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Manage banners shown on the customer app home screen. Drag to reorder.</p>
            </div>
            <a href="{{ route('admin.banners.create') }}" class="inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg shadow-sm transition-colors focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-gray-900">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                Add Banner
            </a>
        </div>

        @if(session('success'))
            <div class="rounded-lg border border-green-200 dark:border-green-800 bg-green-50 dark:bg-green-900/20 text-green-800 dark:text-green-200 px-4 py-3 flex items-center gap-3">
                <svg class="w-5 h-5 flex-shrink-0 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                <span>{{ session('success') }}</span>
            </div>
        @endif

        @if($banners->count() > 0)
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden">
                <div class="px-4 sm:px-6 py-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-800/50">
                    <div class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400">
                        <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4" /></svg>
                        <span>Drag the handle to reorder. Lower position = higher priority (shown first on app).</span>
                    </div>
                    <p id="order-saving-hint" class="mt-1 text-xs text-amber-600 dark:text-amber-400 hidden">Saving order…</p>
                </div>
                <div id="banners-list" class="divide-y divide-gray-200 dark:divide-gray-700">
                    @foreach($banners as $banner)
                        <div class="banner-item group px-4 sm:px-6 py-4 hover:bg-gray-50 dark:hover:bg-gray-700/30 transition-colors flex items-center gap-4" data-id="{{ $banner->id }}" data-priority="{{ $banner->priority }}">
                            <div class="cursor-grab active:cursor-grabbing flex-shrink-0 p-1.5 rounded-lg text-gray-400 group-hover:text-gray-600 dark:group-hover:text-gray-300 transition-colors" title="Drag to reorder">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8h16M4 16h16" /></svg>
                            </div>
                            <div class="flex-shrink-0 w-28 h-[4.5rem] rounded-lg border border-gray-200 dark:border-gray-600 overflow-hidden bg-gray-100 dark:bg-gray-700">
                                <img src="{{ $banner->image_url }}" alt="{{ $banner->title ?: 'Banner' }}" class="w-full h-full object-cover">
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h3 class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $banner->title ?: 'Untitled' }}</h3>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $banner->is_active ? 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300' : 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-400' }}">
                                        {{ $banner->is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </div>
                                <div class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-0.5 text-xs text-gray-500 dark:text-gray-400">
                                    <span class="banner-priority">Position: {{ $banner->priority + 1 }}</span>
                                    @if($banner->link || $banner->action_value)
                                        <span class="text-gray-400 dark:text-gray-500">·</span>
                                        <a href="{{ $banner->action_value ?: $banner->link }}" target="_blank" rel="noopener noreferrer" class="text-indigo-600 dark:text-indigo-400 hover:underline">View</a>
                                    @else
                                        <span class="text-gray-400 dark:text-gray-500">·</span>
                                        <span>No link</span>
                                    @endif
                                </div>
                            </div>
                            <div class="flex items-center gap-2 flex-shrink-0">
                                <button type="button"
                                        data-banner-id="{{ $banner->id }}"
                                        data-banner-active="{{ $banner->is_active ? '1' : '0' }}"
                                        onclick="window.toggleBannerStatus(this)"
                                        class="toggle-btn inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-lg transition-colors {{ $banner->is_active ? 'bg-amber-100 text-amber-800 hover:bg-amber-200 dark:bg-amber-900/40 dark:text-amber-300 dark:hover:bg-amber-900/60' : 'bg-green-100 text-green-800 hover:bg-green-200 dark:bg-green-900/40 dark:text-green-300 dark:hover:bg-green-900/60' }}">
                                    <span class="toggle-label">{{ $banner->is_active ? 'Disable' : 'Enable' }}</span>
                                </button>
                                <a href="{{ route('admin.banners.edit', $banner->id) }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-indigo-600 dark:text-indigo-400 hover:bg-indigo-50 dark:hover:bg-indigo-900/30 rounded-lg transition-colors">
                                    Edit
                                </a>
                                <button type="button" onclick="window.openDeleteModal({{ $banner->id }}, '{{ addslashes($banner->title ?: 'Untitled') }}')" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/30 rounded-lg transition-colors">
                                    Delete
                                </button>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @else
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm p-12 text-center">
                <div class="mx-auto w-14 h-14 rounded-full bg-gray-100 dark:bg-gray-700 flex items-center justify-center mb-4">
                    <svg class="w-7 h-7 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                </div>
                <h3 class="text-base font-medium text-gray-900 dark:text-gray-100">No banners yet</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Add banners to show on the customer app home screen.</p>
                <div class="mt-6">
                    <a href="{{ route('admin.banners.create') }}" class="inline-flex items-center gap-2 px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg shadow-sm transition-colors">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                        Add your first banner
                    </a>
                </div>
            </div>
        @endif

        <!-- Delete confirmation modal -->
        <div x-show="showDeleteModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto" aria-modal="true" role="dialog">
            <div x-show="showDeleteModal" class="flex min-h-full items-center justify-center p-4">
                <div x-show="showDeleteModal" x-transition:enter="ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" class="fixed inset-0 bg-black/50 dark:bg-black/70" @click="showDeleteModal = false"></div>
                <div x-show="showDeleteModal" x-transition:enter="ease-out duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100" class="relative bg-white dark:bg-gray-800 rounded-xl shadow-xl max-w-md w-full p-6 border border-gray-200 dark:border-gray-700">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Delete banner?</h3>
                    <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">"<span x-text="deleteTitle" class="font-medium"></span>" will be removed from the home screen. This cannot be undone.</p>
                    <div class="mt-6 flex gap-3 justify-end">
                        <button type="button" @click="showDeleteModal = false" class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 rounded-lg transition-colors">Cancel</button>
                        <form :action="deleteFormAction" method="POST" class="inline">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-red-600 hover:bg-red-700 rounded-lg transition-colors">Delete</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>
    <script>
        function bannerManager() {
            return {
                showDeleteModal: false,
                deleteTitle: '',
                deleteFormAction: '',
                initDeleteModal() {
                    var self = this;
                    window.openDeleteModal = function(id, title) {
                        self.deleteFormAction = '{{ url("admin/banners") }}/' + id;
                        self.deleteTitle = title || 'Untitled';
                        self.showDeleteModal = true;
                    };
                }
            };
        }

        document.addEventListener('DOMContentLoaded', function() {
            const list = document.getElementById('banners-list');
            if (!list) return;

            const hint = document.getElementById('order-saving-hint');
            const updateOrder = function() {
                if (hint) hint.classList.remove('hidden');
                const items = document.querySelectorAll('.banner-item');
                const banners = Array.from(items).map(function(item, index) {
                    return { id: parseInt(item.dataset.id, 10), priority: index };
                });

                fetch('{{ route("admin.banners.update-order") }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                    body: JSON.stringify({ banners: banners })
                })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data.success) {
                        items.forEach(function(item, index) {
                            item.dataset.priority = index;
                            var span = item.querySelector('.banner-priority');
                            if (span) span.textContent = 'Position: ' + (index + 1);
                        });
                        if (typeof window.toast !== 'undefined') window.toast.success('Order saved.');
                    } else {
                        if (typeof window.toast !== 'undefined') window.toast.error('Could not save order.');
                    }
                })
                .catch(function() {
                    if (typeof window.toast !== 'undefined') window.toast.error('Could not save order.');
                })
                .finally(function() {
                    if (hint) hint.classList.add('hidden');
                });
            };

            window.sortable = Sortable.create(list, {
                handle: '.cursor-grab',
                animation: 150,
                ghostClass: 'opacity-50 bg-indigo-50 dark:bg-indigo-900/20',
                onEnd: updateOrder
            });
        });

        window.toggleBannerStatus = function(btn) {
            var id = btn.getAttribute('data-banner-id');
            var wasActive = btn.getAttribute('data-banner-active') === '1';
            btn.disabled = true;
            var label = btn.querySelector('.toggle-label');
            if (label) label.textContent = '…';

            fetch('{{ url("admin/banners") }}/' + id + '/toggle-status', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success) {
                    if (typeof window.toast !== 'undefined') window.toast.success(wasActive ? 'Banner disabled.' : 'Banner enabled.');
                    window.location.reload();
                } else {
                    btn.disabled = false;
                    if (label) label.textContent = wasActive ? 'Disable' : 'Enable';
                    if (typeof window.toast !== 'undefined') window.toast.error('Could not update status.');
                }
            })
            .catch(function() {
                btn.disabled = false;
                if (label) label.textContent = wasActive ? 'Disable' : 'Enable';
                if (typeof window.toast !== 'undefined') window.toast.error('Could not update status.');
            });
        };
    </script>
</x-admin-layout>
