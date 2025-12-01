<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Models\Order;
use App\Services\PayPalService;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    protected PayPalService $paypal;

    public function __construct(PayPalService $paypal)
    {
        $this->paypal = $paypal;
    }

    // Create a PayPal order for a subscription or order
    public function createPaypalOrder(Request $request)
    {
        $type = $request->input('type', 'subscription');
        $id = $request->input('id');
        $return = $request->input('return_url', url('/'));
        $cancel = $request->input('cancel_url', url('/'));

        if ($type === 'subscription') {
            $sub = Subscription::find($id);
            if (! $sub) {
                return response()->json(['status' => false, 'message' => 'Subscription not found'], 404);
            }
            $amount = (float) $sub->amount;
            $currency = $request->input('currency', 'USD');
        } else {
            $order = Order::find($id);
            if (! $order) {
                return response()->json(['status' => false, 'message' => 'Order not found'], 404);
            }
            $amount = (float) $order->total_amount;
            $currency = $request->input('currency', 'USD');
        }

        $res = $this->paypal->createOrder($amount, $currency, $return, $cancel);

        // Persist mapping between local model and external order id when available
        $externalId = $res['id'] ?? null;
        if ($type === 'subscription') {
            if ($externalId) {
                $sub->payment_reference = $externalId;
                $sub->save();
            }
        } else {
            if ($externalId) {
                $order->payment_reference = $externalId;
                $order->save();
            }
        }

        return response()->json(['status' => true, 'data' => $res], 200);
    }

    // Webhook endpoint for PayPal events (mark subscription/order paid)
    public function paypalWebhook(Request $request)
    {
        $payload = $request->all();

        // Verify webhook signature when possible
        $headers = [];
        foreach ($request->headers->all() as $k => $v) {
            $headers[strtolower($k)] = $v;
        }

        $verified = $this->paypal->verifyWebhook($headers, $request->getContent());
        if (! $verified) {
            return response()->json(['status' => false, 'message' => 'Webhook verification failed'], 400);
        }

        // Minimal handling: if event indicates order capture, mark related model as paid.
        $event = $payload['event_type'] ?? null;
        if ($event === 'CHECKOUT.ORDER.APPROVED' || $event === 'PAYMENT.CAPTURE.COMPLETED' || $event === 'PAYMENT.CAPTURE.DENIED') {
            $resource = $payload['resource'] ?? [];
            $orderId = $resource['id'] ?? null;

            if ($orderId) {
                // Try subscriptions first
                $sub = Subscription::where('payment_reference', $orderId)->first();
                if ($sub) {
                    $sub->payment_status = 'paid';
                    $sub->paid_at = now();
                    $sub->save();
                }

                // Try orders
                $order = Order::where('payment_reference', $orderId)->first();
                if ($order) {
                    $order->payment_status = 'paid';
                    $order->paid_at = now();
                    $order->order_status = 'paid';
                    $order->save();
                }
            }
        }

        return response()->json(['status' => true], 200);
    }
}
