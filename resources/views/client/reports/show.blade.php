@php
    use Illuminate\Support\Facades\Storage;
@endphp
<x-client-layout>
    <!-- Page Header -->
    <div class="mb-4 sm:mb-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 sm:gap-0">
            <div>
                <h1 class="text-lg sm:text-xl font-medium text-gray-900">Report Details</h1>
                <p class="mt-1 text-xs sm:text-sm text-gray-500">View complete report information and recommendations.</p>
            </div>
            <a href="{{ route('client.reports.index') }}" class="text-xs sm:text-sm text-gray-600 hover:text-gray-900">
                ← Back to Reports
            </a>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 sm:gap-6">
        <!-- Main Content -->
        <div class="lg:col-span-2 space-y-4 sm:space-y-6">
            <!-- Report Status -->
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 sm:p-6">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 sm:gap-0 mb-3 sm:mb-4">
                    <h2 class="text-base sm:text-lg font-semibold text-gray-900">Report Status</h2>
                    <span class="px-3 py-1 text-sm font-medium rounded-full 
                        @if($report->status === 'approved') bg-green-100 text-green-800
                        @elseif($report->status === 'pending') bg-yellow-100 text-yellow-800
                        @else bg-gray-100 text-gray-800
                        @endif">
                        {{ ucfirst($report->status ?? 'pending') }}
                    </span>
                </div>
                @if($report->approved_at)
                    <p class="text-sm text-gray-600">Approved on {{ $report->approved_at->format('M d, Y h:i A') }}</p>
                @endif
            </div>

            <!-- Visit Information -->
            @if($report->visit)
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 sm:p-6">
                <h2 class="text-base sm:text-lg font-semibold text-gray-900 mb-3 sm:mb-4">Visit Information</h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4">
                    <div>
                        <p class="text-xs text-gray-500 mb-1">Visit Date</p>
                        <p class="text-sm font-medium text-gray-900">
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
                    @if($report->visit->technician)
                    <div>
                        <p class="text-xs text-gray-500 mb-1">Technician</p>
                        <p class="text-sm font-medium text-gray-900">{{ $report->visit->technician->name }}</p>
                    </div>
                    @endif
                    @if($report->visit->area)
                    <div>
                        <p class="text-xs text-gray-500 mb-1">Area</p>
                        <p class="text-sm font-medium text-gray-900">{{ $report->visit->area->name }}</p>
                    </div>
                    @endif
                </div>
                <div class="mt-4 pt-4 border-t border-gray-200">
                    <a href="{{ route('client.visits.show', $report->visit->id) }}" 
                       class="text-sm text-indigo-600 hover:text-indigo-900">
                        View Visit Details →
                    </a>
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

            <!-- General Notes -->
            @if($report->notes)
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 sm:p-6">
                <h2 class="text-base sm:text-lg font-semibold text-gray-900 mb-3 sm:mb-4">Additional Notes</h2>
                <div class="bg-gray-50 rounded-lg p-3 sm:p-4">
                    <p class="text-xs sm:text-sm text-gray-700 whitespace-pre-wrap">{{ $report->notes }}</p>
                </div>
            </div>
            @endif

            <!-- Recommendations -->
            @if($report->recommendations && is_array($report->recommendations) && count($report->recommendations) > 0)
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 sm:p-6">
                <h2 class="text-base sm:text-lg font-semibold text-gray-900 mb-3 sm:mb-4">Recommendations</h2>
                <ul class="space-y-2">
                    @foreach($report->recommendations as $recommendation)
                        <li class="flex items-start gap-2">
                            <svg class="w-5 h-5 text-green-600 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <p class="text-sm text-gray-700">{{ $recommendation }}</p>
                        </li>
                    @endforeach
                </ul>
            </div>
            @endif

            <!-- Recommended Products -->
            @if($report->recommended_products && is_array($report->recommended_products) && count($report->recommended_products) > 0)
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 sm:p-6">
                <h2 class="text-base sm:text-lg font-semibold text-gray-900 mb-3 sm:mb-4">Recommended Products</h2>
                <div class="space-y-3">
                    @foreach($report->recommended_products as $productId)
                        @php
                            $product = \App\Models\Product::find($productId);
                        @endphp
                        @if($product)
                            <div class="flex items-center gap-4 p-3 bg-gray-50 rounded-lg">
                                @if($product->image)
                                    <img src="{{ Storage::disk('public')->exists($product->image) ? asset('storage/' . $product->image) : asset('images/placeholder.png') }}" 
                                         alt="{{ $product->name }}" 
                                         class="w-16 h-16 object-cover rounded">
                                @else
                                    <div class="w-16 h-16 bg-gray-200 rounded flex items-center justify-center">
                                        <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                        </svg>
                                    </div>
                                @endif
                                <div class="flex-1">
                                    <p class="text-sm font-medium text-gray-900">{{ $product->name }}</p>
                                    <p class="text-sm font-semibold text-indigo-600">AED {{ number_format($product->price, 2) }}</p>
                                </div>
                            </div>
                        @endif
                    @endforeach
                </div>
                <p class="text-xs text-gray-500 mt-4">Note: You can purchase these products through the mobile app.</p>
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
                <h3 class="text-sm sm:text-base font-semibold text-gray-900 mb-3 sm:mb-4">Report By</h3>
                <div class="space-y-3">
                    <div>
                        <p class="text-sm font-medium text-gray-900">{{ $report->supervisor->name }}</p>
                        <p class="text-xs text-gray-500">{{ $report->supervisor->email }}</p>
                        @if($report->supervisor->phone)
                            <p class="text-xs text-gray-500">{{ $report->supervisor->phone }}</p>
                        @endif
                    </div>
                </div>
            </div>
            @endif

            <!-- Report Metadata -->
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 sm:p-6">
                <h3 class="text-sm sm:text-base font-semibold text-gray-900 mb-3 sm:mb-4">Report Information</h3>
                <div class="space-y-3">
                    <div>
                        <p class="text-xs text-gray-500">Created</p>
                        <p class="text-sm font-medium text-gray-900">{{ $report->created_at->format('M d, Y h:i A') }}</p>
                    </div>
                    @if($report->updated_at && $report->updated_at != $report->created_at)
                    <div>
                        <p class="text-xs text-gray-500">Last Updated</p>
                        <p class="text-sm font-medium text-gray-900">{{ $report->updated_at->format('M d, Y h:i A') }}</p>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-client-layout>

