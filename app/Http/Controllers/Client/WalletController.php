<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\WalletCredit;
use Illuminate\Support\Facades\Auth;

class WalletController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $credits = WalletCredit::query()
            ->where('user_id', $user->id)
            ->latest('id')
            ->paginate(20);

        $summary = [
            'balance' => (float) ($user->wallet_balance ?? 0),
            'forfeited_total' => (float) ($user->wallet_forfeited_total ?? 0),
            'active_credits' => (float) WalletCredit::query()
                ->where('user_id', $user->id)
                ->where('status', 'active')
                ->sum('amount'),
            'expiring_soon_7d' => (float) WalletCredit::query()
                ->where('user_id', $user->id)
                ->where('status', 'active')
                ->whereNotNull('expires_at')
                ->whereBetween('expires_at', [now(), now()->copy()->addDays(7)])
                ->sum('amount'),
        ];

        return view('client.wallet.index', compact('credits', 'summary'));
    }
}

