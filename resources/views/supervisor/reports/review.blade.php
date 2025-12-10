<x-supervisor-layout>
    <!-- Page Header -->
    <div class="mb-4 sm:mb-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 sm:gap-0">
            <div>
                <h1 class="text-lg sm:text-xl font-medium text-gray-900">Review & Finalize Report</h1>
                <p class="mt-1 text-xs sm:text-sm text-gray-500">Review the report and add your notes and recommendations.</p>
            </div>
            <a href="{{ route('supervisor.reports.show', $report->id) }}" class="text-xs sm:text-sm text-gray-600 hover:text-gray-900">
                ← Back to Report
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

    <form action="{{ route('supervisor.reports.finalize', $report->id) }}" method="POST" class="space-y-4 sm:space-y-6">
        @csrf

        <!-- Visit Information -->
        @if($report->visit)
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 sm:p-6">
            <h2 class="text-base sm:text-lg font-semibold text-gray-900 mb-3 sm:mb-4">Visit Information</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4">
                <div>
                    <p class="text-xs text-gray-500 mb-1">Visit Date</p>
                    <p class="text-xs sm:text-sm font-medium text-gray-900">
                        {{ $report->visit->scheduled_date ? \Carbon\Carbon::parse($report->visit->scheduled_date)->format('M d, Y') : 'N/A' }}
                    </p>
                </div>
                @if($report->visit->subscription && $report->visit->subscription->client)
                <div>
                    <p class="text-xs text-gray-500 mb-1">Client</p>
                    <p class="text-xs sm:text-sm font-medium text-gray-900">{{ $report->visit->subscription->client->name }}</p>
                </div>
                @endif
                @if($report->visit->technician)
                <div>
                    <p class="text-xs text-gray-500 mb-1">Technician</p>
                    <p class="text-xs sm:text-sm font-medium text-gray-900">{{ $report->visit->technician->name }}</p>
                </div>
                @endif
            </div>
        </div>
        @endif

        <!-- Technician Notes -->
        @if($report->technician_notes)
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 sm:p-6">
            <h2 class="text-base sm:text-lg font-semibold text-gray-900 mb-3 sm:mb-4">Technician Notes</h2>
            <div class="bg-gray-50 rounded-lg p-3 sm:p-4">
                <p class="text-xs sm:text-sm text-gray-700 whitespace-pre-wrap">{{ $report->technician_notes }}</p>
            </div>
        </div>
        @endif

        <!-- Supervisor Notes -->
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 sm:p-6">
            <h2 class="text-base sm:text-lg font-semibold text-gray-900 mb-3 sm:mb-4">Supervisor Notes</h2>
            <div>
                <label for="supervisor_notes" class="block text-xs sm:text-sm font-medium text-gray-700 mb-2">Add Your Notes</label>
                <textarea name="supervisor_notes" id="supervisor_notes" rows="8"
                          class="w-full text-xs sm:text-sm rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                          placeholder="Add your review notes, observations, or recommendations...">{{ old('supervisor_notes', $report->supervisor_notes) }}</textarea>
                <p class="mt-1 text-xs text-gray-500">Add your review notes, observations, or any additional recommendations.</p>
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
                                   {{ in_array($product->id, old('recommended_products', $report->recommended_products ?? [])) ? 'checked' : '' }}
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

        <!-- Approval Status -->
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 sm:p-6">
            <h2 class="text-base sm:text-lg font-semibold text-gray-900 mb-3 sm:mb-4">Finalize Report</h2>
            <div>
                <label for="status" class="block text-xs sm:text-sm font-medium text-gray-700 mb-2">Status *</label>
                <select name="status" id="status" required
                        class="w-full text-xs sm:text-sm rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="pending" {{ old('status', $report->status) === 'pending' ? 'selected' : '' }}>Pending</option>
                    <option value="approved" {{ old('status', $report->status) === 'approved' ? 'selected' : '' }}>Approve</option>
                </select>
                <p class="mt-1 text-xs text-gray-500">Choose whether to approve this report or keep it pending.</p>
            </div>
        </div>

        <!-- Submit Button -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-end gap-3">
            <a href="{{ route('supervisor.reports.show', $report->id) }}" 
               class="w-full sm:w-auto px-4 py-2 text-xs sm:text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 text-center">
                Cancel
            </a>
            <button type="submit" 
                    class="w-full sm:w-auto px-4 py-2 text-xs sm:text-sm font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-700">
                Finalize Report
            </button>
        </div>
    </form>
</x-supervisor-layout>

