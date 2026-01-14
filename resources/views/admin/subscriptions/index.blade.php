<x-admin-layout>
    <div class="space-y-6">
        <h1 class="text-xl font-medium text-gray-900 mb-6">
            Subscriptions Management
        </h1>
        <!-- Filters -->
        <div class="bg-white shadow rounded-lg p-4 mb-6">
            <form method="GET" action="{{ route('admin.subscriptions.index') }}" class="flex gap-4">
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Search by client name or email..." class="flex-1 rounded-md border-gray-300">
                <select name="payment_status" class="rounded-md border-gray-300">
                    <option value="">All Statuses</option>
                    <option value="pending" {{ request('payment_status') == 'pending' ? 'selected' : '' }}>Pending</option>
                    <option value="paid" {{ request('payment_status') == 'paid' ? 'selected' : '' }}>Paid</option>
                    <option value="failed" {{ request('payment_status') == 'failed' ? 'selected' : '' }}>Failed</option>
                </select>
                <select name="plan" class="rounded-md border-gray-300">
                    <option value="">All Plans</option>
                    <option value="1_month" {{ request('plan') == '1_month' ? 'selected' : '' }}>1 Month</option>
                    <option value="3_month" {{ request('plan') == '3_month' ? 'selected' : '' }}>3 Months</option>
                    <option value="6_month" {{ request('plan') == '6_month' ? 'selected' : '' }}>6 Months</option>
                    <option value="12_month" {{ request('plan') == '12_month' ? 'selected' : '' }}>12 Months</option>
                </select>
                <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700">Filter</button>
                <a href="{{ route('admin.subscriptions.index') }}" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300">Clear</a>
            </form>
        </div>

        <!-- Success Message -->
        @if(session('success'))
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                {{ session('success') }}
            </div>
        @endif

        <!-- Subscriptions Table -->
        <div class="bg-white shadow rounded-lg overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Client</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Plan</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Visits</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Dates</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($subscriptions as $subscription)
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900">{{ $subscription->client->name ?? 'N/A' }}</div>
                                <div class="text-sm text-gray-500">{{ $subscription->client->email ?? 'N/A' }}</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                {{ str_replace('_', ' ', ucfirst($subscription->plan)) }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                AED {{ number_format($subscription->amount, 2) }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                    {{ $subscription->payment_status == 'paid' ? 'bg-green-100 text-green-800' : 
                                       ($subscription->payment_status == 'pending' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800') }}">
                                    {{ ucfirst($subscription->payment_status) }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                {{ $subscription->completed_visits ?? 0 }} / {{ $subscription->total_visits ?? 0 }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <div>{{ $subscription->start_date ? \Carbon\Carbon::parse($subscription->start_date)->format('M d, Y') : 'N/A' }}</div>
                                <div class="text-xs">{{ $subscription->end_date ? \Carbon\Carbon::parse($subscription->end_date)->format('M d, Y') : 'N/A' }}</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <a href="{{ route('admin.subscriptions.show', $subscription) }}" class="text-indigo-600 hover:text-indigo-900 mr-3">View</a>
                                <a href="{{ route('admin.subscriptions.edit', $subscription) }}" class="text-yellow-600 hover:text-yellow-900">Edit</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-4 text-center text-sm text-gray-500">No subscriptions found</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div class="mt-4">
            {{ $subscriptions->links() }}
        </div>
    </div>
</x-admin-layout>

