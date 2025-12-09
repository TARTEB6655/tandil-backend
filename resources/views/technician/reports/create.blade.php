<x-technician-layout>
    <!-- Page Header -->
    <div class="mb-4 sm:mb-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 sm:gap-0">
            <div>
                <h1 class="text-lg sm:text-xl font-medium text-gray-900">Create Report</h1>
                <p class="mt-1 text-xs sm:text-sm text-gray-500">Create a new report for a completed visit.</p>
            </div>
            <a href="{{ route('technician.reports.index') }}" class="text-xs sm:text-sm text-gray-600 hover:text-gray-900">
                ← Back to Reports
            </a>
        </div>
    </div>

    @if($errors->any())
        <div class="mb-4 bg-red-50 border-l-4 border-red-400 p-3 sm:p-4 rounded-md">
            <ul class="list-disc list-inside text-xs sm:text-sm text-red-700">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form action="{{ route('technician.reports.store') }}" method="POST" class="space-y-4 sm:space-y-6">
        @csrf
        
        <!-- Visit Selection -->
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 sm:p-6">
            <h2 class="text-base sm:text-lg font-semibold text-gray-900 mb-3 sm:mb-4">Select Visit</h2>
            <div>
                <label for="visit_id" class="block text-xs sm:text-sm font-medium text-gray-700 mb-2">Visit *</label>
                <select name="visit_id" id="visit_id" required
                        class="w-full text-xs sm:text-sm rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">Select a completed visit...</option>
                    @foreach($visits as $visit)
                        <option value="{{ $visit->id }}" {{ old('visit_id', $selectedVisit?->id) == $visit->id ? 'selected' : '' }}>
                            Visit #{{ $visit->id }} - 
                            {{ $visit->subscription && $visit->subscription->client ? $visit->subscription->client->name : 'N/A' }} - 
                            {{ $visit->scheduled_date ? \Carbon\Carbon::parse($visit->scheduled_date)->format('M d, Y') : 'N/A' }}
                        </option>
                    @endforeach
                </select>
                @if($visits->isEmpty())
                    <p class="mt-2 text-xs sm:text-sm text-gray-500">No completed visits available for report creation.</p>
                @endif
            </div>
        </div>

        <!-- Technician Notes -->
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 sm:p-6">
            <h2 class="text-base sm:text-lg font-semibold text-gray-900 mb-3 sm:mb-4">Technician Notes</h2>
            <div>
                <label for="technician_notes" class="block text-xs sm:text-sm font-medium text-gray-700 mb-2">Notes *</label>
                <textarea name="technician_notes" id="technician_notes" rows="8" required
                          class="w-full text-xs sm:text-sm rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                          placeholder="Enter your notes about the visit...">{{ old('technician_notes') }}</textarea>
                <p class="mt-1 text-xs text-gray-500">Describe the work performed, observations, and any important details.</p>
            </div>
        </div>

        <!-- Recommended Products -->
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 sm:p-6">
            <h2 class="text-base sm:text-lg font-semibold text-gray-900 mb-3 sm:mb-4">Recommended Products</h2>
            <div>
                <label for="recommended_products" class="block text-xs sm:text-sm font-medium text-gray-700 mb-2">Select Products (Optional)</label>
                <div class="max-h-64 overflow-y-auto border border-gray-200 rounded-md p-3 sm:p-4 space-y-2">
                    @forelse($products as $product)
                        <label class="flex items-start gap-2 sm:gap-3 p-2 hover:bg-gray-50 rounded-md cursor-pointer">
                            <input type="checkbox" 
                                   name="recommended_products[]" 
                                   value="{{ $product->id }}"
                                   {{ in_array($product->id, old('recommended_products', [])) ? 'checked' : '' }}
                                   class="mt-1 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                            <div class="flex-1 min-w-0">
                                <p class="text-xs sm:text-sm font-medium text-gray-900">{{ $product->name }}</p>
                                <p class="text-xs text-gray-500">AED {{ number_format($product->price, 2) }}</p>
                            </div>
                        </label>
                    @empty
                        <p class="text-xs sm:text-sm text-gray-500">No products available.</p>
                    @endforelse
                </div>
                <p class="mt-2 text-xs text-gray-500">Select products that you recommend for the client based on this visit.</p>
            </div>
        </div>

        <!-- Submit Button -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-end gap-3">
            <a href="{{ route('technician.reports.index') }}" 
               class="w-full sm:w-auto px-4 py-2 text-xs sm:text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 text-center">
                Cancel
            </a>
            <button type="submit" 
                    class="w-full sm:w-auto px-4 py-2 text-xs sm:text-sm font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-700">
                Create Report
            </button>
        </div>
    </form>
</x-technician-layout>

