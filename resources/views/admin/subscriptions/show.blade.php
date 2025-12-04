<x-admin-layout>
    <h2 class="font-semibold text-2xl text-gray-800 leading-tight mb-6">
            Subscription Details
        </h2>

    <div class="space-y-6">
        <div class="bg-white shadow rounded-lg p-6 space-y-6">
            @if(session('success'))
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">
                    {{ session('success') }}
                </div>
            @endif

            <!-- Client Info -->
            <div>
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Client Information</h3>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <p class="text-sm text-gray-500">Name</p>
                        <p class="text-sm font-medium text-gray-900">{{ $subscription->client->name ?? 'N/A' }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Email</p>
                        <p class="text-sm font-medium text-gray-900">{{ $subscription->client->email ?? 'N/A' }}</p>
                    </div>
                </div>
            </div>

            <!-- Subscription Details -->
            <div>
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Subscription Details</h3>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <p class="text-sm text-gray-500">Plan</p>
                        <p class="text-sm font-medium text-gray-900">{{ str_replace('_', ' ', ucfirst($subscription->plan)) }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Amount</p>
                        <p class="text-sm font-medium text-gray-900">AED {{ number_format($subscription->amount, 2) }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Payment Status</p>
                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                            {{ $subscription->payment_status == 'paid' ? 'bg-green-100 text-green-800' : 
                               ($subscription->payment_status == 'pending' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800') }}">
                            {{ ucfirst($subscription->payment_status) }}
                        </span>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Visits</p>
                        <p class="text-sm font-medium text-gray-900">{{ $subscription->completed_visits }} / {{ $subscription->total_visits }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Start Date</p>
                        <p class="text-sm font-medium text-gray-900">{{ $subscription->start_date->format('M d, Y') }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">End Date</p>
                        <p class="text-sm font-medium text-gray-900">{{ $subscription->end_date->format('M d, Y') }}</p>
                    </div>
                </div>
            </div>

            <!-- Visits List -->
            @if($subscription->visits && $subscription->visits->count() > 0)
            <div>
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Visits</h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Technician</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach($subscription->visits as $visit)
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        {{ $visit->scheduled_date ? \Carbon\Carbon::parse($visit->scheduled_date)->format('M d, Y') : 'N/A' }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        {{ $visit->technician->name ?? 'Unassigned' }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                            {{ $visit->status == 'completed' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800' }}">
                                            {{ ucfirst($visit->status) }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            @endif

            <div class="pt-4 flex gap-4">
                <a href="{{ route('admin.subscriptions.edit', $subscription) }}" class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700">Edit</a>
                <a href="{{ route('admin.subscriptions.index') }}" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300">Back to Subscriptions</a>
            </div>
        </div>
    </div>
</x-admin-layout>

