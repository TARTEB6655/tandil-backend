<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class PaymentMethodController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'role:client']);
    }

    /**
     * List saved payment methods – same as API GET /api/user/payment-methods (placeholder).
     */
    public function index()
    {
        $paymentMethods = []; // No saved cards yet
        return view('client.payment-methods.index', compact('paymentMethods'));
    }
}
