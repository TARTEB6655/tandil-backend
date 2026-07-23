<x-admin-layout>
    <div class="space-y-6" x-data="videoBannerManager()" x-init="initDeleteModal()">
        <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-4">
            <div>
                <h1 class="text-2xl font-semibold text-gray-900 dark:text-gray-100">Video Banners</h1>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Preview matches the customer app “Featured Video” card. Max upload 30MB.
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
            <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
                @foreach($videoBanners as $vb)
                    <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden">
                        {{-- App-style featured video banner preview --}}
                        <div class="relative aspect-[16/10] bg-slate-900 overflow-hidden group"
                             x-data="{ playing: false }"
                             @mouseenter="$refs.vid && $refs.vid.play()"
                             @mouseleave="if (!playing) { $refs.vid && $refs.vid.pause(); $refs.vid && ($refs.vid.currentTime = 0); }">
                            @if($vb->video_url)
                                <video x-ref="vid"
                                       src="{{ $vb->video_url }}"
                                       class="absolute inset-0 h-full w-full object-cover"
                                       muted
                                       loop
                                       playsinline
                                       preload="metadata"></video>
                            @else
                                <div class="absolute inset-0 flex items-center justify-center text-slate-400 text-sm">No video file</div>
                            @endif

                            {{-- Soft overlays like a marketing banner --}}
                            <div class="pointer-events-none absolute inset-0 bg-gradient-to-t from-black/75 via-black/25 to-black/10"></div>
                            <div class="pointer-events-none absolute inset-x-0 top-0 h-24 bg-gradient-to-b from-black/40 to-transparent"></div>

                            {{-- Badge --}}
                            @if($vb->badge_text)
                                <div class="absolute top-4 left-4 z-10">
                                    <span class="inline-flex items-center rounded-full bg-white/95 px-3 py-1 text-xs font-semibold text-slate-800 shadow-sm backdrop-blur">
                                        {{ $vb->badge_text }}
                                    </span>
                                </div>
                            @endif

                            {{-- Status chip --}}
                            <div class="absolute top-4 right-4 z-10">
                                <span class="inline-flex items-center rounded-full px-2.5 py-1 text-[11px] font-semibold shadow-sm {{ $vb->is_active ? 'bg-emerald-500 text-white' : 'bg-slate-500 text-white' }}">
                                    {{ $vb->is_active ? 'Live on app' : 'Inactive' }}
                                </span>
                            </div>

                            {{-- Center play affordance --}}
                            <button type="button"
                                    class="absolute inset-0 z-10 flex items-center justify-center"
                                    @click="playing = !playing; if (playing) { $refs.vid.muted = false; $refs.vid.play(); } else { $refs.vid.pause(); $refs.vid.muted = true; }">
                                <span class="flex h-14 w-14 items-center justify-center rounded-full bg-white/95 text-indigo-700 shadow-lg ring-1 ring-black/5 transition group-hover:scale-105"
                                      x-show="!playing">
                                    <svg class="h-6 w-6 ml-0.5" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                                </span>
                                <span class="flex h-14 w-14 items-center justify-center rounded-full bg-black/50 text-white shadow-lg backdrop-blur"
                                      x-cloak
                                      x-show="playing">
                                    <svg class="h-6 w-6" fill="currentColor" viewBox="0 0 24 24"><path d="M6 5h4v14H6zm8 0h4v14h-4z"/></svg>
                                </span>
                            </button>

                            {{-- Bottom content (title + CTA) --}}
                            <div class="absolute inset-x-0 bottom-0 z-10 p-5 sm:p-6">
                                <p class="text-[11px] font-semibold uppercase tracking-[0.14em] text-white/70">Featured video</p>
                                <h3 class="mt-1 text-xl sm:text-2xl font-semibold text-white drop-shadow">
                                    {{ $vb->title ?: 'Untitled video banner' }}
                                </h3>
                                <div class="mt-4 flex flex-wrap items-center gap-3">
                                    @if($vb->button_text)
                                        <span class="inline-flex items-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-md">
                                            {{ $vb->button_text }}
                                        </span>
                                    @endif
                                    <span class="text-xs text-white/70">App home preview</span>
                                </div>
                            </div>
                        </div>

                        {{-- Admin actions --}}
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 px-5 py-4 border-t border-gray-100 dark:border-gray-700 bg-gray-50/70 dark:bg-gray-900/30">
                            <div class="min-w-0 text-xs text-gray-500 dark:text-gray-400">
                                <p class="font-medium text-gray-700 dark:text-gray-300 truncate">ID #{{ $vb->id }}</p>
                                <p class="truncate mt-0.5">{{ $vb->video_url }}</p>
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
                    </div>
                @endforeach
            </div>
        @else
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm p-12 text-center">
                <div class="mx-auto w-14 h-14 rounded-full bg-indigo-50 dark:bg-indigo-900/30 flex items-center justify-center mb-4">
                    <svg class="w-7 h-7 text-indigo-600 dark:text-indigo-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z" /></svg>
                </div>
                <h3 class="text-base font-medium text-gray-900 dark:text-gray-100">No video banners yet</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Create a featured video card for the customer app home screen.</p>
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
