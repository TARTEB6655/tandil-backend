<x-client-layout>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="" />

    <div class="max-w-4xl">
        <div class="mb-6 flex items-center justify-between gap-3">
            <div>
                <h1 class="text-xl font-medium text-gray-900">Create Job</h1>
                <p class="mt-1 text-sm text-gray-500">Add location details and schedule a new visit.</p>
            </div>
            <a href="{{ route('client.visits.index') }}" class="px-3 py-2 rounded-md bg-gray-100 text-gray-700 hover:bg-gray-200">Back to Visits</a>
        </div>

        @if($errors->any())
            <div class="mb-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                Please fix the highlighted fields and try again.
            </div>
        @endif

        <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
            <form method="POST" action="{{ route('client.visits.store') }}" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                @csrf

                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Subscription</label>
                    <select name="subscription_id" required class="w-full rounded-md border-gray-300">
                        <option value="">Select subscription</option>
                        @foreach($subscriptions as $subscription)
                            <option value="{{ $subscription->id }}" {{ (string) old('subscription_id') === (string) $subscription->id ? 'selected' : '' }}>
                                #{{ $subscription->id }} - {{ ucfirst(str_replace('_', ' ', $subscription->plan ?? 'Subscription')) }} ({{ ucfirst($subscription->payment_status ?? 'pending') }})
                            </option>
                        @endforeach
                    </select>
                    @error('subscription_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Scheduled Date</label>
                    <input type="date" name="scheduled_date" value="{{ old('scheduled_date') }}" required class="w-full rounded-md border-gray-300">
                    @error('scheduled_date') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Price (optional)</label>
                    <input type="number" step="0.01" min="0" name="price" value="{{ old('price') }}" class="w-full rounded-md border-gray-300" placeholder="289.99">
                    @error('price') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Area (optional)</label>
                    <select name="area_id" class="w-full rounded-md border-gray-300">
                        <option value="">Auto resolve from location</option>
                        @foreach($areas as $area)
                            <option value="{{ $area->id }}" {{ (string) old('area_id') === (string) $area->id ? 'selected' : '' }}>
                                {{ $area->name }}{{ $area->location ? ' - ' . $area->location : '' }}
                            </option>
                        @endforeach
                    </select>
                    @error('area_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Country</label>
                    <input type="text" name="country" value="{{ old('country', 'UAE') }}" class="w-full rounded-md border-gray-300" placeholder="UAE">
                    @error('country') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">City</label>
                    <input id="city" type="text" name="city" value="{{ old('city') }}" class="w-full rounded-md border-gray-300" placeholder="Abu Dhabi">
                    @error('city') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">State/Emirate</label>
                    <input id="state" type="text" name="state" value="{{ old('state') }}" class="w-full rounded-md border-gray-300" placeholder="Abu Dhabi">
                    @error('state') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div class="md:col-span-2">
                    <div class="flex flex-wrap items-center gap-2 mb-2">
                        <button type="button" id="use-current-location" class="px-3 py-2 rounded-md bg-indigo-100 text-indigo-700 hover:bg-indigo-200 text-sm">
                            Use My Current Location (GPS)
                        </button>
                        <span id="location-help" class="text-xs text-gray-500">You can also tap on the map to set location.</span>
                    </div>
                    <div class="rounded-lg border border-gray-200 p-2 bg-gray-50">
                        <div id="location-map" class="w-full rounded-lg border border-gray-300 bg-white" style="height: 320px;"></div>
                        <div id="location-map-fallback" class="hidden mt-2 rounded-lg overflow-hidden border border-gray-300">
                            <iframe
                                title="Map fallback"
                                class="w-full h-72"
                                loading="lazy"
                                referrerpolicy="no-referrer-when-downgrade"
                                src="https://www.openstreetmap.org/export/embed.html?bbox=51.3%2C22.5%2C56.7%2C26.8&layer=mapnik"
                            ></iframe>
                        </div>
                    </div>
                    <div class="mt-3">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Selected Address (auto)</label>
                        <input id="selected-address" type="text" class="w-full rounded-md border-gray-300 bg-gray-50" placeholder="Pick from map or GPS to auto-fill address" readonly>
                    </div>

                    <details class="mt-3">
                        <summary class="cursor-pointer text-sm text-gray-600 hover:text-gray-800">Advanced: enter coordinates manually</summary>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-3">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Latitude</label>
                                <input id="latitude" type="text" name="latitude" value="{{ old('latitude') }}" class="w-full rounded-md border-gray-300 bg-gray-50" placeholder="Auto-filled from GPS/map">
                                @error('latitude') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Longitude</label>
                                <input id="longitude" type="text" name="longitude" value="{{ old('longitude') }}" class="w-full rounded-md border-gray-300 bg-gray-50" placeholder="Auto-filled from GPS/map">
                                @error('longitude') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>
                        </div>
                    </details>
                </div>

                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Notes (optional)</label>
                    <textarea name="notes" rows="4" class="w-full rounded-md border-gray-300" placeholder="Task details, address hints, gate number, etc.">{{ old('notes') }}</textarea>
                    @error('notes') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div class="md:col-span-2 flex justify-end">
                    <button type="submit" class="px-4 py-2 rounded-md bg-indigo-600 text-white hover:bg-indigo-700">Create Job</button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
    <script>
        (function () {
            const latInput = document.getElementById('latitude');
            const lngInput = document.getElementById('longitude');
            const cityInput = document.getElementById('city');
            const stateInput = document.getElementById('state');
            const countryInput = document.querySelector('input[name="country"]');
            const notesInput = document.querySelector('textarea[name="notes"]');
            const selectedAddressInput = document.getElementById('selected-address');
            const statusEl = document.getElementById('location-help');
            const gpsBtn = document.getElementById('use-current-location');
            const fallbackMap = document.getElementById('location-map-fallback');
            const interactiveMap = document.getElementById('location-map');

            const defaultLat = parseFloat(latInput.value) || 24.4539;
            const defaultLng = parseFloat(lngInput.value) || 54.3773;

            if (typeof L === 'undefined') {
                statusEl.textContent = 'Interactive map blocked by browser policy. Fallback map shown; use GPS button or manual coordinates.';
                interactiveMap.classList.add('hidden');
                fallbackMap.classList.remove('hidden');
                return;
            }

            const map = L.map('location-map').setView([defaultLat, defaultLng], 10);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; OpenStreetMap contributors'
            }).addTo(map);

            const marker = L.marker([defaultLat, defaultLng], { draggable: true }).addTo(map);

            function setLatLng(lat, lng, from) {
                latInput.value = Number(lat).toFixed(6);
                lngInput.value = Number(lng).toFixed(6);
                marker.setLatLng([lat, lng]);
                map.panTo([lat, lng]);
                if (statusEl) {
                    statusEl.textContent = `Location set from ${from}.`;
                }
                reverseGeocode(lat, lng);
            }

            async function reverseGeocode(lat, lng) {
                try {
                    const url = `https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat=${encodeURIComponent(lat)}&lon=${encodeURIComponent(lng)}&zoom=18&addressdetails=1`;
                    const res = await fetch(url, {
                        headers: {
                            'Accept': 'application/json'
                        }
                    });
                    if (!res.ok) {
                        return;
                    }
                    const data = await res.json();
                    const addr = data.address || {};
                    const city = addr.city || addr.town || addr.village || addr.county || '';
                    const state = addr.state || addr.province || '';
                    const country = addr.country || countryInput.value || 'UAE';

                    if (city && cityInput && !cityInput.dataset.touched) {
                        cityInput.value = city;
                    }
                    if (state && stateInput && !stateInput.dataset.touched) {
                        stateInput.value = state;
                    }
                    if (country && countryInput && !countryInput.dataset.touched) {
                        countryInput.value = country;
                    }

                    if (selectedAddressInput) {
                        selectedAddressInput.value = data.display_name || '';
                    }

                    if (notesInput && data.display_name) {
                        const txt = notesInput.value || '';
                        const autoPrefix = 'Auto address: ';
                        if (!txt.includes(autoPrefix)) {
                            notesInput.value = (txt ? txt + '\n' : '') + autoPrefix + data.display_name;
                        } else {
                            notesInput.value = txt.replace(/Auto address: .*/g, autoPrefix + data.display_name);
                        }
                    }

                    if (statusEl) {
                        statusEl.textContent = 'Location selected and address auto-filled.';
                    }
                } catch (e) {
                    // Non-fatal: map selection should still work with coordinates only.
                }
            }

            [cityInput, stateInput, countryInput].forEach(function (el) {
                if (!el) return;
                el.addEventListener('input', function () {
                    el.dataset.touched = '1';
                });
            });

            map.on('click', function (e) {
                setLatLng(e.latlng.lat, e.latlng.lng, 'map');
            });

            marker.on('dragend', function (e) {
                const p = e.target.getLatLng();
                setLatLng(p.lat, p.lng, 'map drag');
            });

            gpsBtn.addEventListener('click', function () {
                if (!navigator.geolocation) {
                    statusEl.textContent = 'Geolocation is not supported in this browser.';
                    return;
                }
                statusEl.textContent = 'Getting your current location...';
                navigator.geolocation.getCurrentPosition(function (position) {
                    setLatLng(position.coords.latitude, position.coords.longitude, 'GPS');
                    map.setZoom(13);
                }, function () {
                    statusEl.textContent = 'Unable to fetch GPS location. Allow location permission and try again.';
                }, { enableHighAccuracy: true, timeout: 10000 });
            });

            setTimeout(function () {
                map.invalidateSize();
            }, 250);
        })();
    </script>
</x-client-layout>

