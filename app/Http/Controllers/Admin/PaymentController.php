<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Setting;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PaymentController extends Controller
{
    public function __construct()
    {
        $this->middleware('role:admin');
    }

    /**
     * Display payment gateway settings and transaction logs.
     */
    public function index(Request $request)
    {
        $query = Transaction::with('transactionable')->orderBy('created_at', 'desc');

        // Filters
        if ($request->has('status') && $request->status) {
            $query->where('status', $request->status);
        }

        if ($request->has('gateway') && $request->gateway) {
            $query->where('gateway', $request->gateway);
        }

        if ($request->has('search') && $request->search) {
            $query->where('transaction_id', 'LIKE', "%{$request->search}%")
                ->orWhere('gateway_transaction_id', 'LIKE', "%{$request->search}%");
        }

        $transactions = $query->paginate(20);

        // Get payment gateway settings
        $gateways = [
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

        return view('admin.payments.index', compact('transactions', 'gateways'));
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

        return redirect()->back()->with('success', ucfirst($gateway).' settings updated successfully.');
    }

    /**
     * Process refund for an order.
     */
    public function refund(Request $request, $orderId)
    {
        $order = Order::findOrFail($orderId);

        $validated = $request->validate([
            'refund_amount' => 'required|numeric|min:0.01|max:'.$order->total_amount,
            'refund_reason' => 'nullable|string|max:500',
        ]);

        // Create refund transaction
        $transaction = Transaction::create([
            'transaction_id' => 'REF-'.Str::upper(Str::random(12)),
            'transactionable_type' => Order::class,
            'transactionable_id' => $order->id,
            'type' => 'refund',
            'gateway' => $order->payment_method ?? 'manual',
            'amount' => $validated['refund_amount'],
            'currency' => 'AED',
            'status' => 'completed',
            'notes' => $validated['refund_reason'] ?? 'Admin refund',
            'processed_at' => now(),
        ]);

        // Update order
        $order->update([
            'payment_status' => 'refunded',
            'refunded_at' => now(),
            'refund_amount' => $validated['refund_amount'],
            'refund_reason' => $validated['refund_reason'] ?? null,
        ]);

        return redirect()->back()->with('success', 'Refund processed successfully. Transaction ID: '.$transaction->transaction_id);
    }

    /**
     * View transaction details.
     */
    public function showTransaction($id)
    {
        $transaction = Transaction::with('transactionable')->findOrFail($id);

        return view('admin.payments.transaction', compact('transaction'));
    }
}
