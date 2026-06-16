<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Setting;
use App\Models\ShopMobileCheckout;
use App\Models\Transaction;
use App\Support\RefundPolicy;
use App\Support\StripeCredentials;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function __construct()
    {
        $this->middleware('role:admin');
    }

    /**
     * Stripe-style payment activity (orders + open mobile checkouts).
     */
    public function transactions(Request $request)
    {
        $type = $request->input('type', 'shop');
        if (! in_array($type, ['shop', 'all'], true)) {
            $type = 'shop';
        }
        $shopOnly = $type !== 'all';

        $baseForCounts = Order::query();
        if ($shopOnly) {
            $baseForCounts->whereNull('package_id');
        }
        if ($request->filled('date_from')) {
            $baseForCounts->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $baseForCounts->whereDate('created_at', '<=', $request->date_to);
        }
        if ($request->filled('gateway')) {
            $baseForCounts->where('payment_method', $request->gateway);
        }
        if ($request->filled('q')) {
            $q = trim((string) $request->input('q'));
            $baseForCounts->where(function ($sub) use ($q) {
                $sub->where('guest_email', 'like', "%{$q}%")
                    ->orWhere('guest_full_name', 'like', "%{$q}%")
                    ->orWhere('payment_reference', 'like', "%{$q}%")
                    ->orWhere('id', $q)
                    ->orWhereHas('user', function ($u) use ($q) {
                        $u->where('email', 'like', "%{$q}%")
                            ->orWhere('name', 'like', "%{$q}%");
                    });
            });
        }

        $statusCounts = [
            'all' => (clone $baseForCounts)->count(),
            'paid' => (clone $baseForCounts)->where('payment_status', 'paid')->count(),
            'pending' => (clone $baseForCounts)->where('payment_status', 'pending')->count(),
            'failed' => (clone $baseForCounts)->where('payment_status', 'failed')->count(),
            'refunded' => (clone $baseForCounts)->where('payment_status', 'refunded')->count(),
        ];

        $query = Order::query()
            ->with(['user', 'shippingAddress'])
            ->orderByDesc('created_at');

        if ($shopOnly) {
            $query->whereNull('package_id');
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $tab = $request->input('tab', 'all');
        if ($tab !== 'all' && ! in_array($tab, ['paid', 'pending', 'failed', 'refunded'], true)) {
            $tab = 'all';
        }
        if ($tab !== 'all') {
            $query->where('payment_status', $tab);
        }

        if ($request->filled('gateway')) {
            $query->where('payment_method', $request->gateway);
        }

        if ($request->filled('q')) {
            $q = trim((string) $request->input('q'));
            $query->where(function ($sub) use ($q) {
                $sub->where('guest_email', 'like', "%{$q}%")
                    ->orWhere('guest_full_name', 'like', "%{$q}%")
                    ->orWhere('payment_reference', 'like', "%{$q}%")
                    ->orWhere('id', $q)
                    ->orWhereHas('user', function ($u) use ($q) {
                        $u->where('email', 'like', "%{$q}%")
                            ->orWhere('name', 'like', "%{$q}%");
                    });
            });
        }

        $orders = $query->paginate(20)->withQueryString();

        $openMobileCheckouts = ShopMobileCheckout::query()
            ->with('user')
            ->whereNull('consumed_at')
            ->orderByDesc('updated_at')
            ->limit(30)
            ->get();

        $currency = strtoupper((string) config('shop.currency', 'AED'));

        return view('admin.payments.transactions', compact(
            'orders',
            'statusCounts',
            'openMobileCheckouts',
            'type',
            'tab',
            'currency'
        ));
    }

    /**
     * Gateway configuration only.
     */
    public function settings()
    {
        return view('admin.payments.settings', [
            'gateways' => $this->gatewaySettings(),
            'stripeDiagnostics' => [
                'mode' => StripeCredentials::mode(),
                'configuration_issues' => StripeCredentials::blockingConfigurationIssues(),
                'configuration_notes' => StripeCredentials::configurationNotes(),
                'secret_key_prefix' => StripeCredentials::maskedSecretPrefix(),
                'publishable_key_prefix' => StripeCredentials::maskedPublishablePrefix(),
            ],
            'refundPolicy' => [
                'grace_minutes' => RefundPolicy::graceMinutes(),
                'partial_refund_percent' => RefundPolicy::partialRefundPercent(),
                'service_fee_percent_after_start' => RefundPolicy::serviceFeePercentAfterStart(),
                'wallet_validity_months' => RefundPolicy::walletValidityMonths(),
            ],
        ]);
    }

    /**
     * Full payment / customer context for one order.
     */
    public function showOrderPayment(Order $order)
    {
        $order->load(['user', 'shippingAddress', 'items.product', 'package', 'transactions']);

        return view('admin.payments.order-payment', compact('order'));
    }

    /**
     * Incomplete Stripe mobile checkout (no order yet).
     */
    public function showMobileCheckout(ShopMobileCheckout $checkout)
    {
        $checkout->load('user');

        return view('admin.payments.mobile-checkout', compact('checkout'));
    }

    /**
     * Legacy row from transactions table (e.g. admin refund log).
     */
    public function showTransaction($id)
    {
        $transaction = Transaction::with('transactionable')->findOrFail($id);

        return view('admin.payments.transaction', compact('transaction'));
    }

    /**
     * Update payment gateway settings.
     */
    public function updateGateway(Request $request, $gateway)
    {
        abort_unless(in_array($gateway, ['stripe', 'paypal'], true), 404);

        $request->validate([
            'enabled' => 'boolean',
        ]);

        Setting::set("{$gateway}_enabled", $request->has('enabled') ? true : false, 'boolean', 'payment');

        if ($gateway === 'stripe') {
            $request->validate([
                'stripe_mode' => 'required|in:live,test',
                'test_public_key' => 'nullable|string',
                'test_secret_key' => 'nullable|string',
                'live_public_key' => 'nullable|string',
                'live_secret_key' => 'nullable|string',
                'test_webhook_secret' => 'nullable|string',
                'live_webhook_secret' => 'nullable|string',
            ]);

            $activeMode = (string) $request->input('stripe_mode', 'test');
            $errors = [];
            $pending = [];

            foreach (['test', 'live'] as $slotMode) {
                $publicInput = StripeCredentials::normalizeKey((string) $request->input("{$slotMode}_public_key", ''));
                $secretInput = StripeCredentials::normalizeKey((string) $request->input("{$slotMode}_secret_key", ''));
                $effectivePublic = $publicInput !== ''
                    ? $publicInput
                    : StripeCredentials::storedPublicForMode($slotMode);
                $effectiveSecret = $secretInput !== ''
                    ? $secretInput
                    : StripeCredentials::storedSecretForMode($slotMode);

                $slotIssues = StripeCredentials::validateModeSlot($slotMode, $effectiveSecret, $effectivePublic);
                if ($slotIssues !== []) {
                    $errors[] = ucfirst($slotMode).' keys: '.implode(' ', $slotIssues);
                }

                $pending[$slotMode] = [
                    'public_input' => $publicInput,
                    'secret_input' => $secretInput,
                ];
            }

            $activeKeys = [
                'public' => StripeCredentials::storedPublicForMode($activeMode),
                'secret' => StripeCredentials::storedSecretForMode($activeMode),
            ];
            if ($pending[$activeMode]['public_input'] !== '') {
                $activeKeys['public'] = $pending[$activeMode]['public_input'];
            }
            if ($pending[$activeMode]['secret_input'] !== '') {
                $activeKeys['secret'] = $pending[$activeMode]['secret_input'];
            }

            $activeIssues = StripeCredentials::validateKeyPair($activeKeys['secret'], $activeKeys['public']);
            if ($activeIssues !== []) {
                $errors[] = 'Active '.ucfirst($activeMode).' mode: '.implode(' ', $activeIssues);
            }

            if ($errors !== []) {
                return redirect()
                    ->route('admin.payments.settings')
                    ->withInput()
                    ->withErrors(['stripe_keys' => implode(' ', $errors)]);
            }

            foreach (['test', 'live'] as $slotMode) {
                StripeCredentials::applyModeKeyUpdates(
                    $slotMode,
                    $pending[$slotMode]['public_input'],
                    $pending[$slotMode]['secret_input']
                );
            }

            Setting::set('stripe_mode', $activeMode, 'text', 'payment');

            $testWebhook = StripeCredentials::normalizeKey((string) $request->input('test_webhook_secret', ''));
            $liveWebhook = StripeCredentials::normalizeKey((string) $request->input('live_webhook_secret', ''));
            if ($testWebhook !== '') {
                Setting::set('stripe_test_webhook_secret', $testWebhook, 'text', 'payment');
            }
            if ($liveWebhook !== '') {
                Setting::set('stripe_live_webhook_secret', $liveWebhook, 'text', 'payment');
            }

            Setting::set('stripe_keys_version', (string) ((int) Setting::get('stripe_keys_version', 0) + 1), 'number', 'payment');

            StripeCredentials::forgetCachedSettings();
            ShopMobileCheckout::query()
                ->whereNull('consumed_at')
                ->delete();

            return redirect()->route('admin.payments.settings');
        } elseif ($gateway === 'paypal') {
            $request->validate([
                'client_id' => 'nullable|string',
                'client_secret' => 'nullable|string',
                'mode' => 'nullable|in:sandbox,live',
            ]);
            Setting::set('paypal_client_id', $request->client_id ?? '', 'text', 'payment');
            Setting::set('paypal_client_secret', $request->client_secret ?? '', 'text', 'payment');
            Setting::set('paypal_mode', $request->mode ?? 'sandbox', 'text', 'payment');
        }

        return redirect()->route('admin.payments.settings');
    }

    public function updateRefundPolicy(Request $request)
    {
        $validated = $request->validate([
            'refund_grace_minutes' => 'required|integer|min:0|max:1440',
            'refund_partial_percent' => 'required|numeric|min:0|max:100',
            'refund_service_fee_percent_after_start' => 'required|numeric|min:0|max:100',
            'refund_wallet_validity_months' => 'required|integer|min:1|max:24',
        ]);

        Setting::set('refund_grace_minutes', (string) $validated['refund_grace_minutes'], 'number', 'payment');
        Setting::set('refund_partial_percent', (string) $validated['refund_partial_percent'], 'number', 'payment');
        Setting::set('refund_service_fee_percent_after_start', (string) $validated['refund_service_fee_percent_after_start'], 'number', 'payment');
        Setting::set('refund_wallet_validity_months', (string) $validated['refund_wallet_validity_months'], 'number', 'payment');

        return redirect()->route('admin.payments.settings')->with('success', 'Refund policy updated successfully.');
    }

    private function gatewaySettings(): array
    {
        return [
            'stripe' => [
                'enabled' => Setting::get('stripe_enabled', false),
                'mode' => StripeCredentials::activeMode(),
                'test' => StripeCredentials::adminModeSettings('test'),
                'live' => StripeCredentials::adminModeSettings('live'),
            ],
            'paypal' => [
                'enabled' => Setting::get('paypal_enabled', false),
                'client_id' => Setting::get('paypal_client_id', ''),
                'client_secret' => Setting::get('paypal_client_secret', ''),
                'mode' => Setting::get('paypal_mode', 'sandbox'),
            ],
        ];
    }
}
