<x-admin-layout>
    <style>
        .ops-map-pin {
            display: block;
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
        .ops-switch {
            position: relative;
            display: inline-flex;
            align-items: center;
            width: 46px;
            height: 26px;
            border-radius: 9999px;
            border: 1px solid #d1d5db;
            background: #d1d5db;
            transition: background-color 180ms ease, border-color 180ms ease, box-shadow 180ms ease, transform 120ms ease;
            cursor: pointer;
        }
        .ops-switch:hover {
            box-shadow: 0 2px 6px rgba(15, 23, 42, 0.14);
        }
        .ops-switch:active {
            transform: scale(0.98);
        }
        .ops-switch:focus-visible {
            outline: none;
            box-shadow: 0 0 0 3px rgba(20, 184, 166, 0.25);
        }
        .ops-switch[data-active="1"] {
            background: #0ea5a3;
            border-color: #0b8b89;
        }
        .ops-switch[data-loading="1"] {
            opacity: 0.92;
        }
        .ops-switch-knob {
            position: absolute;
            top: 2px;
            left: 2px;
            width: 20px;
            height: 20px;
            border-radius: 9999px;
            background: #ffffff;
            border: 1px solid rgba(203, 213, 225, 0.95);
            box-shadow: 0 1px 3px rgba(15, 23, 42, 0.22);
            transition: left 180ms ease;
        }
        .ops-switch[data-active="1"] .ops-switch-knob {
            left: 23px;
        }
    </style>

    @php
        $uaeCityCenters = [
            'abu dhabi' => ['lat' => 24.4539, 'lng' => 54.3773],
            'dubai' => ['lat' => 25.2048, 'lng' => 55.2708],
            'sharjah' => ['lat' => 25.3463, 'lng' => 55.4209],
            'ajman' => ['lat' => 25.4052, 'lng' => 55.5136],
            'umm al quwain' => ['lat' => 25.5647, 'lng' => 55.5552],
            'ras al khaimah' => ['lat' => 25.7895, 'lng' => 55.9432],
            'fujairah' => ['lat' => 25.1288, 'lng' => 56.3265],
            'al ain' => ['lat' => 24.2075, 'lng' => 55.7447],
        ];

        $resolveUaeCenter = function (?string $name, ?string $location) use ($uaeCityCenters): ?array {
            $haystack = strtolower(trim(($name ?? '').' '.($location ?? '')));
            if ($haystack === '') {
                return null;
            }
            foreach ($uaeCityCenters as $city => $coords) {
                if (str_contains($haystack, $city)) {
                    return $coords;
                }
            }

            return null;
        };

        $areasForMap = isset($areasForMap) ? $areasForMap : collect($areas);
        $operationalCount = $areasForMap->where('is_active', true)->count();
        $areasWithCoordinates = $areasForMap->filter(function ($a) use ($resolveUaeCenter) {
            if ($a->latitude !== null && $a->longitude !== null) {
                return true;
            }
            return $resolveUaeCenter($a->name, $a->location) !== null;
        });
        $areasForMapData = $areasForMap->map(fn ($a) => [
            'id' => $a->id,
            'name' => $a->name,
            'location' => $a->location,
            'country' => $a->country,
            'is_active' => (bool) $a->is_active,
            'latitude' => $a->latitude,
            'longitude' => $a->longitude,
            'fallback_center' => $resolveUaeCenter($a->name, $a->location),
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

        <div class="flex items-stretch gap-3 overflow-x-auto" style="display:flex !important; flex-direction:row !important; flex-wrap:nowrap !important; gap:12px; align-items:stretch;">
            <div class="rounded-lg border border-slate-200 bg-white px-3 py-2 shadow-sm shrink-0" style="display:block; flex:1 1 0; min-width: 180px;">
                <p class="text-[10px] uppercase tracking-wider text-slate-500">Total zones</p>
                <p id="ops-total-zones" class="mt-1 text-xl font-semibold text-slate-900 leading-none">{{ method_exists($areas, 'total') ? $areas->total() : $areas->count() }}</p>
            </div>
            <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 shadow-sm shrink-0" style="display:block; flex:1 1 0; min-width: 180px;">
                <p class="text-[10px] uppercase tracking-wider text-emerald-700">Operational</p>
                <p id="ops-operational-zones" class="mt-1 text-xl font-semibold text-emerald-800 leading-none">{{ $operationalCount }}</p>
            </div>
            <div class="rounded-lg border border-slate-200 bg-white px-3 py-2 shadow-sm shrink-0" style="display:block; flex:1 1 0; min-width: 180px;">
                <p class="text-[10px] uppercase tracking-wider text-slate-500">Pinned on map</p>
                <p id="ops-pinned-zones" class="mt-1 text-xl font-semibold text-slate-900 leading-none">{{ $areasWithCoordinates->count() }}</p>
            </div>
        </div>

        <div class="rounded-xl border border-slate-200 bg-white overflow-hidden shadow-sm">
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
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Operational Toggle</th>
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
                                <td class="px-4 py-3">
                                    <button type="button"
                                        class="js-area-toggle ops-switch"
                                        data-area-id="{{ $area->id }}"
                                        data-toggle-url="{{ route('admin.areas.toggle-active', $area->id) }}"
                                        data-area-name="{{ $area->name }}"
                                        data-area-location="{{ $area->location }}"
                                        data-area-country="{{ $area->country ?: 'UAE' }}"
                                        data-is-active="{{ $area->is_active ? '1' : '0' }}"
                                        data-active="{{ $area->is_active ? '1' : '0' }}"
                                        role="switch"
                                        aria-checked="{{ $area->is_active ? 'true' : 'false' }}"
                                        title="{{ $area->is_active ? 'Disable area' : 'Enable area' }}">
                                        <span class="js-area-toggle-knob ops-switch-knob"></span>
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-8 text-center text-sm text-slate-500">{{ __('admin.no_areas_found') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if(method_exists($areas, 'links'))
            <div class="mt-4 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <p class="text-xs text-slate-500">
                    Showing {{ $areas->firstItem() ?? 0 }}-{{ $areas->lastItem() ?? 0 }} of {{ $areas->total() }} cities/zones
                </p>
                <div>
                    {{ $areas->links() }}
                </div>
            </div>
        @endif
        <div class="rounded-xl border border-slate-200 bg-white p-4">
            <form method="GET" action="{{ route('admin.areas.index') }}" class="grid grid-cols-1 md:grid-cols-5 gap-3">
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Search city / zone / country..." class="md:col-span-2 rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500">
                <select name="per_page" class="rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500">
                    @foreach([10, 25, 50, 100] as $size)
                        <option value="{{ $size }}" {{ (int) request('per_page', 25) === $size ? 'selected' : '' }}>{{ $size }} / page</option>
                    @endforeach
                </select>
                <button type="submit" class="rounded-lg bg-indigo-600 text-white px-4 py-2 hover:bg-indigo-700">Apply</button>
                <a href="{{ route('admin.areas.index') }}" class="rounded-lg bg-slate-100 text-slate-700 px-4 py-2 text-center hover:bg-slate-200">Clear</a>
            </form>
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

            let areas = @json($areasForMapData);
            const totalEl = document.getElementById('ops-total-zones');
            const operationalEl = document.getElementById('ops-operational-zones');
            const pinnedEl = document.getElementById('ops-pinned-zones');
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

            function resolveFallbackCenter(area) {
                if (area && area.fallback_center && !Number.isNaN(Number(area.fallback_center.lat)) && !Number.isNaN(Number(area.fallback_center.lng))) {
                    return {
                        lat: Number(area.fallback_center.lat),
                        lng: Number(area.fallback_center.lng),
                    };
                }
                const hay = String(`${area?.name || ''} ${area?.location || ''}`).trim().toLowerCase();
                if (!hay) return null;
                const cityCenters = [
                    ['abu dhabi', 24.4539, 54.3773],
                    ['abu dhabi city', 24.4539, 54.3773],
                    ['madinat zayed', 23.6548, 53.7052],
                    ['ruwais', 24.1103, 52.7306],
                    ['ghayathi', 23.8422, 52.7844],
                    ['liwa', 23.1340, 53.7694],
                    ['liwa oasis', 23.1340, 53.7694],
                    ['al shahama', 24.5242, 54.7153],
                    ['baniyas', 24.3098, 54.6294],
                    ['mussafah', 24.3579, 54.5067],
                    ['dubai', 25.2048, 55.2708],
                    ['dubai city', 25.2048, 55.2708],
                    ['jebel ali', 24.9857, 55.0657],
                    ['hatta', 24.8095, 56.1225],
                    ['sharjah', 25.3463, 55.4209],
                    ['sharjah city', 25.3463, 55.4209],
                    ['khor fakkan', 25.3397, 56.3576],
                    ['kalba', 24.9982, 56.2721],
                    ['dibba al-hisn', 25.6209, 56.2724],
                    ['al dhaid', 25.2881, 55.8816],
                    ['ajman', 25.4052, 55.5136],
                    ['ajman city', 25.4052, 55.5136],
                    ['masfout', 24.8383, 56.0697],
                    ['manama', 25.3257, 56.0026],
                    ['umm al quwain', 25.5647, 55.5552],
                    ['umm al quwain city', 25.5647, 55.5552],
                    ['falaj al mualla', 25.3860, 55.7741],
                    ['ras al khaimah', 25.7895, 55.9432],
                    ['ras al khaimah city', 25.7895, 55.9432],
                    ['al jazirah al hamra', 25.6913, 55.7807],
                    ['digdaga', 25.7048, 55.9893],
                    ['khatt', 25.8070, 56.0475],
                    ['fujairah', 25.1288, 56.3265],
                    ['fujairah city', 25.1288, 56.3265],
                    ['dibba al-fujairah', 25.5925, 56.2618],
                    ['mirbah', 25.4780, 56.3587],
                    ['qidfa', 25.3940, 56.3440],
                    ['al ain', 24.2075, 55.7447],
                    ['dibba', 25.5925, 56.2618],
                ];
                for (const [city, lat, lng] of cityCenters) {
                    if (hay.includes(city)) return { lat, lng };
                }
                return null;
            }

            function addMarker(area) {
                if (!area || !area.is_active) return;
                const country = String(area.country || '').trim().toLowerCase();
                if (country !== '' && country !== 'uae' && country !== 'united arab emirates') return;
                let lat = Number(area.latitude);
                let lng = Number(area.longitude);
                if (Number.isNaN(lat) || Number.isNaN(lng)) {
                    const fallback = resolveFallbackCenter(area);
                    if (fallback) {
                        lat = fallback.lat;
                        lng = fallback.lng;
                    }
                }
                if (Number.isNaN(lat) || Number.isNaN(lng)) {
                    lat = 24.4539;
                    lng = 54.3773;
                }
                if (!uaeBounds.contains([lat, lng])) return;
                if (markers[area.id]) return;
                markers[area.id] = L.marker([lat, lng], { icon: customPin }).addTo(map).bindPopup(pinPopup(area));
            }

            function removeMarker(areaId) {
                if (!markers[areaId]) return;
                map.removeLayer(markers[areaId]);
                delete markers[areaId];
            }

            function clearMarkers() {
                Object.keys(markers).forEach((k) => {
                    if (markers[k]) {
                        map.removeLayer(markers[k]);
                    }
                    delete markers[k];
                });
            }

            function renderPins(sourceAreas) {
                clearMarkers();
                const localBounds = [];
                sourceAreas.forEach((area) => {
                    if (!area || !area.is_active) return;
                    const country = String(area.country || '').trim().toLowerCase();
                    if (country !== '' && country !== 'uae' && country !== 'united arab emirates') return;
                    let lat = Number(area.latitude);
                    let lng = Number(area.longitude);
                    if (Number.isNaN(lat) || Number.isNaN(lng)) {
                        const fallback = resolveFallbackCenter(area);
                        if (fallback) {
                            lat = fallback.lat;
                            lng = fallback.lng;
                        }
                    }
                    if (Number.isNaN(lat) || Number.isNaN(lng)) {
                        lat = 24.4539;
                        lng = 54.3773;
                    }
                    if (!uaeBounds.contains([lat, lng])) return;
                    addMarker({ ...area, latitude: lat, longitude: lng });
                    localBounds.push([lat, lng]);
                });

                if (localBounds.length > 0) {
                    map.fitBounds(L.latLngBounds(localBounds).pad(0.08), { padding: [24, 24], maxZoom: 10 });
                    document.getElementById('uae-map-empty-state')?.classList.add('hidden');
                } else {
                    document.getElementById('uae-map-empty-state')?.classList.remove('hidden');
                    map.fitBounds(uaeBounds, { padding: [16, 16] });
                }
            }

            async function refreshOperationalMeta() {
                try {
                    const params = new URLSearchParams({
                        country: 'UAE',
                        per_page: '10',
                        page: '1',
                        search: '{{ request('search') }}'
                    });
                    const res = await fetch(`/api/admin/operational-areas?${params.toString()}`, {
                        headers: { 'Accept': 'application/json' },
                        credentials: 'same-origin',
                    });
                    if (!res.ok) return;
                    const payload = await res.json();
                    if (!payload?.success) return;
                    const summary = payload?.data?.summary || {};
                    if (totalEl) totalEl.textContent = String(summary.total_zones ?? totalEl.textContent);
                    if (operationalEl) operationalEl.textContent = String(summary.operational_zones ?? operationalEl.textContent);
                    if (pinnedEl) pinnedEl.textContent = String(summary.pinned_on_map ?? pinnedEl.textContent);

                } catch (e) {
                    // non-blocking metadata refresh
                }
            }

            renderPins(areas);
            setTimeout(() => map.invalidateSize(), 120);
            refreshOperationalMeta();

            const csrf = @json(csrf_token());
            const toggleButtons = Array.from(document.querySelectorAll('.js-area-toggle'));

            function setButtonState(button, isActive) {
                const statusEl = button.closest('[class*="rounded-lg"]')?.querySelector('.js-area-toggle-status');
                button.dataset.isActive = isActive ? '1' : '0';
                button.dataset.active = isActive ? '1' : '0';
                button.setAttribute('aria-checked', isActive ? 'true' : 'false');
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
                setButtonState(button, button.dataset.isActive === '1');
                button.addEventListener('click', async () => {
                    if (button.dataset.loading === '1') return;
                    const prevState = button.dataset.isActive === '1';
                    const nextState = !prevState;

                    // Optimistic UI: animate immediately, then sync with server.
                    setButtonState(button, nextState);
                    button.dataset.loading = '1';
                    button.style.pointerEvents = 'none';

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
                            addMarker({
                                ...area,
                                name: area.name || button.dataset.areaName || '',
                                location: area.location || button.dataset.areaLocation || '',
                                country: area.country || button.dataset.areaCountry || 'UAE',
                                is_active: true,
                            });
                        } else {
                            removeMarker(String(area.id));
                            removeMarker(Number(area.id));
                        }
                        refreshOperationalMeta();
                    } catch (err) {
                        // Revert optimistic state on failure.
                        setButtonState(button, prevState);
                        alert(err.message || 'Unable to update area status.');
                    } finally {
                        button.dataset.loading = '0';
                        button.style.pointerEvents = '';
                    }
                });
            });
        })();
    </script>
</x-admin-layout>
