<x-client-layout>
    <!-- Page Header -->
    <div class="mb-4 sm:mb-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 sm:gap-0">
            <div>
                <h1 class="text-lg sm:text-xl font-medium text-gray-900">File a Complaint</h1>
                <p class="mt-1 text-xs sm:text-sm text-gray-500">Report an issue with a visit or service.</p>
            </div>
            <a href="{{ route('client.complaints.index') }}" class="text-xs sm:text-sm text-gray-600 hover:text-gray-900">
                ← Back to Complaints
            </a>
        </div>
    </div>

    @if($errors->any())
        <div class="mb-4 bg-red-50 border-l-4 border-red-400 p-4 rounded-md">
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                    </svg>
                </div>
                <div class="ml-3">
                    <h3 class="text-sm font-medium text-red-800">There were errors with your submission:</h3>
                    <div class="mt-2 text-sm text-red-700">
                        <ul class="list-disc list-inside space-y-1">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 sm:gap-6">
        <!-- Main Form -->
        <div class="lg:col-span-2">
            <form action="{{ route('client.complaints.store') }}" method="POST" class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 sm:p-6">
                @csrf

                <!-- Visit Selection -->
                <div class="mb-4 sm:mb-6">
                    <label for="visit_id" class="block text-xs sm:text-sm font-medium text-gray-700 mb-2">
                        Select Visit <span class="text-red-500">*</span>
                    </label>
                    <select name="visit_id" id="visit_id" 
                            class="w-full text-xs sm:text-sm rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('visit_id') border-red-300 @enderror"
                            required>
                        <option value="">-- Select a visit --</option>
                        @foreach($visits as $visit)
                            <option value="{{ $visit->id }}" 
                                    {{ old('visit_id', $selectedVisit?->id) == $visit->id ? 'selected' : '' }}>
                                Visit #{{ $visit->id }} - 
                                {{ $visit->scheduled_date ? \Carbon\Carbon::parse($visit->scheduled_date)->format('M d, Y') : 'N/A' }}
                                @if($visit->technician)
                                    - Technician: {{ $visit->technician->name }}
                                @endif
                                @if($visit->status)
                                    ({{ ucfirst($visit->status) }})
                                @endif
                            </option>
                        @endforeach
                    </select>
                    @error('visit_id')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                    @if($visits->isEmpty())
                        <p class="mt-2 text-sm text-gray-500">You don't have any visits yet. Complaints can only be filed for completed or scheduled visits.</p>
                    @endif
                </div>

                <!-- Complaint Notes -->
                <div class="mb-4 sm:mb-6">
                    <label for="notes" class="block text-xs sm:text-sm font-medium text-gray-700 mb-2">
                        Complaint Details <span class="text-red-500">*</span>
                    </label>
                    <textarea name="notes" id="notes" rows="5 sm:rows-6" 
                              class="w-full text-xs sm:text-sm rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('notes') border-red-300 @enderror"
                              placeholder="Please describe the issue or concern regarding this visit..."
                              required>{{ old('notes') }}</textarea>
                    @error('notes')
                        <p class="mt-1 text-xs sm:text-sm text-red-600">{{ $message }}</p>
                    @enderror
                    <p class="mt-2 text-xs text-gray-500">Maximum 1000 characters. Be as specific as possible to help us resolve your concern quickly.</p>
                </div>

                <!-- Submit Button -->
                <div class="flex flex-col sm:flex-row items-stretch sm:items-center sm:justify-end gap-2 sm:gap-3">
                    <a href="{{ route('client.complaints.index') }}" 
                       class="w-full sm:w-auto text-center px-4 py-2 text-xs sm:text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                        Cancel
                    </a>
                    <button type="submit" 
                            class="w-full sm:w-auto px-4 py-2 text-xs sm:text-sm font-medium text-white bg-indigo-600 border border-transparent rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed"
                            {{ $visits->isEmpty() ? 'disabled' : '' }}>
                        Submit Complaint
                    </button>
                </div>
            </form>
        </div>

        <!-- Sidebar Info -->
        <div class="space-y-4 sm:space-y-6">
            <!-- Selected Visit Info -->
            @if($selectedVisit)
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 sm:p-6">
                <h3 class="text-sm sm:text-base font-semibold text-gray-900 mb-3 sm:mb-4">Selected Visit</h3>
                <div class="space-y-3">
                    <div>
                        <p class="text-xs text-gray-500">Visit Date</p>
                        <p class="text-sm font-medium text-gray-900">
                            {{ $selectedVisit->scheduled_date ? \Carbon\Carbon::parse($selectedVisit->scheduled_date)->format('M d, Y') : 'N/A' }}
                        </p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500">Status</p>
                        <span class="px-2 py-1 text-xs font-medium rounded-full 
                            @if($selectedVisit->status === 'completed') bg-green-100 text-green-800
                            @elseif($selectedVisit->status === 'started' || $selectedVisit->status === 'accepted') bg-blue-100 text-blue-800
                            @else bg-yellow-100 text-yellow-800
                            @endif">
                            {{ ucfirst($selectedVisit->status) }}
                        </span>
                    </div>
                    @if($selectedVisit->technician)
                    <div>
                        <p class="text-xs text-gray-500">Technician</p>
                        <p class="text-sm font-medium text-gray-900">{{ $selectedVisit->technician->name }}</p>
                    </div>
                    @endif
                    @if($selectedVisit->supervisor)
                    <div>
                        <p class="text-xs text-gray-500">Supervisor</p>
                        <p class="text-sm font-medium text-gray-900">{{ $selectedVisit->supervisor->name }}</p>
                    </div>
                    @endif
                    <div class="pt-3 border-t border-gray-200">
                        <a href="{{ route('client.visits.show', $selectedVisit->id) }}" 
                           class="text-sm text-indigo-600 hover:text-indigo-900">
                            View Visit Details →
                        </a>
                    </div>
                </div>
            </div>
            @endif

            <!-- Help Info -->
            <div class="bg-blue-50 rounded-xl border border-blue-200 p-4 sm:p-6">
                <h3 class="text-sm sm:text-base font-semibold text-blue-900 mb-2">Need Help?</h3>
                <p class="text-xs sm:text-sm text-blue-800 mb-3">
                    If you have concerns about a visit or service, please file a complaint. Our team will review it and get back to you.
                </p>
                <ul class="text-xs text-blue-700 space-y-1 list-disc list-inside">
                    <li>Select the visit you want to complain about</li>
                    <li>Provide detailed information about the issue</li>
                    <li>We'll review and respond within 24-48 hours</li>
                </ul>
            </div>
        </div>
    </div>
</x-client-layout>

