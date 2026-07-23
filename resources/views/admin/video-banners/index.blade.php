<x-admin-layout>
    <div class="space-y-6" x-data="videoBannerManager()" x-init="initDeleteModal()">
        <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-4">
            <div>
                <h1 class="text-2xl font-semibold text-gray-900 dark:text-gray-100">Video Banners</h1>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Featured video on the customer app home screen (<code class="text-xs">GET /api/video-banners</code>). Max upload 30MB — server compresses for fast playback.
                </p>
            </div>
            <a href="{{ route('admin.video-banners.create') }}"
               class="inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg shadow-sm transition-colors focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-gray-900">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                Add Video Banner
            </a>
        </div>

        @if(session('success'))
            <div class="rounded-lg border border-green-200 dark:border-green-800 bg-green-50 dark:bg-green-900/20 text-green-800 dark:text-green-200 px-4 py-3 flex items-center gap-3">
                <svg class="w-5 h-5 flex-shrink-0 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                <span>{{ session('success') }}</span>
            </div>
        @endif

        @if($videoBanners->count() > 0)
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden divide-y divide-gray-200 dark:divide-gray-700">
                @foreach($videoBanners as $vb)
                    <div class="px-4 sm:px-6 py-4 flex flex-col sm:flex-row sm:items-center gap-4">
                        <div class="flex-shrink-0 w-full sm:w-48 aspect-video rounded-lg border border-gray-200 dark:border-gray-600 overflow-hidden bg-gray-900">
                            @if($vb->video_url)
                                <video src="{{ $vb->video_url }}" class="w-full h-full object-cover" muted playsinline preload="metadata" controls></video>
                            @else
                                <div class="w-full h-full flex items-center justify-center text-gray-400 text-xs">No video</div>
                            @endif
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <h3 class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $vb->title ?: 'Untitled' }}</h3>
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $vb->is_active ? 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300' : 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-400' }}">
                                    {{ $vb->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </div>
                            <div class="mt-1 text-xs text-gray-500 dark:text-gray-400 space-y-0.5">
                                @if($vb->badge_text)
                                    <p>Badge: {{ $vb->badge_text }}</p>
                                @endif
                                @if($vb->button_text)
                                    <p>Button: {{ $vb->button_text }}</p>
                                @endif
                                <p class="truncate">URL: {{ $vb->video_url }}</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-2 flex-shrink-0">
                            <button type="button"
                                    data-id="{{ $vb->id }}"
                                    data-active="{{ $vb->is_active ? '1' : '0' }}"
                                    onclick="window.toggleVideoBannerStatus(this)"
                                    class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-lg transition-colors {{ $vb->is_active ? 'bg-amber-100 text-amber-800 hover:bg-amber-200 dark:bg-amber-900/40 dark:text-amber-300' : 'bg-green-100 text-green-800 hover:bg-green-200 dark:bg-green-900/40 dark:text-green-300' }}">
                                <span class="toggle-label">{{ $vb->is_active ? 'Disable' : 'Enable' }}</span>
                            </button>
                            <a href="{{ route('admin.video-banners.edit', $vb->id) }}"
                               class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-indigo-600 dark:text-indigo-400 hover:bg-indigo-50 dark:hover:bg-indigo-900/30 rounded-lg transition-colors">
                                Edit
                            </a>
                            <button type="button"
                                    onclick="window.openVideoBannerDeleteModal({{ $vb->id }}, '{{ addslashes($vb->title ?: 'Untitled') }}')"
                                    class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/30 rounded-lg transition-colors">
                                Delete
                            </button>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm p-12 text-center">
                <div class="mx-auto w-14 h-14 rounded-full bg-gray-100 dark:bg-gray-700 flex items-center justify-center mb-4">
                    <svg class="w-7 h-7 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z" /></svg>
                </div>
                <h3 class="text-base font-medium text-gray-900 dark:text-gray-100">No video banners yet</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Upload a featured video for the customer app home screen.</p>
                <div class="mt-6">
                    <a href="{{ route('admin.video-banners.create') }}"
                       class="inline-flex items-center gap-2 px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg shadow-sm transition-colors">
                        Add your first video banner
                    </a>
                </div>
            </div>
        @endif

        <div x-show="showDeleteModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto" aria-modal="true" role="dialog">
            <div class="flex min-h-full items-center justify-center p-4">
                <div class="fixed inset-0 bg-black/50" @click="showDeleteModal = false"></div>
                <div class="relative bg-white dark:bg-gray-800 rounded-xl shadow-xl max-w-md w-full p-6 border border-gray-200 dark:border-gray-700">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Delete video banner?</h3>
                    <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                        "<span x-text="deleteTitle" class="font-medium"></span>" will be removed from the app. This cannot be undone.
                    </p>
                    <div class="mt-6 flex gap-3 justify-end">
                        <button type="button" @click="showDeleteModal = false"
                                class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 rounded-lg">Cancel</button>
                        <form :action="deleteFormAction" method="POST" class="inline">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-red-600 hover:bg-red-700 rounded-lg">Delete</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function videoBannerManager() {
            return {
                showDeleteModal: false,
                deleteTitle: '',
                deleteFormAction: '',
                initDeleteModal() {
                    var self = this;
                    window.openVideoBannerDeleteModal = function (id, title) {
                        self.deleteFormAction = '{{ url("admin/video-banners") }}/' + id;
                        self.deleteTitle = title || 'Untitled';
                        self.showDeleteModal = true;
                    };
                }
            };
        }

        window.toggleVideoBannerStatus = function (btn) {
            var id = btn.getAttribute('data-id');
            var wasActive = btn.getAttribute('data-active') === '1';
            btn.disabled = true;
            var label = btn.querySelector('.toggle-label');
            if (label) label.textContent = '…';

            fetch('{{ url("admin/video-banners") }}/' + id + '/toggle-status', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' }
            })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.success) {
                    window.location.reload();
                } else {
                    btn.disabled = false;
                    if (label) label.textContent = wasActive ? 'Disable' : 'Enable';
                }
            })
            .catch(function () {
                btn.disabled = false;
                if (label) label.textContent = wasActive ? 'Disable' : 'Enable';
            });
        };
    </script>
</x-admin-layout>
