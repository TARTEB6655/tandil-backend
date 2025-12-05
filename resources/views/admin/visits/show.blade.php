<x-admin-layout>
    <h1 class="text-xl font-medium text-gray-900 mb-6">
            Visit Details
        </h2>

    <div class="space-y-6">
        <div class="bg-white shadow rounded-lg p-6 space-y-6">
            <!-- Visit Info -->
            <div>
                <h3 class="text-base font-medium text-gray-900 mb-4">Visit Information</h3>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <p class="text-sm text-gray-500">Client</p>
                        <p class="text-sm font-medium text-gray-900">{{ $visit->subscription->client->name ?? 'N/A' }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Technician</p>
                        <p class="text-sm font-medium text-gray-900">{{ $visit->technician->name ?? 'Unassigned' }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Supervisor</p>
                        <p class="text-sm font-medium text-gray-900">{{ $visit->supervisor->name ?? 'N/A' }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Area</p>
                        <p class="text-sm font-medium text-gray-900">{{ $visit->area->name ?? 'N/A' }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Scheduled Date</p>
                        <p class="text-sm font-medium text-gray-900">{{ $visit->scheduled_date ? \Carbon\Carbon::parse($visit->scheduled_date)->format('M d, Y') : 'N/A' }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Status</p>
                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                            {{ $visit->status == 'completed' ? 'bg-green-100 text-green-800' : 
                               ($visit->status == 'pending' ? 'bg-yellow-100 text-yellow-800' : 'bg-blue-100 text-blue-800') }}">
                            {{ ucfirst($visit->status) }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- Photos -->
            @if($visit->photos && $visit->photos->count() > 0)
            <div>
                <h3 class="text-base font-medium text-gray-900 mb-4">Visit Photos</h3>
                <div class="grid grid-cols-2 gap-4">
                    @foreach($visit->photos as $photo)
                        <div>
                            <img src="{{ asset('storage/' . $photo->photo_path) }}" alt="Visit Photo" class="rounded-lg w-full">
                            <p class="text-xs text-gray-500 mt-1">{{ $photo->type ?? 'Photo' }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
            @endif

            <!-- Report -->
            @if($visit->report)
            <div>
                <h3 class="text-base font-medium text-gray-900 mb-4">Report</h3>
                <div class="bg-gray-50 p-4 rounded">
                    @if($visit->report->supervisor_notes)
                        <p class="text-sm text-gray-700 mb-2"><strong>Supervisor Notes:</strong> {{ $visit->report->supervisor_notes }}</p>
                    @endif
                    @if($visit->report->recommendations)
                        <p class="text-sm text-gray-700"><strong>Recommendations:</strong></p>
                        <ul class="list-disc list-inside mt-1">
                            @foreach($visit->report->recommendations as $rec)
                                <li class="text-sm text-gray-700">{{ $rec }}</li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>
            @endif

            <div class="pt-4">
                <a href="{{ route('admin.visits.index') }}" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300">Back to Visits</a>
            </div>
        </div>
    </div>
</x-admin-layout>

