<x-admin-layout>
    <h2 class="font-semibold text-2xl text-gray-800 leading-tight mb-6">
            Report Details
        </h2>

    <div class="space-y-6">
        <div class="bg-white shadow rounded-lg p-6 space-y-6">
            <!-- Visit Info -->
            <div>
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Visit Information</h3>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <p class="text-sm text-gray-500">Client</p>
                        <p class="text-sm font-medium text-gray-900">{{ $report->visit->subscription->client->name ?? 'N/A' }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Technician</p>
                        <p class="text-sm font-medium text-gray-900">{{ $report->visit->technician->name ?? 'N/A' }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Scheduled Date</p>
                        <p class="text-sm font-medium text-gray-900">{{ $report->visit->scheduled_date ? \Carbon\Carbon::parse($report->visit->scheduled_date)->format('M d, Y') : 'N/A' }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Status</p>
                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">
                            {{ ucfirst($report->visit->status) }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- Technician Notes -->
            @if($report->technician_notes)
            <div>
                <h3 class="text-lg font-semibold text-gray-900 mb-2">Technician Notes</h3>
                <p class="text-sm text-gray-700 bg-gray-50 p-4 rounded">{{ $report->technician_notes }}</p>
            </div>
            @endif

            <!-- Supervisor Notes -->
            @if($report->supervisor_notes)
            <div>
                <h3 class="text-lg font-semibold text-gray-900 mb-2">Supervisor Notes</h3>
                <p class="text-sm text-gray-700 bg-blue-50 p-4 rounded">{{ $report->supervisor_notes }}</p>
            </div>
            @endif

            <!-- Recommendations -->
            @if($report->recommendations)
            <div>
                <h3 class="text-lg font-semibold text-gray-900 mb-2">Recommendations</h3>
                <div class="bg-green-50 p-4 rounded">
                    <ul class="list-disc list-inside space-y-1">
                        @foreach($report->recommendations as $recommendation)
                            <li class="text-sm text-gray-700">{{ $recommendation }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
            @endif

            <!-- Photos -->
            @if($report->visit->photos && $report->visit->photos->count() > 0)
            <div>
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Visit Photos</h3>
                <div class="grid grid-cols-2 gap-4">
                    @foreach($report->visit->photos as $photo)
                        <img src="{{ asset('storage/' . $photo->photo_path) }}" alt="Visit Photo" class="rounded-lg">
                    @endforeach
                </div>
            </div>
            @endif

            <div class="pt-4">
                <a href="{{ route('admin.reports.index') }}" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300">Back to Reports</a>
            </div>
        </div>
    </div>
</x-admin-layout>

