<x-admin-layout>
    <div class="space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
            <div>
                <h1 class="text-xl font-medium text-gray-900 dark:text-gray-100">Payment gateway settings</h1>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Stripe and PayPal keys, webhooks, and toggles</p>
            </div>
            <a href="{{ route('admin.payments.index') }}"
               class="inline-flex items-center px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700">
                ← Payment activity
            </a>
        </div>

        @if(session('success'))
            <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 p-4 rounded-lg">
                <p class="text-sm text-green-800 dark:text-green-200">{{ session('success') }}</p>
            </div>
        @endif

        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm p-6">
            <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-6">Payment gateway configuration</h2>

            <div class="space-y-6">
                @foreach(['stripe', 'paypal'] as $gateway)
                    <div class="border border-gray-200 dark:border-gray-600 rounded-lg p-4">
                        <div class="flex items-center justify-between mb-4">
                            <div>
                                <h3 class="text-base font-medium text-gray-900 dark:text-gray-100 capitalize">{{ $gateway }}</h3>
                                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Configure {{ ucfirst($gateway) }} payment gateway</p>
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
                                    <div class="w-11 h-6 bg-gray-200 dark:bg-gray-600 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-indigo-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600"></div>
                                </label>
                            </form>
                        </div>

                        <form method="POST" action="{{ route('admin.payments.update-gateway', $gateway) }}" class="space-y-4">
                            @csrf
                            <input type="hidden" name="enabled" value="{{ $gateways[$gateway]['enabled'] ? '1' : '0' }}">

                            @if($gateway === 'stripe')
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Public Key</label>
                                        <input type="text"
                                               name="public_key"
                                               value="{{ $gateways[$gateway]['public_key'] }}"
                                               class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Secret Key</label>
                                        <input type="password"
                                               name="secret_key"
                                               value="{{ $gateways[$gateway]['secret_key'] }}"
                                               class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                                    </div>
                                    <div class="md:col-span-2">
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Webhook Secret</label>
                                        <input type="text"
                                               name="webhook_secret"
                                               value="{{ $gateways[$gateway]['webhook_secret'] }}"
                                               class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                                    </div>
                                </div>
                            @elseif($gateway === 'paypal')
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Client ID</label>
                                        <input type="text"
                                               name="client_id"
                                               value="{{ $gateways[$gateway]['client_id'] }}"
                                               class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Client Secret</label>
                                        <input type="password"
                                               name="client_secret"
                                               value="{{ $gateways[$gateway]['client_secret'] }}"
                                               class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Mode</label>
                                        <select name="mode"
                                                class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                                            <option value="sandbox" {{ $gateways[$gateway]['mode'] === 'sandbox' ? 'selected' : '' }}>Sandbox</option>
                                            <option value="live" {{ $gateways[$gateway]['mode'] === 'live' ? 'selected' : '' }}>Live</option>
                                        </select>
                                    </div>
                                </div>
                            @endif

                            <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors text-sm font-medium">
                                Save {{ ucfirst($gateway) }} settings
                            </button>
                        </form>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm p-6">
            <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-1">Refund and wallet policy</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400 mb-6">
                Configure timeline-based refunds and wallet expiry without app updates.
            </p>

            <form method="POST" action="{{ route('admin.payments.update-refund-policy') }}" class="space-y-5">
                @csrf
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Grace window (minutes)</label>
                        <input type="number" min="0" max="1440" name="refund_grace_minutes" value="{{ old('refund_grace_minutes', $refundPolicy['grace_minutes']) }}"
                               class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Within this window, full refund applies.</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Partial refund (%) after assignment</label>
                        <input type="number" min="0" max="100" step="0.01" name="refund_partial_percent" value="{{ old('refund_partial_percent', $refundPolicy['partial_refund_percent']) }}"
                               class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Service fee (%) after service start/completion</label>
                        <input type="number" min="0" max="100" step="0.01" name="refund_service_fee_percent_after_start" value="{{ old('refund_service_fee_percent_after_start', $refundPolicy['service_fee_percent_after_start']) }}"
                               class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Refund % = 100 - service fee %.</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Wallet validity (months)</label>
                        <input type="number" min="1" max="24" name="refund_wallet_validity_months" value="{{ old('refund_wallet_validity_months', $refundPolicy['wallet_validity_months']) }}"
                               class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                    </div>
                </div>

                <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors text-sm font-medium">
                    Save refund policy
                </button>
            </form>
        </div>
    </div>
</x-admin-layout>
