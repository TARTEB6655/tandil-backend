<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Services\Loyalty\LoyaltyService;
use Illuminate\Http\Request;

class LoyaltyController extends Controller
{
    public function __construct(
        private readonly LoyaltyService $loyalty
    ) {
        $this->middleware(['auth', 'role:client']);
    }

    /**
     * Loyalty points – same payload as API GET /api/client/loyalty.
     */
    public function index(Request $request)
    {
        $payload = $this->loyalty->getScreenPayload($request->user());

        return view('client.loyalty.index', [
            'balance' => $payload['balance'],
            'availableRewards' => $payload['available_rewards'],
            'recentTransactions' => $payload['recent_transactions'],
        ]);
    }
}
