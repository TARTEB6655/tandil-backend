<x-admin-layout>
    <div class="space-y-6"
         x-data="{
            title: @js(old('title', $videoBanner->title)),
            badge: @js(old('badge_text', $videoBanner->badge_text)),
            button: @js(old('button_text', $videoBanner->button_text)),
            previewUrl: @js($videoBanner->video_url),
            setVideo(input) {
                const MAX = 30 * 1024 * 1024;
                const hint = document.getElementById('video-size-hint');
                const btn = document.getElementById('submit-btn');
                if (!input.files || !input.files[0]) return;
                const file = input.files[0];
                if (file.size > MAX) {
                    hint.textContent = 'File is larger than 30MB. Please choose a smaller video.';
                    hint.className = 'mt-1 text-xs text-red-600';
                    btn.disabled = true;
                    input.value = '';
                    return;
                }
                btn.disabled = false;
                hint.textContent = 'Size: ' + (file.size / (1024 * 1024)).toFixed(2) + ' MB (new file)';
                hint.className = 'mt-1 text-xs text-gray-500';
                this.previewUrl = URL.createObjectURL(file);
            }
         }">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-semibold text-gray-900 dark:text-gray-100">Edit video banner</h1>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Update the featured video card. Replace video optional (max 30MB).</p>
            </div>
            <a href="{{ route('admin.video-banners.index') }}" class="inline-flex items-center gap-2 text-sm font-medium text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-200">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
                Back to Video Banners
            </a>
        </div>

        <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden">
                <form method="POST" action="{{ route('admin.video-banners.update', $videoBanner->id) }}" enctype="multipart/form-data" class="p-6 space-y-6" id="video-banner-form">
                    @csrf
                    @method('PUT')

                    <div>
                        <label for="video" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Replace video (optional)</label>
                        <input type="file" name="video" id="video" accept="video/mp4,video/quicktime,video/webm,video/ogg,video/x-m4v,.mp4,.mov,.webm,.ogg,.m4v"
                               class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-indigo-50 file:text-indigo-700"
                               @change="setVideo($event.target)">
                        <p class="mt-1.5 text-xs text-gray-500">Leave empty to keep current. Maximum <strong>30MB</strong>.</p>
                        <p id="video-size-hint" class="mt-1 text-xs text-gray-500"></p>
                        @error('video') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="title" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Title</label>
                        <input type="text" name="title" id="title" x-model="title"
                               class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        @error('title') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="badge_text" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Badge text</label>
                        <input type="text" name="badge_text" id="badge_text" x-model="badge"
                               class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        @error('badge_text') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="button_text" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Button text</label>
                        <input type="text" name="button_text" id="button_text" x-model="button"
                               class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        @error('button_text') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div class="flex items-center gap-3">
                        <input type="hidden" name="is_active" value="0">
                        <input type="checkbox" name="is_active" id="is_active" value="1" {{ old('is_active', $videoBanner->is_active) ? 'checked' : '' }}
                               class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                        <label for="is_active" class="text-sm font-medium text-gray-700 dark:text-gray-300">Active (visible on customer app)</label>
                    </div>

                    <div class="flex flex-wrap gap-3 pt-2 border-t border-gray-200 dark:border-gray-700">
                        <button type="submit" id="submit-btn"
                                class="inline-flex items-center gap-2 px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg shadow-sm">
                            Save changes
                        </button>
                        <a href="{{ route('admin.video-banners.index') }}"
                           class="inline-flex items-center gap-2 px-4 py-2.5 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 text-sm font-medium rounded-lg">
                            Cancel
                        </a>
                    </div>
                </form>
            </div>

            <div>
                <p class="mb-3 text-sm font-medium text-gray-700 dark:text-gray-300">App preview</p>
                <div class="relative aspect-[16/10] overflow-hidden rounded-2xl border border-gray-200 dark:border-gray-700 bg-slate-900 shadow-lg">
                    <template x-if="previewUrl">
                        <video :src="previewUrl" class="absolute inset-0 h-full w-full object-cover" muted loop autoplay playsinline></video>
                    </template>
                    <div class="pointer-events-none absolute inset-0 bg-gradient-to-t from-black/75 via-black/25 to-black/10"></div>
                    <div class="absolute top-4 left-4 z-10" x-show="badge">
                        <span class="inline-flex rounded-full bg-white/95 px-3 py-1 text-xs font-semibold text-slate-800 shadow-sm" x-text="badge"></span>
                    </div>
                    <div class="absolute inset-0 z-10 flex items-center justify-center pointer-events-none">
                        <span class="flex h-14 w-14 items-center justify-center rounded-full bg-white/95 text-indigo-700 shadow-lg">
                            <svg class="h-6 w-6 ml-0.5" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                        </span>
                    </div>
                    <div class="absolute inset-x-0 bottom-0 z-10 p-5">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.14em] text-white/70">Featured video</p>
                        <h3 class="mt-1 text-xl font-semibold text-white" x-text="title || 'Untitled video banner'"></h3>
                        <div class="mt-4" x-show="button">
                            <span class="inline-flex rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-md" x-text="button"></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.getElementById('video-banner-form').addEventListener('submit', function () {
            var btn = document.getElementById('submit-btn');
            btn.disabled = true;
            btn.textContent = 'Saving…';
        });
    </script>
</x-admin-layout>
