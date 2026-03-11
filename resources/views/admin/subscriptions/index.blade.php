<x-admin-layout>
    <div class="space-y-6">
        <h1 class="text-xl font-medium text-gray-900 mb-6">
            {{ __('admin.subscriptions_management') }}
        </h1>
        <!-- Filters -->
        <div class="bg-white shadow rounded-lg p-4 mb-6">
            <form method="GET" action="{{ route('admin.subscriptions.index') }}" class="flex gap-4">
                <input type="text" name="search" value="{{ request('search') }}" placeholder="{{ __('admin.search_by_client_email') }}" class="flex-1 rounded-md border-gray-300">
                <select name="payment_status" class="rounded-md border-gray-300">
                    <option value="">{{ __('admin.all_statuses') }}</option>
                    <option value="pending" {{ request('payment_status') == 'pending' ? 'selected' : '' }}>{{ __('admin.pending') }}</option>
                    <option value="paid" {{ request('payment_status') == 'paid' ? 'selected' : '' }}>{{ __('admin.paid') }}</option>
                    <option value="failed" {{ request('payment_status') == 'failed' ? 'selected' : '' }}>{{ __('admin.failed') }}</option>
                </select>
                <select name="plan" class="rounded-md border-gray-300">
                    <option value="">{{ __('admin.all_plans') }}</option>
                    <option value="1_month" {{ request('plan') == '1_month' ? 'selected' : '' }}>{{ __('admin.plan_1_month') }}</option>
                    <option value="3_month" {{ request('plan') == '3_month' ? 'selected' : '' }}>{{ __('admin.plan_3_months') }}</option>
                    <option value="6_month" {{ request('plan') == '6_month' ? 'selected' : '' }}>{{ __('admin.plan_6_months') }}</option>
                    <option value="12_month" {{ request('plan') == '12_month' ? 'selected' : '' }}>{{ __('admin.plan_12_months') }}</option>
                </select>
                <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700">{{ __('admin.apply_filters') }}</button>
                <a href="{{ route('admin.subscriptions.index') }}" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300">{{ __('admin.clear') }}</a>
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
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('admin.client') }}</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('admin.plan') }}</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('admin.amount') }}</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('admin.status') }}</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('admin.visits') }}</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('admin.dates') }}</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('admin.actions') }}</th>
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
                                    {{ __('admin.' . $subscription->payment_status) }}
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
                                <a href="{{ route('admin.subscriptions.show', $subscription) }}" class="text-indigo-600 hover:text-indigo-900 mr-3">{{ __('admin.view') }}</a>
                                <a href="{{ route('admin.subscriptions.edit', $subscription) }}" class="text-yellow-600 hover:text-yellow-900">{{ __('admin.edit') }}</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-4 text-center text-sm text-gray-500">{{ __('admin.no_subscriptions_found') }}</td>
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

