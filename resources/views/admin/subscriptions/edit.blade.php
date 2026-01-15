<x-admin-layout>
    <h1 class="text-xl font-medium text-gray-900 mb-6">
            Edit Subscription
        </h1>

    <div class="space-y-6">
        <div class="bg-white shadow rounded-lg p-6">
            <form method="POST" action="{{ route('admin.subscriptions.update', $subscription) }}">
                @csrf
                @method('PUT')

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Start Date</label>
                        <input type="date" name="start_date" value="{{ old('start_date', $subscription->start_date) }}" class="mt-1 block w-full rounded-md border-gray-300">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">End Date</label>
                        <input type="date" name="end_date" value="{{ old('end_date', $subscription->end_date) }}" class="mt-1 block w-full rounded-md border-gray-300">
                    </div>
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700">Payment Status</label>
                    <select name="payment_status" required class="mt-1 block w-full rounded-md border-gray-300">
                        <option value="pending" {{ old('payment_status', $subscription->payment_status) == 'pending' ? 'selected' : '' }}>Pending</option>
                        <option value="paid" {{ old('payment_status', $subscription->payment_status) == 'paid' ? 'selected' : '' }}>Paid</option>
                        <option value="failed" {{ old('payment_status', $subscription->payment_status) == 'failed' ? 'selected' : '' }}>Failed</option>
                        <option value="refunded" {{ old('payment_status', $subscription->payment_status) == 'refunded' ? 'selected' : '' }}>Refunded</option>
                        <option value="cancelled" {{ old('payment_status', $subscription->payment_status) == 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                    </select>
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700">Amount (AED) <span class="text-red-500">*</span></label>
                    <input type="number" name="amount" step="0.01" min="0" value="{{ old('amount', $subscription->amount) }}" required class="mt-1 block w-full rounded-md border-gray-300">
                    <p class="mt-1 text-xs text-gray-500">Update the subscription price/amount</p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Total Visits</label>
                        <input type="number" name="total_visits" min="0" value="{{ old('total_visits', $subscription->total_visits) }}" class="mt-1 block w-full rounded-md border-gray-300">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Completed Visits</label>
                        <input type="number" name="completed_visits" min="0" value="{{ old('completed_visits', $subscription->completed_visits) }}" class="mt-1 block w-full rounded-md border-gray-300">
                    </div>
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700">Payment Reference</label>
                    <input type="text" name="payment_reference" value="{{ old('payment_reference', $subscription->payment_reference ?? '') }}" class="mt-1 block w-full rounded-md border-gray-300" placeholder="Payment transaction ID or reference">
                </div>

                <div class="flex gap-4">
                    <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700">Update Subscription</button>
                    <a href="{{ route('admin.subscriptions.show', $subscription) }}" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</x-admin-layout>

