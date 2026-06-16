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
                                <div class="flex flex-wrap items-center gap-2">
                                    <h3 class="text-base font-medium text-gray-900 dark:text-gray-100 capitalize">{{ $gateway }}</h3>
                                    @if($gateway === 'stripe')
                                        @php $headerStripeMode = old('stripe_mode', $gateways['stripe']['mode'] ?? 'test'); @endphp
                                        <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $headerStripeMode === 'live' ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200' : 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200' }}">
                                            Checkout: {{ $headerStripeMode === 'live' ? 'LIVE' : 'TEST' }}
                                        </span>
                                    @endif
                                </div>
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
                                    $stripeActiveMode = old('stripe_mode', $gateways['stripe']['mode'] ?? 'test');
                                    $stripeTest = $gateways['stripe']['test'] ?? [];
                                    $stripeLive = $gateways['stripe']['live'] ?? [];
                                @endphp

                                <div x-data="{ stripeMode: @js($stripeActiveMode) }" class="space-y-4">
                                <div class="rounded-xl border-2 p-4 transition-colors"
                                     :class="stripeMode === 'live' ? 'border-emerald-500 bg-emerald-50 dark:border-emerald-600 dark:bg-emerald-900/20' : 'border-amber-400 bg-amber-50 dark:border-amber-500 dark:bg-amber-900/20'">
                                    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                                        <div>
                                            <p class="text-xs font-semibold uppercase tracking-wide"
                                               :class="stripeMode === 'live' ? 'text-emerald-700 dark:text-emerald-300' : 'text-amber-700 dark:text-amber-300'">
                                                Currently active for mobile checkout
                                            </p>
                                            <p class="mt-1 text-2xl font-bold text-gray-900 dark:text-gray-100" x-text="stripeMode === 'live' ? 'LIVE MODE' : 'TEST MODE'">
                                                {{ $stripeActiveMode === 'live' ? 'LIVE MODE' : 'TEST MODE' }}
                                            </p>
                                            <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">
                                                @if(!empty($stripeDiagnostics['publishable_key_prefix']))
                                                    Saved mode uses {{ $stripeDiagnostics['publishable_key_prefix'] }}
                                                @else
                                                    No publishable key configured for the saved mode yet.
                                                @endif
                                            </p>
                                        </div>

                                        <div class="shrink-0">
                                            <p class="mb-2 text-center text-xs font-medium text-gray-600 dark:text-gray-300 sm:text-right">Switch checkout mode</p>
                                            <div class="inline-flex w-full rounded-xl border border-gray-300 bg-white p-1 shadow-sm dark:border-gray-600 dark:bg-gray-800 sm:w-auto">
                                                <label class="flex-1 cursor-pointer sm:flex-none">
                                                    <input type="radio"
                                                           name="stripe_mode"
                                                           value="test"
                                                           class="sr-only"
                                                           x-model="stripeMode"
                                                           {{ $stripeActiveMode === 'test' ? 'checked' : '' }}>
                                                    <span class="flex items-center justify-center gap-1.5 rounded-lg px-4 py-2.5 text-sm font-semibold transition"
                                                          :class="stripeMode === 'test' ? 'bg-amber-500 text-white shadow' : 'text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700'">
                                                        <span class="inline-block h-2 w-2 rounded-full" :class="stripeMode === 'test' ? 'bg-white' : 'bg-amber-400'"></span>
                                                        Test
                                                    </span>
                                                </label>
                                                <label class="flex-1 cursor-pointer sm:flex-none">
                                                    <input type="radio"
                                                           name="stripe_mode"
                                                           value="live"
                                                           class="sr-only"
                                                           x-model="stripeMode"
                                                           {{ $stripeActiveMode === 'live' ? 'checked' : '' }}>
                                                    <span class="flex items-center justify-center gap-1.5 rounded-lg px-4 py-2.5 text-sm font-semibold transition"
                                                          :class="stripeMode === 'live' ? 'bg-emerald-600 text-white shadow' : 'text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700'">
                                                        <span class="inline-block h-2 w-2 rounded-full" :class="stripeMode === 'live' ? 'bg-white' : 'bg-emerald-500'"></span>
                                                        Live
                                                    </span>
                                                </label>
                                            </div>
                                            <p class="mt-2 text-center text-xs text-gray-500 dark:text-gray-400 sm:text-right">Click Live or Test, then Save below.</p>
                                        </div>
                                    </div>
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

                                <div class="grid grid-cols-1 gap-4 xl:grid-cols-2">
                                    <div class="relative rounded-xl border p-4 transition-all"
                                         :class="stripeMode === 'test' ? 'border-amber-400 bg-amber-50/40 ring-2 ring-amber-300 dark:border-amber-600 dark:bg-amber-900/10 dark:ring-amber-700' : 'border-gray-200 dark:border-gray-600'">
                                        <span x-show="stripeMode === 'test'" x-cloak class="absolute -top-3 left-4 inline-flex rounded-full bg-amber-500 px-2.5 py-0.5 text-xs font-bold uppercase tracking-wide text-white">Active</span>
                                        <div class="mb-3 flex items-center justify-between gap-2">
                                            <h4 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Test keys</h4>
                                            @if($stripeTest['has_secret'] ?? false)
                                                <span class="text-xs text-gray-500 dark:text-gray-400">Secret saved: {{ $stripeTest['secret_prefix'] }}</span>
                                            @endif
                                        </div>
                                        <div class="space-y-3">
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Publishable key</label>
                                                <input type="text" name="test_public_key" value="{{ old('test_public_key', $stripeTest['public_key'] ?? '') }}" placeholder="pk_test_..." autocomplete="off" spellcheck="false" class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 font-mono text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                                            </div>
                                            <div x-data="{ editing: @json((bool) old('test_secret_key') || !($stripeTest['has_secret'] ?? false)) }">
                                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Secret key</label>
                                                @if($stripeTest['has_secret'] ?? false)
                                                    <div x-show="!editing" class="mb-2 rounded-lg border border-emerald-200 bg-emerald-50 p-3 dark:border-emerald-800 dark:bg-emerald-900/20">
                                                        <div class="flex flex-wrap items-center justify-between gap-2">
                                                            <p class="text-sm text-emerald-800 dark:text-emerald-200">
                                                                <span class="font-medium">Saved securely</span>
                                                                <span class="font-mono text-xs">({{ $stripeTest['secret_prefix'] }})</span>
                                                            </p>
                                                            <button type="button" @click="editing = true" class="text-sm font-medium text-indigo-600 hover:text-indigo-700 dark:text-indigo-400">Replace secret</button>
                                                        </div>
                                                        <p class="mt-1 text-xs text-emerald-700 dark:text-emerald-300">The field is empty on purpose — your key is stored in the database. Click Replace only when changing it.</p>
                                                    </div>
                                                @endif
                                                <div x-show="editing" x-cloak>
                                                    <input type="text" name="test_secret_key" value="{{ old('test_secret_key', '') }}" placeholder="{{ ($stripeTest['has_secret'] ?? false) ? 'Paste new sk_test_... to replace' : 'sk_test_...' }}" autocomplete="off" spellcheck="false" class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 font-mono text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                                                </div>
                                            </div>
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Webhook secret (optional)</label>
                                                <input type="text" name="test_webhook_secret" value="{{ old('test_webhook_secret', $stripeTest['webhook_secret'] ?? '') }}" placeholder="whsec_..." autocomplete="off" spellcheck="false" class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 font-mono text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                                            </div>
                                        </div>
                                    </div>

                                    <div class="relative rounded-xl border p-4 transition-all"
                                         :class="stripeMode === 'live' ? 'border-emerald-500 bg-emerald-50/40 ring-2 ring-emerald-400 dark:border-emerald-600 dark:bg-emerald-900/10 dark:ring-emerald-700' : 'border-gray-200 dark:border-gray-600'">
                                        <span x-show="stripeMode === 'live'" x-cloak class="absolute -top-3 left-4 inline-flex rounded-full bg-emerald-600 px-2.5 py-0.5 text-xs font-bold uppercase tracking-wide text-white">Active</span>
                                        <div class="mb-3 flex items-center justify-between gap-2">
                                            <h4 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Live keys</h4>
                                            @if($stripeLive['has_secret'] ?? false)
                                                <span class="text-xs text-gray-500 dark:text-gray-400">Secret saved: {{ $stripeLive['secret_prefix'] }}</span>
                                            @endif
                                        </div>
                                        <div class="space-y-3">
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Publishable key</label>
                                                <input type="text" name="live_public_key" value="{{ old('live_public_key', $stripeLive['public_key'] ?? '') }}" placeholder="pk_live_..." autocomplete="off" spellcheck="false" class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 font-mono text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                                            </div>
                                            <div x-data="{ editing: @json((bool) old('live_secret_key') || !($stripeLive['has_secret'] ?? false)) }">
                                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Secret key</label>
                                                @if($stripeLive['has_secret'] ?? false)
                                                    <div x-show="!editing" class="mb-2 rounded-lg border border-emerald-200 bg-emerald-50 p-3 dark:border-emerald-800 dark:bg-emerald-900/20">
                                                        <div class="flex flex-wrap items-center justify-between gap-2">
                                                            <p class="text-sm text-emerald-800 dark:text-emerald-200">
                                                                <span class="font-medium">Saved securely</span>
                                                                <span class="font-mono text-xs">({{ $stripeLive['secret_prefix'] }})</span>
                                                            </p>
                                                            <button type="button" @click="editing = true" class="text-sm font-medium text-indigo-600 hover:text-indigo-700 dark:text-indigo-400">Replace secret</button>
                                                        </div>
                                                        <p class="mt-1 text-xs text-emerald-700 dark:text-emerald-300">The field is empty on purpose — your key is stored in the database. Click Replace only when changing it.</p>
                                                    </div>
                                                @endif
                                                <div x-show="editing" x-cloak>
                                                    <input type="text" name="live_secret_key" value="{{ old('live_secret_key', '') }}" placeholder="{{ ($stripeLive['has_secret'] ?? false) ? 'Paste new sk_live_... to replace' : 'sk_live_...' }}" autocomplete="off" spellcheck="false" class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 font-mono text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                                                </div>
                                            </div>
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Webhook secret (optional)</label>
                                                <input type="text" name="live_webhook_secret" value="{{ old('live_webhook_secret', $stripeLive['webhook_secret'] ?? '') }}" placeholder="whsec_..." autocomplete="off" spellcheck="false" class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 font-mono text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                                            </div>
                                        </div>
                                    </div>
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
