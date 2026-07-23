<x-admin-layout>
    <div class="space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-semibold text-gray-900 dark:text-gray-100">Edit video banner</h1>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Update featured video for the customer app. Replace video optional (max 30MB).</p>
            </div>
            <a href="{{ route('admin.video-banners.index') }}" class="inline-flex items-center gap-2 text-sm font-medium text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-200">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
                Back to Video Banners
            </a>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden">
            <form method="POST" action="{{ route('admin.video-banners.update', $videoBanner->id) }}" enctype="multipart/form-data" class="p-6 space-y-8" id="video-banner-form">
                @csrf
                @method('PUT')

                <div class="space-y-4">
                    <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100 uppercase tracking-wider border-b border-gray-200 dark:border-gray-700 pb-2">Current video</h2>
                    @if($videoBanner->video_url)
                        <video src="{{ $videoBanner->video_url }}" controls class="max-w-lg w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-black"></video>
                    @else
                        <p class="text-sm text-gray-500">No video on file.</p>
                    @endif
                </div>

                <div class="space-y-4">
                    <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100 uppercase tracking-wider border-b border-gray-200 dark:border-gray-700 pb-2">Replace video (optional)</h2>
                    <div>
                        <label for="video" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">New video file</label>
                        <input type="file" name="video" id="video" accept="video/mp4,video/quicktime,video/webm,video/ogg,video/x-m4v,.mp4,.mov,.webm,.ogg,.m4v"
                               class="block w-full text-sm text-gray-500 dark:text-gray-400 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-indigo-50 file:text-indigo-700 dark:file:bg-indigo-900/30 dark:file:text-indigo-300"
                               onchange="previewVideo(this)">
                        <p class="mt-1.5 text-xs text-gray-500 dark:text-gray-400">Leave empty to keep current. Maximum <strong>30MB</strong>.</p>
                        @error('video')
                            <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                        <div id="video-preview" class="mt-4 hidden">
                            <p class="text-xs font-medium text-gray-500 mb-2">New preview</p>
                            <video id="preview-video" controls class="max-w-lg w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-black"></video>
                            <p id="video-size-hint" class="mt-1 text-xs text-gray-500"></p>
                        </div>
                    </div>
                </div>

                <div class="space-y-4">
                    <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100 uppercase tracking-wider border-b border-gray-200 dark:border-gray-700 pb-2">Content</h2>
                    <div>
                        <label for="title" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Title (optional)</label>
                        <input type="text" name="title" id="title" value="{{ old('title', $videoBanner->title) }}"
                               class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        @error('title')
                            <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label for="badge_text" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Badge text (optional)</label>
                        <input type="text" name="badge_text" id="badge_text" value="{{ old('badge_text', $videoBanner->badge_text) }}"
                               class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 max-w-md">
                        @error('badge_text')
                            <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label for="button_text" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Button text (optional)</label>
                        <input type="text" name="button_text" id="button_text" value="{{ old('button_text', $videoBanner->button_text) }}"
                               class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 max-w-md">
                        @error('button_text')
                            <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="space-y-4">
                    <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100 uppercase tracking-wider border-b border-gray-200 dark:border-gray-700 pb-2">Display</h2>
                    <div class="flex items-center gap-3">
                        <input type="hidden" name="is_active" value="0">
                        <input type="checkbox" name="is_active" id="is_active" value="1" {{ old('is_active', $videoBanner->is_active) ? 'checked' : '' }}
                               class="rounded border-gray-300 dark:border-gray-600 text-indigo-600 focus:ring-indigo-500 dark:bg-gray-700">
                        <label for="is_active" class="text-sm font-medium text-gray-700 dark:text-gray-300">Active (visible on customer app)</label>
                    </div>
                </div>

                <div class="flex flex-wrap gap-3 pt-4 border-t border-gray-200 dark:border-gray-700">
                    <button type="submit" id="submit-btn"
                            class="inline-flex items-center gap-2 px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg shadow-sm transition-colors">
                        Save changes
                    </button>
                    <a href="{{ route('admin.video-banners.index') }}"
                       class="inline-flex items-center gap-2 px-4 py-2.5 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-300 text-sm font-medium rounded-lg">
                        Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>

    <script>
        var MAX_BYTES = 30 * 1024 * 1024;
        function previewVideo(input) {
            var hint = document.getElementById('video-size-hint');
            var box = document.getElementById('video-preview');
            var vid = document.getElementById('preview-video');
            var btn = document.getElementById('submit-btn');
            if (!input.files || !input.files[0]) return;
            var file = input.files[0];
            if (file.size > MAX_BYTES) {
                hint.textContent = 'File is larger than 30MB. Please choose a smaller video.';
                hint.className = 'mt-1 text-xs text-red-600';
                box.classList.remove('hidden');
                vid.removeAttribute('src');
                btn.disabled = true;
                input.value = '';
                return;
            }
            btn.disabled = false;
            hint.textContent = 'Size: ' + (file.size / (1024 * 1024)).toFixed(2) + ' MB';
            hint.className = 'mt-1 text-xs text-gray-500';
            vid.src = URL.createObjectURL(file);
            box.classList.remove('hidden');
        }
        document.getElementById('video-banner-form').addEventListener('submit', function () {
            var btn = document.getElementById('submit-btn');
            btn.disabled = true;
            btn.textContent = 'Saving…';
        });
    </script>
</x-admin-layout>
