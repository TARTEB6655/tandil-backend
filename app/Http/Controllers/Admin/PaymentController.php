<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Setting;
use App\Models\ShopMobileCheckout;
use App\Models\Transaction;
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
        return view('admin.payments.settings', ['gateways' => $this->gatewaySettings()]);
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
                'public_key' => 'nullable|string',
                'secret_key' => 'nullable|string',
                'webhook_secret' => 'nullable|string',
            ]);
            Setting::set('stripe_public_key', $request->public_key ?? '', 'text', 'payment');
            Setting::set('stripe_secret_key', $request->secret_key ?? '', 'text', 'payment');
            Setting::set('stripe_webhook_secret', $request->webhook_secret ?? '', 'text', 'payment');
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

        return redirect()->route('admin.payments.settings')->with('success', ucfirst($gateway).' settings updated successfully.');
    }

    private function gatewaySettings(): array
    {
        return [
            'stripe' => [
                'enabled' => Setting::get('stripe_enabled', false),
                'public_key' => Setting::get('stripe_public_key', ''),
                'secret_key' => Setting::get('stripe_secret_key', ''),
                'webhook_secret' => Setting::get('stripe_webhook_secret', ''),
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
