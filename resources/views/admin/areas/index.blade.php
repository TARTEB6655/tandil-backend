<x-admin-layout>
    @php
        $operationalCount = $areas->where('is_active', true)->count();
        $areasWithCoordinates = $areas->filter(fn ($a) => $a->latitude !== null && $a->longitude !== null);
    @endphp

    <div class="space-y-6">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <h1 class="text-2xl font-semibold text-gray-900">UAE Operational Areas</h1>
                <p class="text-sm text-gray-600 mt-1">Enable/disable cities and zones from one screen, with live map pins for active operations.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('admin.zone-assignment.index') }}" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-lg hover:bg-gray-300">{{ __('admin.zone_assignment') }}</a>
                <a href="{{ route('admin.areas.create') }}" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700">{{ __('admin.new_zone') }}</a>
            </div>
        </div>

        @if(session('success'))
            <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-emerald-800 text-sm">
                {{ session('success') }}
            </div>
        @endif

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="rounded-xl border border-slate-200 bg-white p-4">
                <p class="text-xs uppercase tracking-wider text-slate-500">Total zones</p>
                <p class="text-2xl font-semibold text-slate-900">{{ $areas->count() }}</p>
            </div>
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4">
                <p class="text-xs uppercase tracking-wider text-emerald-700">Operational</p>
                <p class="text-2xl font-semibold text-emerald-800">{{ $operationalCount }}</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-4">
                <p class="text-xs uppercase tracking-wider text-slate-500">Pinned on map</p>
                <p class="text-2xl font-semibold text-slate-900">{{ $areasWithCoordinates->count() }}</p>
            </div>
        </div>

        <div class="rounded-xl border border-slate-200 bg-white overflow-hidden">
            <div class="px-4 py-3 border-b border-slate-200">
                <h2 class="text-sm font-semibold text-slate-800">UAE Map (Active operational pins)</h2>
                <p class="text-xs text-slate-500 mt-1">Pins are visible only for enabled zones with latitude/longitude.</p>
            </div>
            <div id="uae-operational-map" class="h-[420px] w-full"></div>
        </div>

        <div class="rounded-xl border border-slate-200 bg-white p-4">
            <form method="GET" action="{{ route('admin.areas.index') }}" class="grid grid-cols-1 md:grid-cols-5 gap-3">
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Search city / zone / country..." class="md:col-span-3 rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500">
                <button type="submit" class="rounded-lg bg-indigo-600 text-white px-4 py-2 hover:bg-indigo-700">Search</button>
                <a href="{{ route('admin.areas.index') }}" class="rounded-lg bg-slate-100 text-slate-700 px-4 py-2 text-center hover:bg-slate-200">Clear</a>
            </form>
        </div>

        <div class="rounded-xl border border-slate-200 bg-white overflow-hidden">
            <div class="px-4 py-3 border-b border-slate-200">
                <h2 class="text-sm font-semibold text-slate-800">Operational Area List</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">City / Zone</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Country</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Assigned Supervisors</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Priority</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Coordinates</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Operational Toggle</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($areas as $area)
                            <tr>
                                <td class="px-4 py-3">
                                    <p class="text-sm font-semibold text-slate-900">{{ $area->name }}</p>
                                    <p class="text-xs text-slate-500">{{ $area->location ?: 'No location label' }}</p>
                                </td>
                                <td class="px-4 py-3 text-sm text-slate-700">{{ $area->country ?: 'UAE' }}</td>
                                <td class="px-4 py-3">
                                    @if($area->supervisors->isEmpty())
                                        <span class="text-xs text-amber-700 bg-amber-50 px-2 py-1 rounded">No supervisor</span>
                                    @else
                                        <div class="flex flex-wrap gap-1">
                                            @foreach($area->supervisors as $sup)
                                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs bg-indigo-50 text-indigo-700">{{ $sup->name }}</span>
                                            @endforeach
                                        </div>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-sm text-slate-700">{{ (int) ($area->priority ?? 0) }}</td>
                                <td class="px-4 py-3 text-xs text-slate-600">
                                    @if($area->latitude !== null && $area->longitude !== null)
                                        {{ number_format((float) $area->latitude, 6) }}, {{ number_format((float) $area->longitude, 6) }}
                                    @else
                                        <span class="text-amber-700">Missing coordinates</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    <button type="button"
                                        class="js-area-toggle relative inline-flex h-7 w-14 items-center rounded-full transition-colors {{ $area->is_active ? 'bg-emerald-500' : 'bg-slate-300' }}"
                                        data-area-id="{{ $area->id }}"
                                        data-toggle-url="{{ route('admin.areas.toggle-active', $area->id) }}"
                                        data-is-active="{{ $area->is_active ? '1' : '0' }}"
                                        title="{{ $area->is_active ? 'Disable area' : 'Enable area' }}">
                                        <span class="js-area-toggle-knob inline-block h-6 w-6 transform rounded-full bg-white transition {{ $area->is_active ? 'translate-x-7' : 'translate-x-1' }}"></span>
                                    </button>
                                </td>
                                <td class="px-4 py-3 text-sm">
                                    <a href="{{ route('admin.areas.edit', $area->id) }}" class="text-indigo-600 hover:text-indigo-800 mr-3">Edit</a>
                                    <a href="{{ route('admin.areas.show', $area->id) }}" class="text-slate-600 hover:text-slate-800">View</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-8 text-center text-sm text-slate-500">{{ __('admin.no_areas_found') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        (function () {
            const mapEl = document.getElementById('uae-operational-map');
            if (!mapEl) return;

            const map = L.map(mapEl, { zoomControl: true }).setView([24.3, 54.3], 7);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 18,
                attribution: '&copy; OpenStreetMap contributors'
            }).addTo(map);

            const areas = @json(
                $areas->map(fn ($a) => [
                    'id' => $a->id,
                    'name' => $a->name,
                    'location' => $a->location,
                    'country' => $a->country,
                    'is_active' => (bool) $a->is_active,
                    'latitude' => $a->latitude,
                    'longitude' => $a->longitude,
                    'supervisors' => $a->supervisors->pluck('name')->values(),
                ])->values()
            );

            const bounds = [];
            const markers = {};

            function pinPopup(area) {
                return `
                    <div class="text-sm">
                        <div class="font-semibold">${area.name}</div>
                        <div>${area.location || ''}</div>
                        <div>${area.country || 'UAE'}</div>
                        <div class="mt-1 text-xs text-slate-600">Supervisors: ${(area.supervisors || []).join(', ') || 'None'}</div>
                    </div>
                `;
            }

            function addMarker(area) {
                if (area.latitude === null || area.longitude === null) return;
                const lat = Number(area.latitude);
                const lng = Number(area.longitude);
                if (Number.isNaN(lat) || Number.isNaN(lng)) return;
                if (markers[area.id]) return;
                markers[area.id] = L.marker([lat, lng]).addTo(map).bindPopup(pinPopup(area));
            }

            function removeMarker(areaId) {
                if (!markers[areaId]) return;
                map.removeLayer(markers[areaId]);
                delete markers[areaId];
            }

            areas.forEach((area) => {
                if (!area.is_active) return;
                if (area.latitude === null || area.longitude === null) return;

                const lat = Number(area.latitude);
                const lng = Number(area.longitude);
                if (Number.isNaN(lat) || Number.isNaN(lng)) return;

                addMarker(area);
                bounds.push([lat, lng]);
            });

            if (bounds.length > 0) {
                map.fitBounds(bounds, { padding: [30, 30], maxZoom: 11 });
            }

            const csrf = @json(csrf_token());
            const toggleButtons = Array.from(document.querySelectorAll('.js-area-toggle'));

            function setButtonState(button, isActive) {
                const knob = button.querySelector('.js-area-toggle-knob');
                button.dataset.isActive = isActive ? '1' : '0';
                button.classList.toggle('bg-emerald-500', isActive);
                button.classList.toggle('bg-slate-300', !isActive);
                if (knob) {
                    knob.classList.toggle('translate-x-7', isActive);
                    knob.classList.toggle('translate-x-1', !isActive);
                }
                button.title = isActive ? 'Disable area' : 'Enable area';
            }

            toggleButtons.forEach((button) => {
                button.addEventListener('click', async () => {
                    if (button.dataset.loading === '1') return;
                    button.dataset.loading = '1';
                    button.classList.add('opacity-70');
                    button.disabled = true;

                    try {
                        const res = await fetch(button.dataset.toggleUrl, {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': csrf,
                                'Accept': 'application/json',
                            },
                        });
                        const payload = await res.json();
                        if (!res.ok || !payload.success) {
                            throw new Error(payload.message || 'Toggle failed');
                        }

                        const area = payload.data || {};
                        const isActive = !!area.is_active;
                        setButtonState(button, isActive);

                        if (isActive) {
                            addMarker(area);
                        } else {
                            removeMarker(String(area.id));
                            removeMarker(Number(area.id));
                        }
                    } catch (err) {
                        alert(err.message || 'Unable to update area status.');
                    } finally {
                        button.dataset.loading = '0';
                        button.classList.remove('opacity-70');
                        button.disabled = false;
                    }
                });
            });
        })();
    </script>
</x-admin-layout>
