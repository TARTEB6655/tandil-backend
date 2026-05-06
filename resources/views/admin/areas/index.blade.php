<x-admin-layout>
    <style>
        .ops-map-pin {
            width: 24px;
            height: 24px;
            background: radial-gradient(circle at 35% 30%, #ff8a8a 0%, #ef4444 45%, #dc2626 100%);
            border-radius: 50% 50% 50% 0;
            transform: rotate(-45deg);
            box-shadow: 0 3px 8px rgba(220, 38, 38, 0.45);
            position: relative;
            border: 1px solid rgba(255, 255, 255, 0.6);
        }
        .ops-map-pin::after {
            content: '';
            position: absolute;
            width: 10px;
            height: 10px;
            background: #fff;
            border-radius: 50%;
            top: 7px;
            left: 7px;
            box-shadow: 0 1px 4px rgba(15, 23, 42, 0.35);
        }
    </style>

    @php
        $operationalCount = $areas->where('is_active', true)->count();
        $areasWithCoordinates = $areas->filter(fn ($a) => $a->latitude !== null && $a->longitude !== null);
        $areasForMap = $areas->map(fn ($a) => [
            'id' => $a->id,
            'name' => $a->name,
            'location' => $a->location,
            'country' => $a->country,
            'is_active' => (bool) $a->is_active,
            'latitude' => $a->latitude,
            'longitude' => $a->longitude,
            'supervisors' => $a->supervisors->pluck('name')->values(),
        ])->values();
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

        <div class="grid grid-cols-3 gap-3">
            <div class="rounded-lg border border-slate-200 bg-white px-3 py-2 shadow-sm">
                <p class="text-[10px] uppercase tracking-wider text-slate-500">Total zones</p>
                <p class="mt-1 text-xl font-semibold text-slate-900 leading-none">{{ $areas->count() }}</p>
            </div>
            <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 shadow-sm">
                <p class="text-[10px] uppercase tracking-wider text-emerald-700">Operational</p>
                <p class="mt-1 text-xl font-semibold text-emerald-800 leading-none">{{ $operationalCount }}</p>
            </div>
            <div class="rounded-lg border border-slate-200 bg-white px-3 py-2 shadow-sm">
                <p class="text-[10px] uppercase tracking-wider text-slate-500">Pinned on map</p>
                <p class="mt-1 text-xl font-semibold text-slate-900 leading-none">{{ $areasWithCoordinates->count() }}</p>
            </div>
        </div>

        <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
            <div class="xl:col-span-2 rounded-xl border border-slate-200 bg-white overflow-hidden shadow-sm">
                <div class="px-5 py-4 border-b border-slate-200 flex items-center justify-between">
                    <div>
                        <h2 class="text-sm font-semibold text-slate-800">UAE Operational Map</h2>
                        <p class="text-xs text-slate-500 mt-1">Only active zones with valid coordinates are pinned on map.</p>
                    </div>
                    <span class="inline-flex items-center rounded-full bg-indigo-50 text-indigo-700 px-3 py-1 text-xs font-medium">
                        Live Pins
                    </span>
                </div>
                <div id="uae-operational-map" class="w-full" style="height: 440px; min-height: 440px;"></div>
                <div id="uae-map-empty-state" class="hidden border-t border-slate-200 px-5 py-3 text-xs text-amber-700 bg-amber-50">
                    No active zones with coordinates found. Add latitude/longitude and enable an area to see map pins.
                </div>
            </div>

            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-sm font-semibold text-slate-800">Quick Operational Toggles</h3>
                    <span class="text-xs text-slate-500">{{ $areas->count() }} zones</span>
                </div>
                <div class="space-y-3 max-h-[440px] overflow-y-auto pr-1">
                    @forelse($areas as $area)
                        <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-3">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="text-sm font-semibold text-slate-900">{{ $area->name }}</p>
                                    <p class="text-xs text-slate-500">{{ $area->country ?: 'UAE' }}{{ $area->location ? ' · '.$area->location : '' }}</p>
                                </div>
                                <button type="button"
                                    class="js-area-toggle relative inline-flex h-8 w-[62px] items-center rounded-full transition-colors duration-200 ease-out focus:outline-none focus:ring-2 focus:ring-emerald-400 {{ $area->is_active ? 'bg-emerald-500' : 'bg-slate-300' }}"
                                    data-area-id="{{ $area->id }}"
                                    data-toggle-url="{{ route('admin.areas.toggle-active', $area->id) }}"
                                    data-is-active="{{ $area->is_active ? '1' : '0' }}"
                                    title="{{ $area->is_active ? 'Disable area' : 'Enable area' }}">
                                    <span class="js-area-toggle-knob inline-block h-7 w-7 transform rounded-full bg-white shadow-[0_2px_6px_rgba(15,23,42,0.35)] transition duration-200 ease-out {{ $area->is_active ? 'translate-x-8' : 'translate-x-[2px]' }}"></span>
                                </button>
                            </div>
                            <div class="mt-2 flex items-center justify-between">
                                <span class="js-area-toggle-status inline-flex items-center rounded-full px-2 py-1 text-xs font-medium {{ $area->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-600' }}">
                                    {{ $area->is_active ? 'Enabled' : 'Disabled' }}
                                </span>
                                <a href="{{ route('admin.areas.edit', $area->id) }}" class="text-xs font-medium text-indigo-600 hover:text-indigo-800">Manage</a>
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-slate-500">{{ __('admin.no_areas_found') }}</p>
                    @endforelse
                </div>
            </div>
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
                                        class="js-area-toggle relative inline-flex h-8 w-[62px] items-center rounded-full transition-colors duration-200 ease-out focus:outline-none focus:ring-2 focus:ring-emerald-400 {{ $area->is_active ? 'bg-emerald-500' : 'bg-slate-300' }}"
                                        data-area-id="{{ $area->id }}"
                                        data-toggle-url="{{ route('admin.areas.toggle-active', $area->id) }}"
                                        data-is-active="{{ $area->is_active ? '1' : '0' }}"
                                        title="{{ $area->is_active ? 'Disable area' : 'Enable area' }}">
                                        <span class="js-area-toggle-knob inline-block h-7 w-7 transform rounded-full bg-white shadow-[0_2px_6px_rgba(15,23,42,0.35)] transition duration-200 ease-out {{ $area->is_active ? 'translate-x-8' : 'translate-x-[2px]' }}"></span>
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

            const uaeBounds = L.latLngBounds(
                L.latLng(22.4, 51.3),
                L.latLng(26.7, 56.9)
            );

            const map = L.map(mapEl, {
                zoomControl: true,
                minZoom: 6,
                maxZoom: 12,
                maxBounds: uaeBounds,
                maxBoundsViscosity: 1.0,
            }).setView([24.3, 54.3], 7);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 18,
                attribution: '&copy; OpenStreetMap contributors'
            }).addTo(map);

            const areas = @json($areasForMap);
            const customPin = L.divIcon({
                className: 'ops-map-pin-wrapper',
                html: '<span class="ops-map-pin"></span>',
                iconSize: [24, 24],
                iconAnchor: [12, 22],
                popupAnchor: [0, -20],
            });

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
                const country = String(area.country || '').trim().toLowerCase();
                if (country !== '' && country !== 'uae' && country !== 'united arab emirates') return;
                const lat = Number(area.latitude);
                const lng = Number(area.longitude);
                if (Number.isNaN(lat) || Number.isNaN(lng)) return;
                if (!uaeBounds.contains([lat, lng])) return;
                if (markers[area.id]) return;
                markers[area.id] = L.marker([lat, lng], { icon: customPin }).addTo(map).bindPopup(pinPopup(area));
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
                const country = String(area.country || '').trim().toLowerCase();
                if (country !== '' && country !== 'uae' && country !== 'united arab emirates') return;
                if (!uaeBounds.contains([lat, lng])) return;

                addMarker(area);
                bounds.push([lat, lng]);
            });

            if (bounds.length > 0) {
                map.fitBounds(L.latLngBounds(bounds).pad(0.08), { padding: [24, 24], maxZoom: 10 });
            } else {
                const emptyState = document.getElementById('uae-map-empty-state');
                if (emptyState) emptyState.classList.remove('hidden');
                map.fitBounds(uaeBounds, { padding: [16, 16] });
            }
            setTimeout(() => map.invalidateSize(), 120);

            const csrf = @json(csrf_token());
            const toggleButtons = Array.from(document.querySelectorAll('.js-area-toggle'));

            function setButtonState(button, isActive) {
                const knob = button.querySelector('.js-area-toggle-knob');
                const statusEl = button.closest('[class*="rounded-lg"]')?.querySelector('.js-area-toggle-status');
                button.dataset.isActive = isActive ? '1' : '0';
                button.classList.toggle('bg-emerald-500', isActive);
                button.classList.toggle('bg-slate-300', !isActive);
                if (knob) {
                    knob.classList.toggle('translate-x-8', isActive);
                    knob.classList.toggle('translate-x-[2px]', !isActive);
                }
                button.title = isActive ? 'Disable area' : 'Enable area';
                if (statusEl) {
                    statusEl.textContent = isActive ? 'Enabled' : 'Disabled';
                    statusEl.classList.toggle('bg-emerald-100', isActive);
                    statusEl.classList.toggle('text-emerald-700', isActive);
                    statusEl.classList.toggle('bg-slate-200', !isActive);
                    statusEl.classList.toggle('text-slate-600', !isActive);
                }
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
