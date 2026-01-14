<x-admin-layout>
    <div class="space-y-6">
        <!-- Header Section -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
            <div>
                <h1 class="text-xl font-medium text-gray-900">Payments & Transactions</h1>
                <p class="text-sm text-gray-500 mt-1">Manage payment gateways and view transaction logs</p>
            </div>
        </div>

        @if(session('success'))
            <div class="bg-green-50 border-l-4 border-green-400 p-4 rounded-md">
                <p class="text-sm text-green-700">{{ session('success') }}</p>
            </div>
        @endif

        <!-- Payment Gateways -->
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
            <h2 class="text-lg font-medium text-gray-900 mb-6">Payment Gateway Configuration</h2>
            
            <div class="space-y-6">
                @foreach(['stripe', 'paypal', 'razorpay'] as $gateway)
                    <div class="border border-gray-200 rounded-lg p-4">
                        <div class="flex items-center justify-between mb-4">
                            <div>
                                <h3 class="text-base font-medium text-gray-900 capitalize">{{ $gateway }}</h3>
                                <p class="text-sm text-gray-500 mt-1">Configure {{ ucfirst($gateway) }} payment gateway</p>
                            </div>
                            <form method="POST" action="{{ route('admin.payments.update-gateway', $gateway) }}" class="inline">
                                @csrf
                                <label class="relative inline-flex items-center cursor-pointer">
                                    <input type="checkbox" 
                                           name="enabled" 
                                           value="1"
                                           {{ $gateways[$gateway]['enabled'] ? 'checked' : '' }}
                                           onchange="this.form.submit()"
                                           class="sr-only peer">
                                    <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-indigo-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600"></div>
                                </label>
                            </form>
                        </div>
                        
                        <form method="POST" action="{{ route('admin.payments.update-gateway', $gateway) }}" class="space-y-4">
                            @csrf
                            <input type="hidden" name="enabled" value="{{ $gateways[$gateway]['enabled'] ? '1' : '0' }}">
                            
                            @if($gateway === 'stripe')
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Public Key</label>
                                        <input type="text" 
                                               name="public_key" 
                                               value="{{ $gateways[$gateway]['public_key'] }}"
                                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Secret Key</label>
                                        <input type="password" 
                                               name="secret_key" 
                                               value="{{ $gateways[$gateway]['secret_key'] }}"
                                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                                    </div>
                                    <div class="md:col-span-2">
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Webhook Secret</label>
                                        <input type="text" 
                                               name="webhook_secret" 
                                               value="{{ $gateways[$gateway]['webhook_secret'] }}"
                                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                                    </div>
                                </div>
                            @elseif($gateway === 'paypal')
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Client ID</label>
                                        <input type="text" 
                                               name="client_id" 
                                               value="{{ $gateways[$gateway]['client_id'] }}"
                                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Client Secret</label>
                                        <input type="password" 
                                               name="client_secret" 
                                               value="{{ $gateways[$gateway]['client_secret'] }}"
                                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Mode</label>
                                        <select name="mode" 
                                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                                            <option value="sandbox" {{ $gateways[$gateway]['mode'] === 'sandbox' ? 'selected' : '' }}>Sandbox</option>
                                            <option value="live" {{ $gateways[$gateway]['mode'] === 'live' ? 'selected' : '' }}>Live</option>
                                        </select>
                                    </div>
                                </div>
                            @elseif($gateway === 'razorpay')
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Key ID</label>
                                        <input type="text" 
                                               name="key_id" 
                                               value="{{ $gateways[$gateway]['key_id'] }}"
                                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Key Secret</label>
                                        <input type="password" 
                                               name="key_secret" 
                                               value="{{ $gateways[$gateway]['key_secret'] }}"
                                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                                    </div>
                                </div>
                            @endif
                            
                            <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors text-sm font-medium">
                                Save {{ ucfirst($gateway) }} Settings
                            </button>
                        </form>
                    </div>
                @endforeach
            </div>
        </div>

        <!-- Transaction Logs -->
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
            <h2 class="text-lg font-medium text-gray-900 mb-6">Transaction Logs</h2>
            
            <!-- Filters -->
            <div class="mb-4 flex flex-wrap gap-4">
                <form method="GET" action="{{ route('admin.payments.index') }}" class="flex gap-2">
                    <input type="text" 
                           name="search" 
                           value="{{ request('search') }}"
                           placeholder="Search by transaction ID..."
                           class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                    <select name="status" 
                            class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                        <option value="">All Statuses</option>
                        <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>Pending</option>
                        <option value="completed" {{ request('status') === 'completed' ? 'selected' : '' }}>Completed</option>
                        <option value="failed" {{ request('status') === 'failed' ? 'selected' : '' }}>Failed</option>
                        <option value="refunded" {{ request('status') === 'refunded' ? 'selected' : '' }}>Refunded</option>
                    </select>
                    <button type="submit" class="px-4 py-2 bg-gray-900 text-white rounded-lg hover:bg-gray-800 transition-colors">
                        Filter
                    </button>
                </form>
            </div>

            <!-- Transactions Table -->
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Transaction ID</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Gateway</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse($transactions as $transaction)
                            <tr class="hover:bg-gray-50 transition-colors">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900">{{ $transaction->transaction_id }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="text-sm text-gray-900 capitalize">{{ $transaction->type }}</span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="text-sm text-gray-900 capitalize">{{ $transaction->gateway ?? 'N/A' }}</span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900">{{ $transaction->currency }} {{ number_format($transaction->amount, 2) }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full 
                                        {{ $transaction->status === 'completed' ? 'bg-green-100 text-green-800' : 
                                           ($transaction->status === 'failed' ? 'bg-red-100 text-red-800' : 
                                           ($transaction->status === 'refunded' ? 'bg-gray-100 text-gray-800' : 'bg-yellow-100 text-yellow-800')) }}">
                                        {{ ucfirst($transaction->status) }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-500">{{ $transaction->created_at->format('M d, Y') }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <a href="{{ route('admin.payments.transaction', $transaction->id) }}" 
                                       class="text-indigo-600 hover:text-indigo-900">View</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-6 py-12 text-center">
                                    <div class="text-sm text-gray-500">No transactions found</div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            @if($transactions->hasPages())
                <div class="mt-4">
                    {{ $transactions->links() }}
                </div>
            @endif
        </div>
    </div>
</x-admin-layout>




