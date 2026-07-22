@php
    $fieldWorker = $reportData['field_worker'] ?? null;
    $supervisor = $reportData['supervisor'] ?? null;
    $visitInfo = $reportData['visit_information'] ?? [];
    $recommendations = $reportData['recommendations'] ?? [];
    $recommendedProducts = $reportData['recommended_products'] ?? [];
    $beforePhotos = $reportData['before_photos'] ?? [];
    $afterPhotos = $reportData['after_photos'] ?? [];
    $fieldPhotos = $reportData['field_photos'] ?? [];
    $galleryPhotos = (!empty($beforePhotos) || !empty($afterPhotos)) ? [] : $fieldPhotos;

    $fmtDate = function ($value, $format = 'M d, Y') {
        if (empty($value)) {
            return null;
        }
        try {
            return \Carbon\Carbon::parse($value)->format($format);
        } catch (\Throwable $e) {
            return null;
        }
    };
@endphp
<x-client-layout>
    <!-- Page Header -->
    <div class="mb-4 sm:mb-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 sm:gap-0">
            <div>
                <h1 class="text-lg sm:text-xl font-medium text-gray-900">Service Report</h1>
                <p class="mt-1 text-xs sm:text-sm text-gray-500">Order #{{ $order->id }}</p>
            </div>
            <a href="{{ route('client.orders.show', $order->id) }}" class="text-xs sm:text-sm text-gray-600 hover:text-gray-900">
                ← Back to Order
            </a>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 sm:gap-6">
        <!-- Main Content -->
        <div class="lg:col-span-2 space-y-4 sm:space-y-6">
            <!-- Report Status -->
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 sm:p-6">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 sm:gap-0">
                    <h2 class="text-base sm:text-lg font-semibold text-gray-900">Report Status</h2>
                    <span class="px-3 py-1 text-sm font-medium rounded-full bg-green-100 text-green-800">
                        {{ ucfirst(str_replace('_', ' ', (string) ($reportData['status'] ?? 'sent_to_client'))) }}
                    </span>
                </div>
                @if($fmtDate($reportData['submitted_at'] ?? null, 'M d, Y h:i A'))
                    <p class="mt-2 text-sm text-gray-600">Shared on {{ $fmtDate($reportData['submitted_at'], 'M d, Y h:i A') }}</p>
                @endif
            </div>

            <!-- Visit Information -->
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 sm:p-6">
                <h2 class="text-base sm:text-lg font-semibold text-gray-900 mb-3 sm:mb-4">Visit Information</h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4">
                    @if(!empty($visitInfo['service_name']))
                    <div>
                        <p class="text-xs text-gray-500 mb-1">Service</p>
                        <p class="text-sm font-medium text-gray-900">{{ $visitInfo['service_name'] }}</p>
                    </div>
                    @endif
                    @if(!empty($visitInfo['location']))
                    <div>
                        <p class="text-xs text-gray-500 mb-1">Location</p>
                        <p class="text-sm font-medium text-gray-900">{{ $visitInfo['location'] }}</p>
                    </div>
                    @endif
                    <div>
                        <p class="text-xs text-gray-500 mb-1">Scheduled Date</p>
                        <p class="text-sm font-medium text-gray-900">{{ $fmtDate($visitInfo['scheduled_date'] ?? null) ?? 'N/A' }}</p>
                    </div>
                    @if($fmtDate($visitInfo['completed_at'] ?? null, 'M d, Y h:i A'))
                    <div>
                        <p class="text-xs text-gray-500 mb-1">Completed</p>
                        <p class="text-sm font-medium text-gray-900">{{ $fmtDate($visitInfo['completed_at'], 'M d, Y h:i A') }}</p>
                    </div>
                    @endif
                </div>
            </div>

            <!-- Technician Notes -->
            @if(!empty($reportData['technician_notes']))
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 sm:p-6">
                <h2 class="text-base sm:text-lg font-semibold text-gray-900 mb-3 sm:mb-4">Technician Notes</h2>
                <div class="bg-gray-50 rounded-lg p-3 sm:p-4">
                    <p class="text-xs sm:text-sm text-gray-700 whitespace-pre-wrap">{{ $reportData['technician_notes'] }}</p>
                </div>
            </div>
            @endif

            <!-- Supervisor Notes -->
            @if(!empty($reportData['supervisor_notes']))
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 sm:p-6">
                <h2 class="text-base sm:text-lg font-semibold text-gray-900 mb-3 sm:mb-4">Supervisor Notes</h2>
                <div class="bg-blue-50 rounded-lg p-3 sm:p-4">
                    <p class="text-xs sm:text-sm text-gray-700 whitespace-pre-wrap">{{ $reportData['supervisor_notes'] }}</p>
                </div>
            </div>
            @endif

            <!-- Additional Notes -->
            @if(!empty($reportData['notes']))
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 sm:p-6">
                <h2 class="text-base sm:text-lg font-semibold text-gray-900 mb-3 sm:mb-4">Additional Notes</h2>
                <div class="bg-gray-50 rounded-lg p-3 sm:p-4">
                    <p class="text-xs sm:text-sm text-gray-700 whitespace-pre-wrap">{{ $reportData['notes'] }}</p>
                </div>
            </div>
            @endif

            <!-- Recommendations -->
            @if(!empty($recommendations))
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 sm:p-6">
                <h2 class="text-base sm:text-lg font-semibold text-gray-900 mb-3 sm:mb-4">Recommendations</h2>
                <ul class="space-y-2">
                    @foreach($recommendations as $recommendation)
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
            @if(!empty($recommendedProducts))
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 sm:p-6">
                <h2 class="text-base sm:text-lg font-semibold text-gray-900 mb-3 sm:mb-4">Recommended Products</h2>
                <div class="space-y-3">
                    @foreach($recommendedProducts as $product)
                        <div class="flex items-center justify-between gap-4 p-3 bg-gray-50 rounded-lg">
                            <div>
                                <p class="text-sm font-medium text-gray-900">{{ $product['name'] ?? 'Product' }}</p>
                                @if(!empty($product['job_duration']))
                                    <p class="text-xs text-gray-500">Duration: {{ $product['job_duration'] }}</p>
                                @endif
                            </div>
                            <p class="text-sm font-semibold text-indigo-600 flex-shrink-0">AED {{ number_format((float) ($product['price'] ?? 0), 2) }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
            @endif

            <!-- Before Photos -->
            @if(!empty($beforePhotos))
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 sm:p-6">
                <h2 class="text-base sm:text-lg font-semibold text-gray-900 mb-3 sm:mb-4">Before</h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4">
                    @foreach($beforePhotos as $photo)
                        @if(!empty($photo['photo_url']))
                        <a href="{{ $photo['photo_url'] }}" target="_blank" rel="noopener" class="block">
                            <img src="{{ $photo['photo_url'] }}" alt="Before photo" class="w-full h-40 sm:h-48 object-cover rounded-lg border border-gray-200">
                        </a>
                        @endif
                    @endforeach
                </div>
            </div>
            @endif

            <!-- After Photos -->
            @if(!empty($afterPhotos))
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 sm:p-6">
                <h2 class="text-base sm:text-lg font-semibold text-gray-900 mb-3 sm:mb-4">After</h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4">
                    @foreach($afterPhotos as $photo)
                        @if(!empty($photo['photo_url']))
                        <a href="{{ $photo['photo_url'] }}" target="_blank" rel="noopener" class="block">
                            <img src="{{ $photo['photo_url'] }}" alt="After photo" class="w-full h-40 sm:h-48 object-cover rounded-lg border border-gray-200">
                        </a>
                        @endif
                    @endforeach
                </div>
            </div>
            @endif

            <!-- Other Photos (when not tagged before/after) -->
            @if(!empty($galleryPhotos))
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 sm:p-6">
                <h2 class="text-base sm:text-lg font-semibold text-gray-900 mb-3 sm:mb-4">Visit Photos</h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4">
                    @foreach($galleryPhotos as $photo)
                        @if(!empty($photo['photo_url']))
                        <a href="{{ $photo['photo_url'] }}" target="_blank" rel="noopener" class="block relative">
                            <img src="{{ $photo['photo_url'] }}" alt="Visit photo" class="w-full h-40 sm:h-48 object-cover rounded-lg border border-gray-200">
                            @if(!empty($photo['type']))
                                <span class="absolute bottom-2 left-2 bg-black bg-opacity-50 text-white text-xs px-2 py-1 rounded">{{ ucfirst($photo['type']) }}</span>
                            @endif
                        </a>
                        @endif
                    @endforeach
                </div>
            </div>
            @endif
        </div>

        <!-- Sidebar -->
        <div class="space-y-4 sm:space-y-6">
            @if($fieldWorker)
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 sm:p-6">
                <h3 class="text-sm sm:text-base font-semibold text-gray-900 mb-3 sm:mb-4">Technician</h3>
                <div class="flex items-center gap-3">
                    @if(!empty($fieldWorker['profile_picture_url']))
                        <img src="{{ $fieldWorker['profile_picture_url'] }}" alt="{{ $fieldWorker['name'] ?? 'Technician' }}" class="w-10 h-10 rounded-full object-cover">
                    @else
                        <span class="w-10 h-10 rounded-full bg-indigo-100 text-indigo-700 flex items-center justify-center text-sm font-semibold">
                            {{ strtoupper(mb_substr((string) ($fieldWorker['name'] ?? '?'), 0, 1)) }}
                        </span>
                    @endif
                    <div>
                        <p class="text-sm font-medium text-gray-900">{{ $fieldWorker['name'] ?? 'Technician' }}</p>
                        @if(!empty($fieldWorker['phone']))
                            <p class="text-xs text-gray-500">{{ $fieldWorker['phone'] }}</p>
                        @endif
                    </div>
                </div>
            </div>
            @endif

            @if($supervisor)
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 sm:p-6">
                <h3 class="text-sm sm:text-base font-semibold text-gray-900 mb-3 sm:mb-4">Report Shared By</h3>
                <p class="text-sm font-medium text-gray-900">{{ $supervisor['name'] ?? 'Supervisor' }}</p>
            </div>
            @endif

            @if(strtolower((string) $order->order_status) === 'completed')
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 sm:p-6">
                <h3 class="text-sm sm:text-base font-semibold text-gray-900 mb-3">Confirm Delivery</h3>
                <p class="text-xs text-gray-500 mb-3">If everything looks good, confirm that you received this order.</p>
                <form action="{{ route('client.orders.mark-delivered', $order->id) }}" method="POST"
                      onsubmit="return confirm('Confirm that you have received this order?');">
                    @csrf
                    <button type="submit" class="w-full px-4 py-2.5 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors text-sm font-medium">
                        Mark as Delivered
                    </button>
                </form>
            </div>
            @endif
        </div>
    </div>
</x-client-layout>
