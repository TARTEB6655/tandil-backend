@php
    use Illuminate\Support\Facades\Storage;
@endphp
<x-client-layout>
    <!-- Page Header -->
    <div class="mb-4 sm:mb-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 sm:gap-0">
            <div>
                <h1 class="text-lg sm:text-xl font-medium text-gray-900">Complaint Details</h1>
                <p class="mt-1 text-xs sm:text-sm text-gray-500">View complaint information and status updates.</p>
            </div>
            <a href="{{ route('client.complaints.index') }}" class="text-xs sm:text-sm text-gray-600 hover:text-gray-900">
                ← Back to Complaints
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="mb-4 bg-green-50 border-l-4 border-green-400 p-4 rounded-md">
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-green-400" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                    </svg>
                </div>
                <div class="ml-3">
                    <p class="text-sm text-green-700">{{ session('success') }}</p>
                </div>
            </div>
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 sm:gap-6">
        <!-- Main Content -->
        <div class="lg:col-span-2 space-y-4 sm:space-y-6">
            <!-- Complaint Information -->
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 sm:p-6">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 sm:gap-0 mb-3 sm:mb-4">
                    <h2 class="text-base sm:text-lg font-semibold text-gray-900">Complaint Information</h2>
                    <span class="px-3 py-1 text-sm font-medium rounded-full 
                        @if($complaint->status === 'resolved') bg-green-100 text-green-800
                        @elseif($complaint->status === 'in_progress') bg-blue-100 text-blue-800
                        @else bg-yellow-100 text-yellow-800
                        @endif">
                        {{ ucfirst($complaint->status ?? 'pending') }}
                    </span>
                </div>
                
                <div class="space-y-4">
                    <div>
                        <p class="text-xs text-gray-500 mb-1">Complaint Notes</p>
                        <div class="bg-gray-50 rounded-lg p-4">
                            <p class="text-sm text-gray-700 whitespace-pre-wrap">{{ $complaint->notes ?? 'No notes provided' }}</p>
                        </div>
                    </div>
                    
                    <div>
                        <p class="text-xs text-gray-500 mb-1">Filed On</p>
                        <p class="text-sm font-medium text-gray-900">{{ $complaint->created_at->format('M d, Y h:i A') }}</p>
                    </div>
                    
                    @if($complaint->updated_at && $complaint->updated_at != $complaint->created_at)
                    <div>
                        <p class="text-xs text-gray-500 mb-1">Last Updated</p>
                        <p class="text-sm font-medium text-gray-900">{{ $complaint->updated_at->format('M d, Y h:i A') }}</p>
                    </div>
                    @endif
                </div>
            </div>

            <!-- Related Visit -->
            @if($complaint->visit)
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 sm:p-6">
                <h2 class="text-base sm:text-lg font-semibold text-gray-900 mb-3 sm:mb-4">Related Visit</h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4">
                    <div>
                        <p class="text-xs text-gray-500 mb-1">Visit Date</p>
                        <p class="text-sm font-medium text-gray-900">
                            {{ $complaint->visit->scheduled_date ? \Carbon\Carbon::parse($complaint->visit->scheduled_date)->format('M d, Y') : 'N/A' }}
                        </p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 mb-1">Status</p>
                        <span class="px-2 py-1 text-xs font-medium rounded-full 
                            @if($complaint->visit->status === 'completed') bg-green-100 text-green-800
                            @else bg-yellow-100 text-yellow-800
                            @endif">
                            {{ ucwords(str_replace('_', ' ', $complaint->visit->status ?? '')) }}
                        </span>
                    </div>
                    @if($complaint->visit->technician)
                    <div>
                        <p class="text-xs text-gray-500 mb-1">Technician</p>
                        <p class="text-sm font-medium text-gray-900">{{ $complaint->visit->technician->name }}</p>
                    </div>
                    @endif
                    @if($complaint->visit->supervisor)
                    <div>
                        <p class="text-xs text-gray-500 mb-1">Supervisor</p>
                        <p class="text-sm font-medium text-gray-900">{{ $complaint->visit->supervisor->name }}</p>
                    </div>
                    @endif
                    @if($complaint->visit->area)
                    <div>
                        <p class="text-xs text-gray-500 mb-1">Area</p>
                        <p class="text-sm font-medium text-gray-900">{{ $complaint->visit->area->name }}</p>
                    </div>
                    @endif
                </div>
                <div class="mt-4 pt-4 border-t border-gray-200">
                    <a href="{{ route('client.visits.show', $complaint->visit->id) }}" 
                       class="text-sm text-indigo-600 hover:text-indigo-900">
                        View Visit Details →
                    </a>
                </div>
            </div>
            @endif

            <!-- Visit Photos -->
            @if($complaint->visit && $complaint->visit->photos && $complaint->visit->photos->count() > 0)
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 sm:p-6">
                <h2 class="text-base sm:text-lg font-semibold text-gray-900 mb-3 sm:mb-4">Visit Photos</h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4">
                    @foreach($complaint->visit->photos as $photo)
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
        </div>

        <!-- Sidebar -->
        <div class="space-y-4 sm:space-y-6">
            <!-- Status Information -->
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 sm:p-6">
                <h3 class="text-sm sm:text-base font-semibold text-gray-900 mb-3 sm:mb-4">Status Information</h3>
                <div class="space-y-3">
                    <div>
                        <p class="text-xs text-gray-500 mb-1">Current Status</p>
                        <span class="px-2 py-1 text-xs font-medium rounded-full 
                            @if($complaint->status === 'resolved') bg-green-100 text-green-800
                            @elseif($complaint->status === 'in_progress') bg-blue-100 text-blue-800
                            @else bg-yellow-100 text-yellow-800
                            @endif">
                            {{ ucfirst($complaint->status ?? 'pending') }}
                        </span>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 mb-1">Complaint ID</p>
                        <p class="text-sm font-medium text-gray-900">#{{ $complaint->id }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 mb-1">Visit ID</p>
                        <p class="text-sm font-medium text-gray-900">#{{ $complaint->visit_id }}</p>
                    </div>
                </div>
            </div>

            <!-- Contact Information -->
            @if($complaint->visit)
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 sm:p-6">
                <h3 class="text-sm sm:text-base font-semibold text-gray-900 mb-3 sm:mb-4">Contact Information</h3>
                <div class="space-y-4">
                    @if($complaint->visit->supervisor)
                    <div>
                        <p class="text-xs text-gray-500 mb-1">Supervisor</p>
                        <p class="text-sm font-medium text-gray-900">{{ $complaint->visit->supervisor->name }}</p>
                        <p class="text-xs text-gray-500">{{ $complaint->visit->supervisor->email }}</p>
                        @if($complaint->visit->supervisor->phone)
                            <p class="text-xs text-gray-500">{{ $complaint->visit->supervisor->phone }}</p>
                        @endif
                    </div>
                    @endif
                    @if($complaint->visit->technician)
                    <div>
                        <p class="text-xs text-gray-500 mb-1">Technician</p>
                        <p class="text-sm font-medium text-gray-900">{{ $complaint->visit->technician->name }}</p>
                        <p class="text-xs text-gray-500">{{ $complaint->visit->technician->email }}</p>
                        @if($complaint->visit->technician->phone)
                            <p class="text-xs text-gray-500">{{ $complaint->visit->technician->phone }}</p>
                        @endif
                    </div>
                    @endif
                </div>
            </div>
            @endif
        </div>
    </div>
</x-client-layout>

