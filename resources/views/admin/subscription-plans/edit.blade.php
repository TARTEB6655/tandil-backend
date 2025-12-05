<x-admin-layout>
    <div class="space-y-6">
        <h2 class="font-semibold text-2xl text-gray-800 leading-tight mb-6">
            Edit Subscription Plan
        </h2>

        <div class="bg-white shadow rounded-lg p-6">
            <form method="POST" action="{{ route('admin.subscription-plans.update', $plan['key']) }}">
                @csrf
                @method('PUT')

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700">Plan Name</label>
                    <input type="text" name="label" value="{{ old('label', $plan['label']) }}" required class="mt-1 block w-full rounded-md border-gray-300">
                    @error('label') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700">Price (AED)</label>
                    <input type="number" name="price" value="{{ old('price', $plan['price']) }}" step="0.01" min="0" required class="mt-1 block w-full rounded-md border-gray-300">
                    @error('price') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700">Visit Frequency</label>
                    <select name="visit_frequency" required class="mt-1 block w-full rounded-md border-gray-300">
                        <option value="weekly" {{ old('visit_frequency', $plan['visit_frequency'] ?? 'monthly') == 'weekly' ? 'selected' : '' }}>Weekly</option>
                        <option value="bi-weekly" {{ old('visit_frequency', $plan['visit_frequency'] ?? 'monthly') == 'bi-weekly' ? 'selected' : '' }}>Bi-Weekly</option>
                        <option value="monthly" {{ old('visit_frequency', $plan['visit_frequency'] ?? 'monthly') == 'monthly' ? 'selected' : '' }}>Monthly</option>
                    </select>
                    @error('visit_frequency') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div class="mb-4">
                    <label class="flex items-center">
                        <input type="checkbox" name="enabled" value="1" {{ old('enabled', $plan['enabled']) ? 'checked' : '' }} class="rounded border-gray-300">
                        <span class="ml-2 text-sm text-gray-700">Enabled</span>
                    </label>
                </div>

                <div class="flex gap-4">
                    <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700">Update Plan</button>
                    <a href="{{ route('admin.subscription-plans.index') }}" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</x-admin-layout>


