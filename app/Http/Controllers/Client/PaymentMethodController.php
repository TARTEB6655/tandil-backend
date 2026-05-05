<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\UserPaymentMethod;

class PaymentMethodController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'role:client']);
    }

    /**
     * List saved payment methods – same source as API GET /api/user/payment-methods.
     */
    public function index()
    {
        $paymentMethods = UserPaymentMethod::query()
            ->where('user_id', auth()->id())
            ->orderByDesc('is_default')
            ->orderByDesc('id')
            ->get();

        return view('client.payment-methods.index', compact('paymentMethods'));
    }
}
