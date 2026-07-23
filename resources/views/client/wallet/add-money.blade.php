<x-client-layout>
    @php
        $balance = (float) ($options['available_balance'] ?? 0);
        $presets = array_values($options['presets'] ?? [50, 100, 150, 200]);
        $minAmount = (float) ($options['min_amount'] ?? 2);
        $maxAmount = (float) ($options['max_amount'] ?? 5000);
        $currency = $options['currency'] ?? 'AED';
        $methods = $options['payment_methods'] ?? [];
        $defaultAmount = in_array(100, $presets, true) ? 100 : ($presets[0] ?? $minAmount);
    @endphp

    <div class="mx-auto max-w-lg space-y-6" id="wallet-add-money"
         data-balance="{{ $balance }}"
         data-presets='@json($presets)'
         data-min="{{ $minAmount }}"
         data-max="{{ $maxAmount }}"
         data-currency="{{ $currency }}"
         data-default-amount="{{ $defaultAmount }}"
         data-intent-url="{{ route('client.wallet.add-money.payment-intent') }}"
         data-confirm-url="{{ route('client.wallet.add-money.confirm') }}"
         data-wallet-url="{{ route('client.wallet.index') }}"
         data-csrf="{{ csrf_token() }}">

        <div class="flex items-center gap-3">
            <a href="{{ route('client.wallet.index') }}" class="rounded-lg p-2 text-gray-500 hover:bg-gray-100 hover:text-gray-800" aria-label="Back">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </a>
            <div>
                <h1 class="text-xl font-semibold text-gray-900">Add Money</h1>
                <p class="text-sm text-gray-500">Top up your wallet with card or Apple Pay</p>
            </div>
        </div>

        <div class="rounded-2xl border border-emerald-100 bg-[#f4f1ea] px-5 py-4">
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-full bg-emerald-100 text-emerald-700">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Available balance</p>
                    <p class="text-2xl font-bold text-emerald-800">{{ $currency }} <span id="ui-balance">{{ number_format($balance, 2) }}</span></p>
                </div>
            </div>
        </div>

        {{-- Amount step --}}
        <div id="step-amount" class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm space-y-4">
            <div>
                <h2 class="text-base font-semibold text-gray-900">Select amount</h2>
                <p class="mt-1 text-sm text-gray-500">Choose a preset or enter a custom amount to top up your wallet.</p>
            </div>

            <div class="grid grid-cols-2 gap-3" id="preset-grid">
                @foreach($presets as $preset)
                    <button type="button"
                            data-preset="{{ $preset }}"
                            class="preset-btn rounded-xl border-2 px-4 py-3 text-sm font-semibold transition-colors {{ (float) $preset === (float) $defaultAmount ? 'border-emerald-700 bg-emerald-50 text-emerald-800' : 'border-transparent bg-[#f4f1ea] text-gray-800 hover:border-emerald-300' }}">
                        {{ $currency }} {{ $preset }}
                    </button>
                @endforeach
            </div>

            <div>
                <label for="custom-amount" class="text-sm font-medium text-gray-700">Or enter custom amount</label>
                <div class="mt-1.5 flex rounded-xl border border-gray-200 bg-[#f4f1ea] focus-within:border-emerald-600 focus-within:ring-1 focus-within:ring-emerald-600">
                    <span class="flex items-center pl-3 text-sm font-medium text-gray-500">{{ $currency }}</span>
                    <input id="custom-amount" type="number" min="0" step="0.01" placeholder="Enter amount"
                           class="w-full border-0 bg-transparent px-2 py-3 text-sm text-gray-900 placeholder:text-gray-400 focus:ring-0">
                </div>
                <p class="mt-1 text-xs text-gray-500">
                    Min {{ $currency }} {{ number_format($minAmount, 2) }} · Max {{ $currency }} {{ number_format($maxAmount, 2) }}
                </p>
            </div>

            <div class="rounded-xl bg-[#f4f1ea] px-4 py-3 text-sm space-y-2">
                <div class="flex justify-between text-gray-700">
                    <span>You will add</span>
                    <span class="font-semibold">{{ $currency }} <span id="ui-add">{{ number_format($defaultAmount, 2) }}</span></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-700">New balance</span>
                    <span class="font-bold text-emerald-800">{{ $currency }} <span id="ui-new">{{ number_format($balance + $defaultAmount, 2) }}</span></span>
                </div>
            </div>

            <div>
                <p class="text-sm font-semibold text-gray-900 mb-2">Payment method</p>
                <div class="space-y-2">
                    @forelse($methods as $i => $method)
                        <label class="flex cursor-pointer items-start gap-3 rounded-xl border-2 p-3 transition-colors method-label {{ $i === 0 ? 'border-emerald-700 bg-emerald-50' : 'border-gray-200' }}">
                            <input type="radio" name="payment_method" value="{{ $method['id'] }}" class="mt-1 text-emerald-700 focus:ring-emerald-600" {{ $i === 0 ? 'checked' : '' }}>
                            <span>
                                <span class="block text-sm font-semibold text-gray-900">{{ $method['label'] ?? ucfirst($method['id']) }}</span>
                                <span class="block text-xs text-gray-500">{{ $method['description'] ?? '' }}</span>
                            </span>
                        </label>
                    @empty
                        <label class="flex cursor-pointer items-start gap-3 rounded-xl border-2 border-emerald-700 bg-emerald-50 p-3">
                            <input type="radio" name="payment_method" value="stripe" class="mt-1" checked>
                            <span>
                                <span class="block text-sm font-semibold">Stripe</span>
                                <span class="block text-xs text-gray-500">Pay with card via Stripe</span>
                            </span>
                        </label>
                    @endforelse
                </div>
            </div>

            <p class="text-xs text-gray-500">Funds stay in your wallet and can be used at checkout. This flow is separate from shop checkout.</p>
            <p id="amount-error" class="hidden text-sm text-rose-600"></p>

            <button type="button" id="btn-start-pay"
                    class="w-full rounded-xl bg-emerald-800 px-4 py-3.5 text-sm font-semibold text-white hover:bg-emerald-900 disabled:opacity-60 transition-colors">
                Add {{ $currency }} <span id="btn-amount-label">{{ number_format($defaultAmount, 2) }}</span>
            </button>
        </div>

        {{-- Pay step --}}
        <div id="step-pay" class="hidden rounded-2xl border border-gray-200 bg-white p-5 shadow-sm space-y-4">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <h2 class="text-base font-semibold text-gray-900">Complete payment</h2>
                    <p class="mt-1 text-sm text-gray-500">
                        Amount: <span class="font-semibold text-emerald-800">{{ $currency }} <span id="pay-amount-label">0.00</span></span>
                    </p>
                </div>
                <button type="button" id="btn-change-amount" class="text-sm font-medium text-emerald-800 hover:underline">Change amount</button>
            </div>
            <div id="payment-element" class="min-h-[180px] rounded-xl border border-gray-100 p-3"></div>
            <p id="pay-error" class="hidden text-sm text-rose-600"></p>
            <button type="button" id="btn-confirm-pay"
                    class="w-full rounded-xl bg-emerald-800 px-4 py-3.5 text-sm font-semibold text-white hover:bg-emerald-900 disabled:opacity-60 transition-colors">
                Pay {{ $currency }} <span id="pay-btn-amount">0.00</span>
            </button>
        </div>

        {{-- Done --}}
        <div id="step-done" class="hidden rounded-2xl border border-emerald-200 bg-emerald-50 p-6 text-center space-y-3">
            <p class="text-lg font-semibold text-emerald-900">Money added successfully</p>
            <p class="text-sm text-emerald-800">
                Added {{ $currency }} <span id="done-added">0.00</span>.
                New balance: <strong>{{ $currency }} <span id="done-balance">0.00</span></strong>
            </p>
            <a href="{{ route('client.wallet.index') }}" class="inline-flex rounded-lg bg-emerald-800 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-900">Back to wallet</a>
        </div>
    </div>

    @push('scripts')
        <script src="https://js.stripe.com/v3/"></script>
        <script>
            (function () {
                const root = document.getElementById('wallet-add-money');
                if (!root) return;

                const cfg = {
                    balance: parseFloat(root.dataset.balance),
                    presets: JSON.parse(root.dataset.presets || '[]'),
                    min: parseFloat(root.dataset.min),
                    max: parseFloat(root.dataset.max),
                    currency: root.dataset.currency,
                    intentUrl: root.dataset.intentUrl,
                    confirmUrl: root.dataset.confirmUrl,
                    walletUrl: root.dataset.walletUrl,
                    csrf: root.dataset.csrf,
                };

                let amount = parseFloat(root.dataset.defaultAmount);
                let stripe = null;
                let elements = null;
                let paymentIntentId = null;

                const el = {
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
                    el.add.textContent = money(amount);
                    el.neu.textContent = money(cfg.balance + amount);
                    el.btnLabel.textContent = money(amount);
                    el.payAmount.textContent = money(amount);
                    el.payBtnAmount.textContent = money(amount);
                }

                function setPresetActive(value) {
                    document.querySelectorAll('.preset-btn').forEach((btn) => {
                        const active = parseFloat(btn.dataset.preset) === value && !el.custom.value;
                        btn.classList.toggle('border-emerald-700', active);
                        btn.classList.toggle('bg-emerald-50', active);
                        btn.classList.toggle('text-emerald-800', active);
                        btn.classList.toggle('border-transparent', !active);
                        btn.classList.toggle('bg-[#f4f1ea]', !active);
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
                        amount = parseFloat(btn.dataset.preset);
                        el.custom.value = '';
                        setPresetActive(amount);
                        showError(el.amountError, '');
                        refreshSummary();
                    });
                });

                el.custom.addEventListener('input', () => {
                    const v = parseFloat(el.custom.value);
                    if (!isNaN(v) && v > 0) {
                        amount = Math.round(v * 100) / 100;
                        setPresetActive(null);
                        refreshSummary();
                    }
                });

                document.querySelectorAll('input[name="payment_method"]').forEach((input) => {
                    input.addEventListener('change', () => {
                        document.querySelectorAll('.method-label').forEach((label) => {
                            const checked = label.querySelector('input').checked;
                            label.classList.toggle('border-emerald-700', checked);
                            label.classList.toggle('bg-emerald-50', checked);
                            label.classList.toggle('border-gray-200', !checked);
                        });
                    });
                });

                el.startBtn.addEventListener('click', async () => {
                    showError(el.amountError, '');
                    if (amount < cfg.min) {
                        showError(el.amountError, `Minimum amount is ${cfg.currency} ${money(cfg.min)}.`);
                        return;
                    }
                    if (amount > cfg.max) {
                        showError(el.amountError, `Maximum amount is ${cfg.currency} ${money(cfg.max)}.`);
                        return;
                    }

                    const method = (document.querySelector('input[name="payment_method"]:checked') || {}).value || 'stripe';
                    el.startBtn.disabled = true;
                    el.startBtn.textContent = 'Preparing payment…';

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
                            appearance: { theme: 'stripe', variables: { colorPrimary: '#065f46' } },
                        });

                        el.stepAmount.classList.add('hidden');
                        el.stepPay.classList.remove('hidden');
                        el.paymentMount.innerHTML = '';
                        elements.create('payment').mount('#payment-element');
                        refreshSummary();
                    } catch (e) {
                        showError(el.amountError, e.message || 'Payment setup failed.');
                    } finally {
                        el.startBtn.disabled = false;
                        el.startBtn.innerHTML = `Add ${cfg.currency} <span id="btn-amount-label">${money(amount)}</span>`;
                        el.btnLabel = document.getElementById('btn-amount-label');
                    }
                });

                el.changeBtn.addEventListener('click', () => {
                    el.stepPay.classList.add('hidden');
                    el.stepAmount.classList.remove('hidden');
                    el.paymentMount.innerHTML = '';
                    elements = null;
                    showError(el.payError, '');
                });

                el.confirmBtn.addEventListener('click', async () => {
                    if (!stripe || !elements) return;
                    el.confirmBtn.disabled = true;
                    showError(el.payError, '');

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
                        el.balance.textContent = money(cfg.balance);
                        el.doneAdded.textContent = money(json.data.amount_added);
                        el.doneBalance.textContent = money(json.data.available_balance);
                        el.stepPay.classList.add('hidden');
                        el.stepDone.classList.remove('hidden');
                    } catch (e) {
                        showError(el.payError, e.message || 'Payment failed.');
                    } finally {
                        el.confirmBtn.disabled = false;
                    }
                });

                refreshSummary();
            })();
        </script>
    @endpush
</x-client-layout>
