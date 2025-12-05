<x-admin-layout>
    <h1 class="text-xl font-medium text-gray-900 mb-6">
            Edit Subscription
        </h1>

    <div class="space-y-6">
        <div class="bg-white shadow rounded-lg p-6">
            <form method="POST" action="{{ route('admin.subscriptions.update', $subscription) }}">
                @csrf
                @method('PUT')

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700">Payment Status</label>
                    <select name="payment_status" required class="mt-1 block w-full rounded-md border-gray-300">
                        <option value="pending" {{ old('payment_status', $subscription->payment_status) == 'pending' ? 'selected' : '' }}>Pending</option>
                        <option value="paid" {{ old('payment_status', $subscription->payment_status) == 'paid' ? 'selected' : '' }}>Paid</option>
                        <option value="failed" {{ old('payment_status', $subscription->payment_status) == 'failed' ? 'selected' : '' }}>Failed</option>
                        <option value="refunded" {{ old('payment_status', $subscription->payment_status) == 'refunded' ? 'selected' : '' }}>Refunded</option>
                    </select>
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700">Amount (AED)</label>
                    <input type="number" name="amount" step="0.01" value="{{ old('amount', $subscription->amount) }}" class="mt-1 block w-full rounded-md border-gray-300">
                </div>

                <div class="flex gap-4">
                    <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700">Update Subscription</button>
                    <a href="{{ route('admin.subscriptions.show', $subscription) }}" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</x-admin-layout>

