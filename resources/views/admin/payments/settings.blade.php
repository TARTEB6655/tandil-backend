<x-admin-layout>
    <div class="space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
            <div>
                <h1 class="text-xl font-medium text-gray-900 dark:text-gray-100">Payment gateway settings</h1>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Stripe and PayPal keys, webhooks, and toggles</p>
            </div>
            <a href="{{ route('admin.payments.index') }}"
               class="inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:bg-gray-50 hover:shadow-md focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-1 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700">
                <svg class="h-4 w-4 text-gray-500 dark:text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                </svg>
                <span>Payment activity</span>
            </a>
        </div>

        @if(session('success'))
            <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 p-4 rounded-lg">
                <p class="text-sm text-green-800 dark:text-green-200">{{ session('success') }}</p>
            </div>
        @endif

        @if($errors->has('stripe_keys'))
            <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 p-4 rounded-lg">
                <p class="text-sm text-red-800 dark:text-red-200">{{ $errors->first('stripe_keys') }}</p>
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
                                @php
                                    $stripeMode = $stripeDiagnostics['mode'] ?? 'unknown';
                                    $modeLabel = $stripeMode === 'live' ? 'Live' : ($stripeMode === 'test' ? 'Test' : 'Unknown');
                                    $modeClass = $stripeMode === 'live'
                                        ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-200'
                                        : ($stripeMode === 'test'
                                            ? 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-200'
                                            : 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-200');
                                @endphp
                                <div class="mb-4 flex flex-wrap items-center gap-2 text-sm">
                                    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 font-medium {{ $modeClass }}">
                                        {{ $modeLabel }} mode
                                    </span>
                                    @if(!empty($stripeDiagnostics['secret_key_prefix']))
                                        <span class="text-gray-500 dark:text-gray-400">Secret: {{ $stripeDiagnostics['secret_key_prefix'] }}</span>
                                    @endif
                                    @if(!empty($stripeDiagnostics['publishable_key_prefix']))
                                        <span class="text-gray-500 dark:text-gray-400">Publishable: {{ $stripeDiagnostics['publishable_key_prefix'] }}</span>
                                    @endif
                                </div>
                                @if(!empty($stripeDiagnostics['configuration_issues']))
                                    <div class="mb-4 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-800 dark:border-red-800 dark:bg-red-900/20 dark:text-red-200">
                                        {{ implode(' ', $stripeDiagnostics['configuration_issues']) }}
                                    </div>
                                @endif
                                @if(!empty($stripeDiagnostics['configuration_notes']))
                                    <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-900/20 dark:text-amber-100">
                                        {{ implode(' ', $stripeDiagnostics['configuration_notes']) }}
                                    </div>
                                @endif
                                <p class="mb-4 text-sm text-gray-500 dark:text-gray-400">
                                    Both keys must be from the same Stripe mode (<code class="text-xs">sk_test_</code> + <code class="text-xs">pk_test_</code>, or <code class="text-xs">sk_live_</code> + <code class="text-xs">pk_live_</code>).
                                    When switching test/live, paste <strong>both</strong> keys and save together.
                                </p>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Public Key</label>
                                        <input type="text"
                                               name="public_key"
                                               value="{{ old('public_key', $gateways[$gateway]['public_key']) }}"
                                               placeholder="pk_test_... or pk_live_..."
                                               class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Secret Key</label>
                                        <input type="password"
                                               name="secret_key"
                                               value=""
                                               placeholder="{{ $gateways[$gateway]['secret_key'] !== '' ? 'Leave blank to keep current secret' : 'sk_test_... or sk_live_...' }}"
                                               autocomplete="new-password"
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
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Refund credits expire after this period; the daily job can forfeit unused amounts to the company bucket. Logged-in customers can apply wallet balance at checkout (<code class="text-xs">wallet_amount</code> on POST /api/shop/checkout/start or mobile payment-intent).</p>
                    </div>
                </div>

                <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors text-sm font-medium">
                    Save refund policy
                </button>
            </form>
        </div>
    </div>
</x-admin-layout>
