<x-admin-layout>
    @php
        $selectedSupervisorIds = collect(old('supervisors', $area->supervisors->pluck('id')->toArray()))->map(fn ($id) => (int) $id)->all();
        $selectedTechnicianIds = collect(old('technicians', $area->technicians->pluck('id')->toArray()))->map(fn ($id) => (int) $id)->all();
    @endphp
    <div class="space-y-6">
        <h1 class="text-xl font-medium text-gray-900 dark:text-gray-100 mb-6">
            Edit Zone: {{ $area->name }}
        </h1>

        @if(session('success'))
            <div class="bg-green-100 dark:bg-green-900/30 border border-green-400 text-green-700 dark:text-green-300 px-4 py-3 rounded-lg">{{ session('success') }}</div>
        @endif

        <div class="bg-white dark:bg-gray-800 shadow rounded-xl p-6">
            <form method="POST" action="{{ route('admin.areas.update', $area->id) }}">
                @csrf
                @method('PUT')

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Name</label>
                        <input type="text" name="name" value="{{ old('name', $area->name) }}" required class="block w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        @error('name') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Country</label>
                        <input type="text" name="country" value="{{ old('country', $area->country ?? 'UAE') }}" placeholder="e.g. UAE" class="block w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        @error('country') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Location Label</label>
                        <input type="text" name="location" value="{{ old('location', $area->location) }}" placeholder="e.g. Abu Dhabi - Khalifa City" class="block w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        @error('location') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Priority (lower = preferred)</label>
                        <input type="number" name="priority" value="{{ old('priority', $area->priority ?? 100) }}" min="0" class="block w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        @error('priority') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Center Latitude</label>
                        <input type="text" name="latitude" value="{{ old('latitude', $area->latitude) }}" placeholder="24.453884" class="block w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        @error('latitude') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Center Longitude</label>
                        <input type="text" name="longitude" value="{{ old('longitude', $area->longitude) }}" placeholder="54.377344" class="block w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        @error('longitude') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Service Radius (km)</label>
                        <input type="number" step="0.1" min="0.1" name="service_radius_km" value="{{ old('service_radius_km', $area->service_radius_km ?? 30) }}" class="block w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        @error('service_radius_km') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div class="md:col-span-2">
                        <input type="hidden" name="is_active" value="0">
                        <label class="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                            <input type="checkbox" name="is_active" value="1" {{ old('is_active', $area->is_active ?? true) ? 'checked' : '' }} class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                            <span>Area is active for job assignment</span>
                        </label>
                    </div>

                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Description</label>
                        <textarea name="description" rows="3" class="block w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('description', $area->description) }}</textarea>
                        @error('description') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div class="relative js-multiselect" data-placeholder="Select supervisors">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Supervisors</label>
                        <button type="button" class="js-multiselect-trigger w-full rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 bg-white px-3 py-2 text-left text-sm text-gray-700 dark:text-gray-100 shadow-sm">
                            <span class="js-multiselect-label">Select supervisors</span>
                        </button>
                        <div class="js-multiselect-panel hidden absolute z-20 mt-2 w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-800 shadow-xl">
                            <div class="p-2 border-b border-gray-100 dark:border-gray-700">
                                <input type="text" class="js-multiselect-search w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 text-sm" placeholder="Search supervisor...">
                            </div>
                            <div class="max-h-56 overflow-auto p-2 space-y-1">
                                @foreach($supervisors as $s)
                                    <label class="js-multiselect-option flex items-center gap-2 px-2 py-1.5 rounded hover:bg-gray-50 dark:hover:bg-gray-700 text-sm text-gray-700 dark:text-gray-200" data-label="{{ strtolower($s->name.' '.$s->email) }}">
                                        <input type="checkbox" name="supervisors[]" value="{{ $s->id }}" {{ in_array((int) $s->id, $selectedSupervisorIds, true) ? 'checked' : '' }} class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                        <span>{{ $s->name }} <span class="text-xs text-gray-500">({{ $s->email }})</span></span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                        @error('supervisors.*') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div class="relative js-multiselect" data-placeholder="Select technicians">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Technicians</label>
                        <button type="button" class="js-multiselect-trigger w-full rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 bg-white px-3 py-2 text-left text-sm text-gray-700 dark:text-gray-100 shadow-sm">
                            <span class="js-multiselect-label">Select technicians</span>
                        </button>
                        <div class="js-multiselect-panel hidden absolute z-20 mt-2 w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-800 shadow-xl">
                            <div class="p-2 border-b border-gray-100 dark:border-gray-700">
                                <input type="text" class="js-multiselect-search w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 text-sm" placeholder="Search technician...">
                            </div>
                            <div class="max-h-56 overflow-auto p-2 space-y-1">
                                @foreach($technicians as $t)
                                    <label class="js-multiselect-option flex items-center gap-2 px-2 py-1.5 rounded hover:bg-gray-50 dark:hover:bg-gray-700 text-sm text-gray-700 dark:text-gray-200" data-label="{{ strtolower($t->name) }}">
                                        <input type="checkbox" name="technicians[]" value="{{ $t->id }}" {{ in_array((int) $t->id, $selectedTechnicianIds, true) ? 'checked' : '' }} class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                        <span>{{ $t->name }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                        @error('technicians.*') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="flex gap-3 mt-6 pt-6 border-t border-gray-200 dark:border-gray-700">
                    <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors">Update Zone</button>
                    <a href="{{ route('admin.zone-assignment.index') }}" class="px-4 py-2 bg-gray-200 dark:bg-gray-600 text-gray-700 dark:text-gray-200 rounded-lg hover:bg-gray-300 dark:hover:bg-gray-500 transition-colors">Cancel</a>
                </div>
            </form>
        </div>
    </div>
    <script>
        (function () {
            const wrappers = Array.from(document.querySelectorAll('.js-multiselect'));
            if (!wrappers.length) return;

            function refreshLabel(wrapper) {
                const checks = wrapper.querySelectorAll('input[type="checkbox"]:checked');
                const label = wrapper.querySelector('.js-multiselect-label');
                const placeholder = wrapper.dataset.placeholder || 'Select';
                if (!label) return;
                label.textContent = checks.length ? `${checks.length} selected` : placeholder;
            }

            wrappers.forEach((wrapper) => {
                const trigger = wrapper.querySelector('.js-multiselect-trigger');
                const panel = wrapper.querySelector('.js-multiselect-panel');
                const search = wrapper.querySelector('.js-multiselect-search');
                const options = Array.from(wrapper.querySelectorAll('.js-multiselect-option'));
                const checks = Array.from(wrapper.querySelectorAll('input[type="checkbox"]'));

                refreshLabel(wrapper);
                checks.forEach((cb) => cb.addEventListener('change', () => refreshLabel(wrapper)));

                trigger?.addEventListener('click', () => {
                    wrappers.forEach((w) => w.querySelector('.js-multiselect-panel')?.classList.add('hidden'));
                    panel?.classList.toggle('hidden');
                    if (panel && !panel.classList.contains('hidden')) search?.focus();
                });

                search?.addEventListener('input', () => {
                    const q = search.value.trim().toLowerCase();
                    options.forEach((opt) => {
                        const txt = opt.dataset.label || '';
                        opt.classList.toggle('hidden', q !== '' && !txt.includes(q));
                    });
                });
            });

            document.addEventListener('click', (e) => {
                wrappers.forEach((wrapper) => {
                    if (!wrapper.contains(e.target)) {
                        wrapper.querySelector('.js-multiselect-panel')?.classList.add('hidden');
                    }
                });
            });
        })();
    </script>
</x-admin-layout>


