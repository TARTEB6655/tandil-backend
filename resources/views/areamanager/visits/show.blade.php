@php
    use Illuminate\Support\Facades\Storage;
@endphp
<x-areamanager-layout>
    <!-- Page Header -->
    <div class="mb-4 sm:mb-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 sm:gap-0">
            <div>
                <h1 class="text-lg sm:text-xl font-medium text-gray-900">Visit Details</h1>
                <p class="mt-1 text-xs sm:text-sm text-gray-500">View complete visit information.</p>
            </div>
            <a href="{{ route('areamanager.visits.index') }}" class="text-xs sm:text-sm text-gray-600 hover:text-gray-900">
                ← Back to Visits
            </a>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 sm:gap-6">
        <!-- Main Content -->
        <div class="lg:col-span-2 space-y-4 sm:space-y-6">
            <!-- Visit Information -->
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 sm:p-6">
                <h2 class="text-base sm:text-lg font-semibold text-gray-900 mb-3 sm:mb-4">Visit Information</h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4">
                    <div>
                        <p class="text-xs text-gray-500 mb-1">Scheduled Date</p>
                        <p class="text-xs sm:text-sm font-medium text-gray-900">
                            {{ $visit->scheduled_date ? \Carbon\Carbon::parse($visit->scheduled_date)->format('M d, Y') : 'N/A' }}
                        </p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 mb-1">Status</p>
                        <span class="px-2 py-1 text-xs font-medium rounded-full 
                            @if($visit->status === 'completed') bg-green-100 text-green-800
                            @elseif($visit->status === 'approved') bg-blue-100 text-blue-800
                            @elseif($visit->status === 'rejected') bg-red-100 text-red-800
                            @elseif($visit->status === 'started') bg-indigo-100 text-indigo-800
                            @elseif($visit->status === 'accepted') bg-purple-100 text-purple-800
                            @else bg-yellow-100 text-yellow-800
                            @endif">
                            {{ ucwords(str_replace('_', ' ', $visit->status ?? '')) }}
                        </span>
                    </div>
                    @if($visit->subscription && $visit->subscription->client)
                    <div>
                        <p class="text-xs text-gray-500 mb-1">Client</p>
                        <p class="text-xs sm:text-sm font-medium text-gray-900">{{ $visit->subscription->client->name }}</p>
                        <p class="text-xs text-gray-500">{{ $visit->subscription->client->email }}</p>
                        @if($visit->subscription->client->phone)
                            <p class="text-xs text-gray-500">{{ $visit->subscription->client->phone }}</p>
                        @endif
                    </div>
                    @endif
                    @if($visit->technician)
                    <div>
                        <p class="text-xs text-gray-500 mb-1">Technician</p>
                        <p class="text-xs sm:text-sm font-medium text-gray-900">{{ $visit->technician->name }}</p>
                        <p class="text-xs text-gray-500">{{ $visit->technician->email }}</p>
                    </div>
                    @endif
                    @if($visit->supervisor)
                    <div>
                        <p class="text-xs text-gray-500 mb-1">Supervisor</p>
                        <p class="text-xs sm:text-sm font-medium text-gray-900">{{ $visit->supervisor->name }}</p>
                        <p class="text-xs text-gray-500">{{ $visit->supervisor->email }}</p>
                    </div>
                    @endif
                    @if($visit->area)
                    <div>
                        <p class="text-xs text-gray-500 mb-1">Area</p>
                        <p class="text-xs sm:text-sm font-medium text-gray-900">{{ $visit->area->name }}</p>
                    </div>
                    @endif
                    @if($visit->accepted_at)
                    <div>
                        <p class="text-xs text-gray-500 mb-1">Accepted At</p>
                        <p class="text-xs sm:text-sm font-medium text-gray-900">
                            {{ \Carbon\Carbon::parse($visit->accepted_at)->format('M d, Y h:i A') }}
                        </p>
                    </div>
                    @endif
                    @if($visit->started_at)
                    <div>
                        <p class="text-xs text-gray-500 mb-1">Started At</p>
                        <p class="text-xs sm:text-sm font-medium text-gray-900">
                            {{ \Carbon\Carbon::parse($visit->started_at)->format('M d, Y h:i A') }}
                        </p>
                    </div>
                    @endif
                    @if($visit->completed_at)
                    <div>
                        <p class="text-xs text-gray-500 mb-1">Completed At</p>
                        <p class="text-xs sm:text-sm font-medium text-gray-900">
                            {{ \Carbon\Carbon::parse($visit->completed_at)->format('M d, Y h:i A') }}
                        </p>
                    </div>
                    @endif
                    @if($visit->approved_at)
                    <div>
                        <p class="text-xs text-gray-500 mb-1">Approved At</p>
                        <p class="text-xs sm:text-sm font-medium text-gray-900">
                            {{ \Carbon\Carbon::parse($visit->approved_at)->format('M d, Y h:i A') }}
                        </p>
                    </div>
                    @endif
                </div>
                @if($visit->notes)
                <div class="mt-4 pt-4 border-t border-gray-200">
                    <p class="text-xs text-gray-500 mb-1">Notes</p>
                    <p class="text-xs sm:text-sm text-gray-700 whitespace-pre-wrap">{{ $visit->notes }}</p>
                </div>
                @endif
            </div>

            <!-- Visit Photos -->
            @if($visit->photos && $visit->photos->count() > 0)
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 sm:p-6">
                <h2 class="text-base sm:text-lg font-semibold text-gray-900 mb-3 sm:mb-4">Visit Photos</h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4">
                    @foreach($visit->photos as $photo)
                        <div class="relative group">
                            <img src="{{ Storage::disk('public')->exists($photo->photo_path) ? asset('storage/' . $photo->photo_path) : asset('images/placeholder.png') }}" 
                                 alt="Visit Photo" 
                                 class="w-full h-40 sm:h-48 object-cover rounded-lg border border-gray-200">
                            <div class="absolute bottom-2 left-2 bg-black bg-opacity-50 text-white text-xs px-2 py-1 rounded">
                                {{ ucfirst($photo->type ?? 'Photo') }}
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
            @endif

            <!-- Related Report -->
            @if($visit->report)
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 sm:p-6">
                <h2 class="text-base sm:text-lg font-semibold text-gray-900 mb-3 sm:mb-4">Related Report</h2>
                <div class="space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="text-xs sm:text-sm text-gray-500">Status</span>
                        <span class="px-2 py-1 text-xs font-medium rounded-full 
                            @if($visit->report->status === 'approved') bg-green-100 text-green-800
                            @else bg-yellow-100 text-yellow-800
                            @endif">
                            {{ ucfirst($visit->report->status ?? 'pending') }}
                        </span>
                    </div>
                    <div>
                        <a href="{{ route('areamanager.reports.show', $visit->report->id) }}" 
                           class="text-xs sm:text-sm text-indigo-600 hover:text-indigo-900">
                            View Report →
                        </a>
                    </div>
                </div>
            </div>
            @endif

            <!-- Related Complaints -->
            @if($visit->complaints && $visit->complaints->count() > 0)
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 sm:p-6">
                <h2 class="text-base sm:text-lg font-semibold text-gray-900 mb-3 sm:mb-4">Related Complaints ({{ $visit->complaints->count() }})</h2>
                <div class="space-y-2">
                    @foreach($visit->complaints as $complaint)
                        <div class="flex items-center justify-between p-2 bg-gray-50 rounded-lg">
                            <div>
                                <p class="text-xs sm:text-sm font-medium text-gray-900">Complaint #{{ $complaint->id }}</p>
                                <p class="text-xs text-gray-500">{{ ucfirst($complaint->status) }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
            @endif
        </div>

        <!-- Sidebar -->
        <div class="space-y-4 sm:space-y-6">
            <!-- Subscription Information -->
            @if($visit->subscription)
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 sm:p-6">
                <h3 class="text-sm sm:text-base font-semibold text-gray-900 mb-3 sm:mb-4">Subscription</h3>
                <div class="space-y-2">
                    <div>
                        <p class="text-xs text-gray-500">Plan</p>
                        <p class="text-xs sm:text-sm font-medium text-gray-900">{{ ucfirst(str_replace('_', ' ', $visit->subscription->plan)) }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500">Amount</p>
                        <p class="text-xs sm:text-sm font-medium text-gray-900">AED {{ number_format($visit->subscription->amount, 2) }}</p>
                    </div>
                </div>
            </div>
            @endif

            <!-- Quick Actions -->
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 sm:p-6">
                <h3 class="text-sm sm:text-base font-semibold text-gray-900 mb-3 sm:mb-4">Quick Actions</h3>
                <div class="space-y-2">
                    @if($visit->report)
                        <a href="{{ route('areamanager.reports.show', $visit->report->id) }}" 
                           class="block w-full text-center px-3 sm:px-4 py-2 text-xs sm:text-sm font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-700">
                            View Report
                        </a>
                    @endif
                    @if($visit->area)
                        <a href="{{ route('areamanager.areas.show', $visit->area->id) }}" 
                           class="block w-full text-center px-3 sm:px-4 py-2 text-xs sm:text-sm font-medium text-purple-600 bg-purple-50 rounded-lg hover:bg-purple-100">
                            View Area
                        </a>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-areamanager-layout>

