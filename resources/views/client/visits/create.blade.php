<x-client-layout>
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
                    <input type="text" name="city" value="{{ old('city') }}" class="w-full rounded-md border-gray-300" placeholder="Abu Dhabi">
                    @error('city') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">State/Emirate</label>
                    <input type="text" name="state" value="{{ old('state') }}" class="w-full rounded-md border-gray-300" placeholder="Abu Dhabi">
                    @error('state') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Latitude (optional)</label>
                    <input type="text" name="latitude" value="{{ old('latitude') }}" class="w-full rounded-md border-gray-300" placeholder="24.453884">
                    @error('latitude') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Longitude (optional)</label>
                    <input type="text" name="longitude" value="{{ old('longitude') }}" class="w-full rounded-md border-gray-300" placeholder="54.377344">
                    @error('longitude') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
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
</x-client-layout>

