<x-admin-layout>
    <div class="space-y-6">
        <!-- Page Header -->
        <div class="mb-6 md:mb-8">
            <h1 class="text-xl font-medium text-gray-900">Edit Subscription Plan</h1>
            <p class="mt-1 text-sm text-gray-500">Update plan pricing and settings</p>
        </div>

        @if(session('success'))
            <div class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg flex items-center gap-2 mb-4">
                <svg class="w-5 h-5 text-green-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span class="text-sm">{{ session('success') }}</span>
            </div>
        @endif

        @if(session('error'))
            <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg flex items-center gap-2 mb-4">
                <svg class="w-5 h-5 text-red-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span class="text-sm">{{ session('error') }}</span>
            </div>
        @endif

        <!-- Edit Plan Form -->
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
            <form method="POST" action="{{ route('admin.subscription-plans.update', $plan['key']) }}" class="space-y-6">
                @csrf
                @method('PUT')

                <!-- Plan Name -->
                <div>
                    <label for="label" class="block text-sm font-medium text-gray-700 mb-2">Plan Name</label>
                    <input type="text" 
                           id="label" 
                           name="label" 
                           value="{{ old('label', $plan['label']) }}" 
                           required 
                           class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                    @error('label')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Price -->
                <div>
                    <label for="price" class="block text-sm font-medium text-gray-700 mb-2">Price (AED)</label>
                    <input type="number" 
                           id="price" 
                           name="price" 
                           value="{{ old('price', $plan['price']) }}" 
                           step="0.01" 
                           min="0" 
                           required 
                           class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                    @error('price')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Visit Frequency -->
                <div>
                    <label for="visit_frequency" class="block text-sm font-medium text-gray-700 mb-2">Visit Frequency</label>
                    <select id="visit_frequency" 
                            name="visit_frequency" 
                            required 
                            class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                        <option value="weekly" {{ old('visit_frequency', $plan['visit_frequency'] ?? 'monthly') == 'weekly' ? 'selected' : '' }}>Weekly</option>
                        <option value="bi-weekly" {{ old('visit_frequency', $plan['visit_frequency'] ?? 'monthly') == 'bi-weekly' ? 'selected' : '' }}>Bi-Weekly</option>
                        <option value="monthly" {{ old('visit_frequency', $plan['visit_frequency'] ?? 'monthly') == 'monthly' ? 'selected' : '' }}>Monthly</option>
                    </select>
                    @error('visit_frequency')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Enabled Status -->
                <div class="flex items-center gap-3 p-4 bg-gray-50 rounded-lg border border-gray-200">
                    <input type="checkbox" 
                           id="enabled" 
                           name="enabled" 
                           value="1" 
                           {{ old('enabled', $plan['enabled']) ? 'checked' : '' }} 
                           class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                    <label for="enabled" class="text-sm font-medium text-gray-700 cursor-pointer">
                        Enable this plan
                    </label>
                </div>

                <!-- Actions -->
                <div class="flex items-center justify-end gap-4 pt-4 border-t border-gray-200">
                    <a href="{{ route('admin.subscription-plans.index') }}" 
                       class="px-6 py-2.5 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors">
                        Cancel
                    </a>
                    <button type="submit" 
                            class="px-6 py-2.5 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors font-medium">
                        Update Plan
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-admin-layout>
