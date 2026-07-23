<x-client-layout>
    @php
        $balance = (float) ($options['available_balance'] ?? 0);
        $presets = array_values($options['presets'] ?? [50, 100, 150, 200]);
        $minAmount = (float) ($options['min_amount'] ?? 2);
        $maxAmount = (float) ($options['max_amount'] ?? 5000);
        $currency = $options['currency'] ?? 'AED';
        $methods = $options['payment_methods'] ?? [];
        $defaultAmount = in_array(100, $presets, true) ? 100 : (float) ($presets[0] ?? $minAmount);
    @endphp

    <div class="mx-auto max-w-2xl space-y-6" id="wallet-add-money"
         data-balance="{{ $balance }}"
         data-min="{{ $minAmount }}"
         data-max="{{ $maxAmount }}"
         data-currency="{{ $currency }}"
         data-default-amount="{{ $defaultAmount }}"
         data-intent-url="{{ route('client.wallet.add-money.payment-intent') }}"
         data-confirm-url="{{ route('client.wallet.add-money.confirm') }}"
         data-wallet-url="{{ route('client.wallet.index') }}"
         data-csrf="{{ csrf_token() }}">

        <div class="flex items-start gap-3">
            <a href="{{ route('client.wallet.index') }}"
               class="mt-0.5 inline-flex h-9 w-9 items-center justify-center rounded-lg border border-gray-200 bg-white text-gray-600 hover:bg-gray-50"
               aria-label="Back to wallet">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </a>
            <div>
                <h1 class="text-xl font-semibold text-gray-900">Add Money</h1>
                <p class="mt-1 text-sm text-gray-500">Top up your wallet using Stripe or Apple Pay.</p>
            </div>
        </div>

        <div class="rounded-xl border border-indigo-200 bg-indigo-50 px-5 py-4">
            <p class="text-xs font-semibold uppercase tracking-wide text-indigo-700">Available balance</p>
            <p class="mt-1 text-2xl font-bold text-indigo-900">
                {{ $currency }} <span id="ui-balance">{{ number_format($balance, 2) }}</span>
            </p>
        </div>

        {{-- Step 1: amount --}}
        <div id="step-amount" class="rounded-xl border border-gray-200 bg-white shadow-sm">
            <div class="border-b border-gray-100 px-5 py-4">
                <h2 class="text-base font-semibold text-gray-900">1. Select amount</h2>
                <p class="mt-1 text-sm text-gray-500">Choose a preset or enter a custom amount.</p>
            </div>

            <div class="space-y-5 px-5 py-5">
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                    @foreach($presets as $preset)
                        <button type="button"
                                data-preset="{{ $preset }}"
                                class="preset-btn rounded-lg border px-3 py-3 text-sm font-semibold transition-colors {{ (float) $preset === (float) $defaultAmount ? 'border-indigo-600 bg-indigo-50 text-indigo-800' : 'border-gray-200 bg-white text-gray-800 hover:border-indigo-300' }}">
                            {{ $currency }} {{ $preset }}
                        </button>
                    @endforeach
                </div>

                <div>
                    <label for="custom-amount" class="block text-sm font-medium text-gray-700">Custom amount</label>
                    <div class="mt-1.5 flex overflow-hidden rounded-lg border border-gray-300 focus-within:border-indigo-500 focus-within:ring-1 focus-within:ring-indigo-500">
                        <span class="inline-flex items-center bg-gray-50 px-3 text-sm text-gray-500 border-r border-gray-300">{{ $currency }}</span>
                        <input id="custom-amount" type="number" min="0" step="0.01" placeholder="0.00"
                               class="w-full border-0 px-3 py-2.5 text-sm text-gray-900 placeholder:text-gray-400 focus:ring-0">
                    </div>
                    <p class="mt-1 text-xs text-gray-500">Min {{ $currency }} {{ number_format($minAmount, 2) }} · Max {{ $currency }} {{ number_format($maxAmount, 2) }}</p>
                </div>

                <div class="rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 text-sm space-y-2">
                    <div class="flex items-center justify-between text-gray-700">
                        <span>You will add</span>
                        <span class="font-semibold text-gray-900">{{ $currency }} <span id="ui-add">{{ number_format($defaultAmount, 2) }}</span></span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-gray-700">New balance</span>
                        <span class="font-semibold text-indigo-700">{{ $currency }} <span id="ui-new">{{ number_format($balance + $defaultAmount, 2) }}</span></span>
                    </div>
                </div>

                <div>
                    <p class="mb-2 text-sm font-medium text-gray-900">Payment method</p>
                    <div class="space-y-2">
                        @forelse($methods as $i => $method)
                            <label class="method-label flex cursor-pointer items-start gap-3 rounded-lg border p-3 transition-colors {{ $i === 0 ? 'border-indigo-600 bg-indigo-50' : 'border-gray-200 bg-white hover:border-gray-300' }}">
                                <input type="radio"
                                       name="payment_method"
                                       value="{{ $method['id'] }}"
                                       class="mt-1 text-indigo-600 focus:ring-indigo-500"
                                       {{ $i === 0 ? 'checked' : '' }}>
                                <span>
                                    <span class="block text-sm font-semibold text-gray-900">{{ $method['label'] ?? ucfirst($method['id']) }}</span>
                                    <span class="block text-xs text-gray-500">{{ $method['description'] ?? '' }}</span>
                                </span>
                            </label>
                        @empty
                            <label class="method-label flex cursor-pointer items-start gap-3 rounded-lg border border-indigo-600 bg-indigo-50 p-3">
                                <input type="radio" name="payment_method" value="stripe" class="mt-1 text-indigo-600" checked>
                                <span>
                                    <span class="block text-sm font-semibold text-gray-900">Stripe</span>
                                    <span class="block text-xs text-gray-500">Pay with card</span>
                                </span>
                            </label>
                        @endforelse
                    </div>
                </div>

                <p id="amount-error" class="hidden text-sm text-red-600"></p>
            </div>

            <div class="border-t border-gray-100 bg-gray-50 px-5 py-4">
                <button type="button" id="btn-start-pay"
                        class="inline-flex w-full items-center justify-center rounded-lg bg-indigo-600 px-4 py-3 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60">
                    Continue to payment · {{ $currency }} <span id="btn-amount-label">{{ number_format($defaultAmount, 2) }}</span>
                </button>
                <p class="mt-2 text-center text-xs text-gray-500">Funds stay in your wallet and can be used at checkout.</p>
            </div>
        </div>

        {{-- Step 2: pay --}}
        <div id="step-pay" class="hidden rounded-xl border border-gray-200 bg-white shadow-sm">
            <div class="border-b border-gray-100 px-5 py-4 flex items-center justify-between gap-3">
                <div>
                    <h2 class="text-base font-semibold text-gray-900">2. Complete payment</h2>
                    <p class="mt-1 text-sm text-gray-500">
                        Amount due:
                        <span class="font-semibold text-gray-900">{{ $currency }} <span id="pay-amount-label">{{ number_format($defaultAmount, 2) }}</span></span>
                    </p>
                </div>
                <button type="button" id="btn-change-amount" class="text-sm font-medium text-indigo-600 hover:text-indigo-800">
                    Change amount
                </button>
            </div>

            <div class="space-y-4 px-5 py-5">
                <div id="payment-element" class="min-h-[200px] rounded-lg border border-gray-200 bg-white p-3"></div>
                <p id="pay-error" class="hidden text-sm text-red-600"></p>
            </div>

            <div class="border-t border-gray-100 bg-gray-50 px-5 py-4">
                <button type="button" id="btn-confirm-pay"
                        class="inline-flex w-full items-center justify-center rounded-lg bg-indigo-600 px-4 py-3 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60">
                    Pay {{ $currency }} <span id="pay-btn-amount">{{ number_format($defaultAmount, 2) }}</span>
                </button>
            </div>
        </div>

        {{-- Done --}}
        <div id="step-done" class="hidden rounded-xl border border-green-200 bg-green-50 px-6 py-8 text-center">
            <p class="text-lg font-semibold text-green-900">Money added successfully</p>
            <p class="mt-2 text-sm text-green-800">
                Added {{ $currency }} <span id="done-added">0.00</span>.
                New balance: <strong>{{ $currency }} <span id="done-balance">0.00</span></strong>
            </p>
            <a href="{{ route('client.wallet.index') }}"
               class="mt-5 inline-flex rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700">
                Back to wallet
            </a>
        </div>
    </div>

    @push('scripts')
        <script src="https://js.stripe.com/v3/"></script>
        <script>
            (function () {
                const root = document.getElementById('wallet-add-money');
                if (!root || typeof Stripe === 'undefined') return;

                const cfg = {
                    balance: parseFloat(root.dataset.balance),
                    min: parseFloat(root.dataset.min),
                    max: parseFloat(root.dataset.max),
                    currency: root.dataset.currency,
                    intentUrl: root.dataset.intentUrl,
                    confirmUrl: root.dataset.confirmUrl,
                    walletUrl: root.dataset.walletUrl,
                    csrf: root.dataset.csrf,
                };

                let amount = parseFloat(root.dataset.defaultAmount);
                let usingCustom = false;
                let stripe = null;
                let elements = null;
                let paymentIntentId = null;

                const ui = {
                    balance: document.getElementById('ui-balance'),
                    add: document.getElementById('ui-add'),
                    neu: document.getElementById('ui-new'),
                    btnLabel: document.getElementById('btn-amount-label'),
                    payAmount: document.getElementById('pay-amount-label'),
                    payBtnAmount: document.getElementById('pay-btn-amount'),
                    amountError: document.getElementById('amount-error'),
                    payError: document.getElementById('pay-error'),
                    stepAmount: document.getElementById('step-amount'),
                    stepPay: document.getElementById('step-pay'),
                    stepDone: document.getElementById('step-done'),
                    custom: document.getElementById('custom-amount'),
                    startBtn: document.getElementById('btn-start-pay'),
                    confirmBtn: document.getElementById('btn-confirm-pay'),
                    changeBtn: document.getElementById('btn-change-amount'),
                    paymentMount: document.getElementById('payment-element'),
                    doneAdded: document.getElementById('done-added'),
                    doneBalance: document.getElementById('done-balance'),
                };

                function money(n) {
                    return (Math.round(Number(n) * 100) / 100).toFixed(2);
                }

                function refreshSummary() {
                    ui.add.textContent = money(amount);
                    ui.neu.textContent = money(cfg.balance + amount);
                    ui.btnLabel.textContent = money(amount);
                    ui.payAmount.textContent = money(amount);
                    ui.payBtnAmount.textContent = money(amount);
                }

                function setPresetStyles() {
                    document.querySelectorAll('.preset-btn').forEach((btn) => {
                        const active = !usingCustom && parseFloat(btn.dataset.preset) === amount;
                        btn.classList.toggle('border-indigo-600', active);
                        btn.classList.toggle('bg-indigo-50', active);
                        btn.classList.toggle('text-indigo-800', active);
                        btn.classList.toggle('border-gray-200', !active);
                        btn.classList.toggle('bg-white', !active);
                        btn.classList.toggle('text-gray-800', !active);
                    });
                }

                function showError(node, msg) {
                    if (!msg) {
                        node.classList.add('hidden');
                        node.textContent = '';
                        return;
                    }
                    node.textContent = msg;
                    node.classList.remove('hidden');
                }

                document.querySelectorAll('.preset-btn').forEach((btn) => {
                    btn.addEventListener('click', () => {
                        usingCustom = false;
                        amount = parseFloat(btn.dataset.preset);
                        ui.custom.value = '';
                        setPresetStyles();
                        showError(ui.amountError, '');
                        refreshSummary();
                    });
                });

                ui.custom.addEventListener('input', () => {
                    const v = parseFloat(ui.custom.value);
                    usingCustom = true;
                    if (!isNaN(v) && v > 0) {
                        amount = Math.round(v * 100) / 100;
                        setPresetStyles();
                        refreshSummary();
                    }
                });

                document.querySelectorAll('input[name="payment_method"]').forEach((input) => {
                    input.addEventListener('change', () => {
                        document.querySelectorAll('.method-label').forEach((label) => {
                            const checked = label.querySelector('input').checked;
                            label.classList.toggle('border-indigo-600', checked);
                            label.classList.toggle('bg-indigo-50', checked);
                            label.classList.toggle('border-gray-200', !checked);
                            label.classList.toggle('bg-white', !checked);
                        });
                    });
                });

                ui.startBtn.addEventListener('click', async () => {
                    showError(ui.amountError, '');
                    if (amount < cfg.min) {
                        showError(ui.amountError, `Minimum amount is ${cfg.currency} ${money(cfg.min)}.`);
                        return;
                    }
                    if (amount > cfg.max) {
                        showError(ui.amountError, `Maximum amount is ${cfg.currency} ${money(cfg.max)}.`);
                        return;
                    }

                    const method = (document.querySelector('input[name="payment_method"]:checked') || {}).value || 'stripe';
                    const original = ui.startBtn.innerHTML;
                    ui.startBtn.disabled = true;
                    ui.startBtn.textContent = 'Preparing secure payment…';

                    try {
                        const res = await fetch(cfg.intentUrl, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': cfg.csrf,
                            },
                            body: JSON.stringify({ amount, payment_method: method }),
                        });
                        const json = await res.json();
                        if (!res.ok || !json.success) {
                            throw new Error(json.message || 'Could not start payment.');
                        }

                        const data = json.data;
                        paymentIntentId = data.payment_intent_id;
                        stripe = Stripe(data.publishable_key);
                        elements = stripe.elements({
                            clientSecret: data.client_secret,
                            appearance: {
                                theme: 'stripe',
                                variables: { colorPrimary: '#4f46e5' },
                            },
                        });

                        ui.stepAmount.classList.add('hidden');
                        ui.stepPay.classList.remove('hidden');
                        ui.paymentMount.innerHTML = '';
                        elements.create('payment').mount('#payment-element');
                        refreshSummary();
                        ui.stepPay.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    } catch (e) {
                        showError(ui.amountError, e.message || 'Payment setup failed.');
                    } finally {
                        ui.startBtn.disabled = false;
                        ui.startBtn.innerHTML = original;
                        ui.btnLabel = document.getElementById('btn-amount-label');
                        if (ui.btnLabel) ui.btnLabel.textContent = money(amount);
                    }
                });

                ui.changeBtn.addEventListener('click', () => {
                    ui.stepPay.classList.add('hidden');
                    ui.stepAmount.classList.remove('hidden');
                    ui.paymentMount.innerHTML = '';
                    elements = null;
                    showError(ui.payError, '');
                });

                ui.confirmBtn.addEventListener('click', async () => {
                    if (!stripe || !elements) return;
                    ui.confirmBtn.disabled = true;
                    const original = ui.confirmBtn.innerHTML;
                    ui.confirmBtn.textContent = 'Processing payment…';
                    showError(ui.payError, '');

                    try {
                        const { error, paymentIntent } = await stripe.confirmPayment({
                            elements,
                            redirect: 'if_required',
                            confirmParams: { return_url: cfg.walletUrl },
                        });
                        if (error) throw new Error(error.message || 'Payment failed.');

                        const piId = (paymentIntent && paymentIntent.id) || paymentIntentId;
                        const res = await fetch(cfg.confirmUrl, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': cfg.csrf,
                            },
                            body: JSON.stringify({ payment_intent_id: piId }),
                        });
                        const json = await res.json();
                        if (!res.ok || !json.success) {
                            throw new Error(json.message || 'Could not credit wallet.');
                        }

                        cfg.balance = Number(json.data.available_balance);
                        ui.balance.textContent = money(cfg.balance);
                        ui.doneAdded.textContent = money(json.data.amount_added);
                        ui.doneBalance.textContent = money(json.data.available_balance);
                        ui.stepPay.classList.add('hidden');
                        ui.stepDone.classList.remove('hidden');
                    } catch (e) {
                        showError(ui.payError, e.message || 'Payment failed.');
                        ui.confirmBtn.innerHTML = original;
                    } finally {
                        ui.confirmBtn.disabled = false;
                        if (!ui.stepDone.classList.contains('hidden')) {
                            // success
                        } else if (!ui.confirmBtn.textContent.includes('Pay')) {
                            ui.confirmBtn.innerHTML = `Pay ${cfg.currency} <span id="pay-btn-amount">${money(amount)}</span>`;
                            ui.payBtnAmount = document.getElementById('pay-btn-amount');
                        }
                    }
                });

                refreshSummary();
                setPresetStyles();
            })();
        </script>
    @endpush
</x-client-layout>
