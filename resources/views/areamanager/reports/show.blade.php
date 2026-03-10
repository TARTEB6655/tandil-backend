@php
    use App\Services\ProfilePictureUploadService;
@endphp
<x-areamanager-layout>
    <!-- Page Header -->
    <div class="mb-4 sm:mb-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 sm:gap-0">
            <div>
                <h1 class="text-lg sm:text-xl font-medium text-gray-900">Report Details</h1>
                <p class="mt-1 text-xs sm:text-sm text-gray-500">View complete report information.</p>
            </div>
            <a href="{{ route('areamanager.reports.index') }}" class="text-xs sm:text-sm text-gray-600 hover:text-gray-900">
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
                    <span class="px-3 py-1 text-xs sm:text-sm font-medium rounded-full 
                        @if($report->status === 'approved') bg-green-100 text-green-800
                        @elseif($report->status === 'pending') bg-yellow-100 text-yellow-800
                        @elseif($report->status === 'sent_to_client') bg-sky-100 text-sky-800
                        @else bg-gray-100 text-gray-800
                        @endif">
                        {{ \Illuminate\Support\Str::title(str_replace('_', ' ', $report->status ?? 'pending')) }}
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

            <!-- Visit Photos (Before & After) -->
            @if($report->visit)
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 sm:p-6">
                <h2 class="text-base sm:text-lg font-semibold text-gray-900 mb-3 sm:mb-4">Visit Photos</h2>
                @php
                    $photos = $report->visit->photos ?? collect();
                    $beforePhoto = $photos->firstWhere('type', 'before');
                    $afterPhoto = $photos->firstWhere('type', 'after');
                @endphp
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 sm:gap-6">
                    <div class="rounded-lg border border-gray-200 overflow-hidden bg-gray-50">
                        <div class="aspect-[4/3] flex items-center justify-center min-h-[180px]">
                            @if($beforePhoto && ($beforeUrl = ProfilePictureUploadService::fullUrl($beforePhoto->photo_path)))
                                <img src="{{ $beforeUrl }}" alt="Before" class="w-full h-full object-cover" onerror="this.onerror=null; this.src='data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 200 150\'%3E%3Crect fill=\'%23e5e7eb\' width=\'200\' height=\'150\'/%3E%3Ctext fill=\'%239ca3af\' x=\'50%25\' y=\'50%25\' dominant-baseline=\'middle\' text-anchor=\'middle\' font-size=\'14\'%3ENo image%3C/text%3E%3C/svg%3E';">
                            @else
                                <div class="w-full h-full flex flex-col items-center justify-center text-gray-400 p-4">
                                    <svg class="w-12 h-12 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                    <span class="text-sm">No before photo</span>
                                </div>
                            @endif
                        </div>
                        <div class="px-3 py-2 bg-gray-100 border-t border-gray-200">
                            <span class="text-xs font-medium text-gray-600">Before</span>
                        </div>
                    </div>
                    <div class="rounded-lg border border-gray-200 overflow-hidden bg-gray-50">
                        <div class="aspect-[4/3] flex items-center justify-center min-h-[180px]">
                            @if($afterPhoto && ($afterUrl = ProfilePictureUploadService::fullUrl($afterPhoto->photo_path)))
                                <img src="{{ $afterUrl }}" alt="After" class="w-full h-full object-cover" onerror="this.onerror=null; this.src='data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 200 150\'%3E%3Crect fill=\'%23e5e7eb\' width=\'200\' height=\'150\'/%3E%3Ctext fill=\'%239ca3af\' x=\'50%25\' y=\'50%25\' dominant-baseline=\'middle\' text-anchor=\'middle\' font-size=\'14\'%3ENo image%3C/text%3E%3C/svg%3E';">
                            @else
                                <div class="w-full h-full flex flex-col items-center justify-center text-gray-400 p-4">
                                    <svg class="w-12 h-12 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                    <span class="text-sm">No after photo</span>
                                </div>
                            @endif
                        </div>
                        <div class="px-3 py-2 bg-gray-100 border-t border-gray-200">
                            <span class="text-xs font-medium text-gray-600">After</span>
                        </div>
                    </div>
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
        </div>
    </div>
</x-areamanager-layout>

