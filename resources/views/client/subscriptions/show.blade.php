@php
    use Illuminate\Support\Facades\Storage;
@endphp
<x-client-layout>
    <!-- Page Header -->
    <div class="mb-4 sm:mb-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 sm:gap-0">
            <div>
                <h1 class="text-lg sm:text-xl font-medium text-gray-900">Subscription Details</h1>
                <p class="mt-1 text-xs sm:text-sm text-gray-500">View complete subscription information and all visits.</p>
            </div>
            <a href="{{ route('client.subscriptions.index') }}" class="text-xs sm:text-sm text-gray-600 hover:text-gray-900">
                ← Back to Subscriptions
            </a>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 sm:gap-6">
        <!-- Main Content -->
        <div class="lg:col-span-2 space-y-4 sm:space-y-6">
            <!-- Subscription Information -->
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 sm:p-6">
                <h2 class="text-base sm:text-lg font-semibold text-gray-900 mb-3 sm:mb-4">Subscription Information</h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4">
                    <div>
                        <p class="text-xs text-gray-500 mb-1">Plan</p>
                        <p class="text-sm font-medium text-gray-900">{{ ucfirst(str_replace('_', ' ', $subscription->plan)) }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 mb-1">Payment Status</p>
                        <span class="px-2 py-1 text-xs font-medium rounded-full 
                            {{ $subscription->payment_status === 'paid' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800' }}">
                            {{ ucfirst($subscription->payment_status) }}
                        </span>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 mb-1">Start Date</p>
                        <p class="text-sm font-medium text-gray-900">{{ $subscription->start_date->format('M d, Y') }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 mb-1">End Date</p>
                        <p class="text-sm font-medium text-gray-900">{{ $subscription->end_date->format('M d, Y') }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 mb-1">Amount</p>
                        <p class="text-sm font-medium text-gray-900">AED {{ number_format($subscription->amount, 2) }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 mb-1">Visits</p>
                        <p class="text-sm font-medium text-gray-900">
                            {{ $subscription->visits->count() }} / {{ $subscription->total_visits }}
                        </p>
                    </div>
                    @if($subscription->payment_reference)
                    <div>
                        <p class="text-xs text-gray-500 mb-1">Payment Reference</p>
                        <p class="text-sm font-medium text-gray-900">{{ $subscription->payment_reference }}</p>
                    </div>
                    @endif
                    @if($subscription->paid_at)
                    <div>
                        <p class="text-xs text-gray-500 mb-1">Paid At</p>
                        <p class="text-sm font-medium text-gray-900">{{ $subscription->paid_at->format('M d, Y h:i A') }}</p>
                    </div>
                    @endif
                </div>
            </div>

            <!-- All Visits -->
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 sm:p-6">
                <h2 class="text-base sm:text-lg font-semibold text-gray-900 mb-3 sm:mb-4">All Visits ({{ $subscription->visits->count() }})</h2>
                <div class="space-y-3">
                    @forelse($subscription->visits as $visit)
                        <div class="border border-gray-200 rounded-lg p-4 hover:bg-gray-50 transition-colors">
                            <div class="flex items-center justify-between">
                                <div class="flex-1">
                                    <div class="flex items-center gap-3 mb-2">
                                        <p class="text-sm font-medium text-gray-900">
                                            {{ $visit->scheduled_date ? \Carbon\Carbon::parse($visit->scheduled_date)->format('M d, Y') : 'N/A' }}
                                        </p>
                                        <span class="px-2 py-1 text-xs font-medium rounded-full 
                                            @if($visit->status === 'completed') bg-green-100 text-green-800
                                            @elseif($visit->status === 'started' || $visit->status === 'accepted') bg-blue-100 text-blue-800
                                            @elseif($visit->status === 'pending') bg-yellow-100 text-yellow-800
                                            @else bg-gray-100 text-gray-800
                                            @endif">
                                            {{ ucwords(str_replace('_', ' ', $visit->status ?? '')) }}
                                        </span>
                                    </div>
                                    <div class="flex items-center gap-4 text-xs text-gray-500">
                                        @if($visit->technician)
                                            <span>Technician: {{ $visit->technician->name }}</span>
                                        @endif
                                        @if($visit->supervisor)
                                            <span>Supervisor: {{ $visit->supervisor->name }}</span>
                                        @endif
                                        @if($visit->area)
                                            <span>Area: {{ $visit->area->name }}</span>
                                        @endif
                                    </div>
                                </div>
                                <a href="{{ route('client.visits.show', $visit->id) }}" 
                                   class="text-sm text-indigo-600 hover:text-indigo-900 font-medium">
                                    View →
                                </a>
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-gray-500 text-center py-4">No visits scheduled yet.</p>
                    @endforelse
                </div>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="space-y-4 sm:space-y-6">
            <!-- Subscription Status -->
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 sm:p-6">
                <h3 class="text-sm sm:text-base font-semibold text-gray-900 mb-3 sm:mb-4">Status</h3>
                @php
                    $isActive = $subscription->payment_status === 'paid' && $subscription->end_date >= now();
                    $daysRemaining = $isActive ? now()->diffInDays($subscription->end_date) : 0;
                @endphp
                <div class="space-y-2">
                    @if($isActive)
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-green-100 text-green-800">
                            Active
                        </span>
                        <p class="text-xs text-gray-500">{{ $daysRemaining }} days remaining</p>
                    @else
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-gray-100 text-gray-800">
                            {{ $subscription->end_date < now() ? 'Expired' : 'Inactive' }}
                        </span>
                    @endif
                </div>
            </div>

            <!-- Progress -->
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 sm:p-6">
                <h3 class="text-sm sm:text-base font-semibold text-gray-900 mb-3 sm:mb-4">Visit Progress</h3>
                <div class="space-y-2">
                    @php
                        $completed = $subscription->visits->where('status', 'completed')->count();
                        $total = $subscription->total_visits;
                        $percentage = $total > 0 ? ($completed / $total) * 100 : 0;
                    @endphp
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-gray-600">Completed</span>
                        <span class="font-medium text-gray-900">{{ $completed }} / {{ $total }}</span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-2">
                        <div class="bg-indigo-600 h-2 rounded-full" style="width: {{ $percentage }}%"></div>
                    </div>
                    <p class="text-xs text-gray-500">{{ round($percentage) }}% complete</p>
                </div>
            </div>
        </div>
    </div>
</x-client-layout>

