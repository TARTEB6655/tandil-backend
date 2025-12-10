@php
    use Illuminate\Support\Facades\Storage;
@endphp
<x-supervisor-layout>
    <!-- Page Header -->
    <div class="mb-4 sm:mb-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 sm:gap-0">
            <div>
                <h1 class="text-lg sm:text-xl font-medium text-gray-900">Complaint Details</h1>
                <p class="mt-1 text-xs sm:text-sm text-gray-500">View and update complaint status and notes.</p>
            </div>
            <a href="{{ route('supervisor.complaints.index') }}" class="text-xs sm:text-sm text-gray-600 hover:text-gray-900">
                ← Back to Complaints
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="mb-4 bg-green-50 border-l-4 border-green-400 p-3 sm:p-4 rounded-md">
            <p class="text-xs sm:text-sm text-green-700">{{ session('success') }}</p>
        </div>
    @endif

    @if($errors->any())
        <div class="mb-4 bg-red-50 border-l-4 border-red-400 p-3 sm:p-4 rounded-md">
            <ul class="list-disc list-inside text-xs sm:text-sm text-red-700">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 sm:gap-6">
        <!-- Main Content -->
        <div class="lg:col-span-2 space-y-4 sm:space-y-6">
            <!-- Complaint Information -->
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 sm:p-6">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 sm:gap-0 mb-3 sm:mb-4">
                    <h2 class="text-base sm:text-lg font-semibold text-gray-900">Complaint Information</h2>
                    <span class="px-3 py-1 text-xs sm:text-sm font-medium rounded-full 
                        @if($complaint->status === 'resolved') bg-green-100 text-green-800
                        @elseif($complaint->status === 'in_progress') bg-blue-100 text-blue-800
                        @elseif($complaint->status === 'escalated') bg-red-100 text-red-800
                        @else bg-yellow-100 text-yellow-800
                        @endif">
                        {{ ucfirst($complaint->status ?? 'pending') }}
                    </span>
                </div>
                
                <div class="space-y-3 sm:space-y-4">
                    <div>
                        <p class="text-xs text-gray-500 mb-1">Complaint Notes</p>
                        <div class="bg-gray-50 rounded-lg p-3 sm:p-4">
                            <p class="text-xs sm:text-sm text-gray-700 whitespace-pre-wrap">{{ $complaint->notes ?? 'No notes provided' }}</p>
                        </div>
                    </div>
                    
                    <div>
                        <p class="text-xs text-gray-500 mb-1">Filed On</p>
                        <p class="text-xs sm:text-sm font-medium text-gray-900">{{ $complaint->created_at->format('M d, Y h:i A') }}</p>
                    </div>
                    
                    @if($complaint->updated_at && $complaint->updated_at != $complaint->created_at)
                    <div>
                        <p class="text-xs text-gray-500 mb-1">Last Updated</p>
                        <p class="text-xs sm:text-sm font-medium text-gray-900">{{ $complaint->updated_at->format('M d, Y h:i A') }}</p>
                    </div>
                    @endif
                </div>
            </div>

            <!-- Update Complaint Form -->
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 sm:p-6">
                <h2 class="text-base sm:text-lg font-semibold text-gray-900 mb-3 sm:mb-4">Update Complaint</h2>
                <form action="{{ route('supervisor.complaints.update', $complaint->id) }}" method="POST" class="space-y-4">
                    @csrf
                    <div>
                        <label for="status" class="block text-xs sm:text-sm font-medium text-gray-700 mb-2">Status *</label>
                        <select name="status" id="status" required
                                class="w-full text-xs sm:text-sm rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="pending" {{ old('status', $complaint->status) === 'pending' ? 'selected' : '' }}>Pending</option>
                            <option value="in_progress" {{ old('status', $complaint->status) === 'in_progress' ? 'selected' : '' }}>In Progress</option>
                            <option value="resolved" {{ old('status', $complaint->status) === 'resolved' ? 'selected' : '' }}>Resolved</option>
                            <option value="escalated" {{ old('status', $complaint->status) === 'escalated' ? 'selected' : '' }}>Escalated</option>
                        </select>
                    </div>
                    <div>
                        <label for="notes" class="block text-xs sm:text-sm font-medium text-gray-700 mb-2">Additional Notes (Optional)</label>
                        <textarea name="notes" id="notes" rows="4"
                                  class="w-full text-xs sm:text-sm rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                  placeholder="Add any additional notes or updates...">{{ old('notes', $complaint->notes) }}</textarea>
                    </div>
                    <button type="submit" 
                            class="w-full sm:w-auto px-4 py-2 text-xs sm:text-sm font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-700">
                        Update Complaint
                    </button>
                </form>
            </div>

            <!-- Related Visit -->
            @if($complaint->visit)
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 sm:p-6">
                <h2 class="text-base sm:text-lg font-semibold text-gray-900 mb-3 sm:mb-4">Related Visit</h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4">
                    <div>
                        <p class="text-xs text-gray-500 mb-1">Visit Date</p>
                        <p class="text-xs sm:text-sm font-medium text-gray-900">
                            {{ $complaint->visit->scheduled_date ? \Carbon\Carbon::parse($complaint->visit->scheduled_date)->format('M d, Y') : 'N/A' }}
                        </p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 mb-1">Status</p>
                        <span class="px-2 py-1 text-xs font-medium rounded-full 
                            @if($complaint->visit->status === 'completed') bg-green-100 text-green-800
                            @else bg-yellow-100 text-yellow-800
                            @endif">
                            {{ ucfirst($complaint->visit->status) }}
                        </span>
                    </div>
                    @if($complaint->visit->technician)
                    <div>
                        <p class="text-xs text-gray-500 mb-1">Technician</p>
                        <p class="text-xs sm:text-sm font-medium text-gray-900">{{ $complaint->visit->technician->name }}</p>
                    </div>
                    @endif
                    @if($complaint->visit->area)
                    <div>
                        <p class="text-xs text-gray-500 mb-1">Area</p>
                        <p class="text-xs sm:text-sm font-medium text-gray-900">{{ $complaint->visit->area->name }}</p>
                    </div>
                    @endif
                </div>
                <div class="mt-4 pt-4 border-t border-gray-200">
                    <a href="{{ route('supervisor.visits.show', $complaint->visit->id) }}" 
                       class="text-xs sm:text-sm text-indigo-600 hover:text-indigo-900">
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
                <div class="space-y-2">
                    <div>
                        <p class="text-xs text-gray-500 mb-1">Current Status</p>
                        <span class="px-2 py-1 text-xs font-medium rounded-full 
                            @if($complaint->status === 'resolved') bg-green-100 text-green-800
                            @elseif($complaint->status === 'in_progress') bg-blue-100 text-blue-800
                            @elseif($complaint->status === 'escalated') bg-red-100 text-red-800
                            @else bg-yellow-100 text-yellow-800
                            @endif">
                            {{ ucfirst($complaint->status ?? 'pending') }}
                        </span>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 mb-1">Complaint ID</p>
                        <p class="text-xs sm:text-sm font-medium text-gray-900">#{{ $complaint->id }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 mb-1">Visit ID</p>
                        <p class="text-xs sm:text-sm font-medium text-gray-900">#{{ $complaint->visit_id }}</p>
                    </div>
                </div>
            </div>

            <!-- Client Information -->
            @if($complaint->visit && $complaint->visit->subscription && $complaint->visit->subscription->client)
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 sm:p-6">
                <h3 class="text-sm sm:text-base font-semibold text-gray-900 mb-3 sm:mb-4">Client Information</h3>
                <div class="space-y-2">
                    <p class="text-xs sm:text-sm font-medium text-gray-900">{{ $complaint->visit->subscription->client->name }}</p>
                    <p class="text-xs text-gray-500">{{ $complaint->visit->subscription->client->email }}</p>
                    @if($complaint->visit->subscription->client->phone)
                        <p class="text-xs text-gray-500">{{ $complaint->visit->subscription->client->phone }}</p>
                    @endif
                </div>
            </div>
            @endif
        </div>
    </div>
</x-supervisor-layout>

