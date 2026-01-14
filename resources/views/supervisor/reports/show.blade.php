@php
    use Illuminate\Support\Facades\Storage;
@endphp
<x-supervisor-layout>
    <!-- Page Header -->
    <div class="mb-4 sm:mb-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 sm:gap-0">
            <div>
                <h1 class="text-lg sm:text-xl font-medium text-gray-900">Report Details</h1>
                <p class="mt-1 text-xs sm:text-sm text-gray-500">View complete report information and review.</p>
            </div>
            <div class="flex gap-2">
                <a href="{{ route('supervisor.reports.index') }}" class="text-xs sm:text-sm text-gray-600 hover:text-gray-900">
                    ← Back to Reports
                </a>
                @if($report->status === 'pending')
                    <a href="{{ route('supervisor.reports.review', $report->id) }}" 
                       class="px-3 sm:px-4 py-1.5 sm:py-2 text-xs sm:text-sm font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-700">
                        Review & Finalize
                    </a>
                @endif
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 sm:gap-6">
        <!-- Main Content -->
        <div class="lg:col-span-2 space-y-4 sm:space-y-6">
            <!-- Report Status -->
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 sm:p-6">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 sm:gap-0 mb-3 sm:mb-4">
                    <h2 class="text-base sm:text-lg font-semibold text-gray-900">Report Status</h2>
                    <span class="px-3 py-1 text-xs sm:text-sm font-medium rounded-full 
                        @if($report->status === 'approved') bg-green-100 text-green-800
                        @elseif($report->status === 'pending') bg-yellow-100 text-yellow-800
                        @else bg-gray-100 text-gray-800
                        @endif">
                        {{ ucfirst($report->status ?? 'pending') }}
                    </span>
                </div>
                @if($report->approved_at)
                    <p class="text-xs sm:text-sm text-gray-600">Approved on {{ $report->approved_at->format('M d, Y h:i A') }}</p>
                @endif
            </div>

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
                    <div>
                        <p class="text-xs text-gray-500 mb-1">Status</p>
                        <span class="px-2 py-1 text-xs font-medium rounded-full 
                            @if($report->visit->status === 'completed') bg-green-100 text-green-800
                            @else bg-yellow-100 text-yellow-800
                            @endif">
                            {{ ucfirst($report->visit->status) }}
                        </span>
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
                    @if($report->visit->area)
                    <div>
                        <p class="text-xs text-gray-500 mb-1">Area</p>
                        <p class="text-xs sm:text-sm font-medium text-gray-900">{{ $report->visit->area->name }}</p>
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
            @if($report->supervisor_notes)
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 sm:p-6">
                <h2 class="text-base sm:text-lg font-semibold text-gray-900 mb-3 sm:mb-4">Supervisor Notes</h2>
                <div class="bg-blue-50 rounded-lg p-3 sm:p-4">
                    <p class="text-xs sm:text-sm text-gray-700 whitespace-pre-wrap">{{ $report->supervisor_notes }}</p>
                </div>
            </div>
            @endif

            <!-- Recommended Products -->
            @if($report->recommended_products && is_array($report->recommended_products) && count($report->recommended_products) > 0)
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 sm:p-6">
                <h2 class="text-base sm:text-lg font-semibold text-gray-900 mb-3 sm:mb-4">Recommended Products</h2>
                <div class="space-y-2">
                    @php
                        $products = \App\Models\Product::whereIn('id', $report->recommended_products)->get();
                    @endphp
                    @foreach($products as $product)
                        <div class="flex items-center justify-between p-2 bg-gray-50 rounded-lg">
                            <div>
                                <p class="text-xs sm:text-sm font-medium text-gray-900">{{ $product->name }}</p>
                                <p class="text-xs text-gray-500">AED {{ number_format($product->price, 2) }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
            @endif

            <!-- Visit Photos -->
            @if($report->visit && $report->visit->photos && $report->visit->photos->count() > 0)
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 sm:p-6">
                <h2 class="text-base sm:text-lg font-semibold text-gray-900 mb-3 sm:mb-4">Visit Photos</h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4">
                    @foreach($report->visit->photos as $photo)
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
            <!-- Supervisor Information -->
            @if($report->supervisor)
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 sm:p-6">
                <h3 class="text-sm sm:text-base font-semibold text-gray-900 mb-3 sm:mb-4">Reviewed By</h3>
                <div class="space-y-2">
                    <p class="text-xs sm:text-sm font-medium text-gray-900">{{ $report->supervisor->name }}</p>
                    <p class="text-xs text-gray-500">{{ $report->supervisor->email }}</p>
                </div>
            </div>
            @endif

            <!-- Report Metadata -->
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 sm:p-6">
                <h3 class="text-sm sm:text-base font-semibold text-gray-900 mb-3 sm:mb-4">Report Information</h3>
                <div class="space-y-2">
                    <div>
                        <p class="text-xs text-gray-500">Created</p>
                        <p class="text-xs sm:text-sm font-medium text-gray-900">{{ $report->created_at->format('M d, Y h:i A') }}</p>
                    </div>
                    @if($report->updated_at && $report->updated_at != $report->created_at)
                    <div>
                        <p class="text-xs text-gray-500">Last Updated</p>
                        <p class="text-xs sm:text-sm font-medium text-gray-900">{{ $report->updated_at->format('M d, Y h:i A') }}</p>
                    </div>
                    @endif
                </div>
            </div>

            <!-- Quick Actions -->
            @if($report->status === 'pending')
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 sm:p-6">
                <h3 class="text-sm sm:text-base font-semibold text-gray-900 mb-3 sm:mb-4">Quick Actions</h3>
                <div class="space-y-2">
                    <a href="{{ route('supervisor.reports.review', $report->id) }}" 
                       class="block w-full text-center px-3 sm:px-4 py-2 text-xs sm:text-sm font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-700">
                        Review & Finalize
                    </a>
                </div>
            </div>
            @endif
        </div>
    </div>
</x-supervisor-layout>

