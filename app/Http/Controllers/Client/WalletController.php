<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\WalletCredit;
use App\Support\RefundPolicy;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

class WalletController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $path = request()->url();
        $page = max(1, (int) request()->query('page', 1));

        $balanceCol = Schema::hasColumn('users', 'wallet_balance');
        $forfeitedCol = Schema::hasColumn('users', 'wallet_forfeited_total');

        if (! Schema::hasTable('wallet_credits')) {
            $credits = new LengthAwarePaginator([], 0, 20, $page, ['path' => $path]);
            $summary = [
                'balance' => $balanceCol ? (float) ($user->wallet_balance ?? 0) : 0.0,
                'forfeited_total' => $forfeitedCol ? (float) ($user->wallet_forfeited_total ?? 0) : 0.0,
                'active_credits' => 0.0,
                'expiring_soon_7d' => 0.0,
                'wallet_validity_months' => RefundPolicy::walletValidityMonths(),
                'next_active_expiry_at' => null,
            ];

            return view('client.wallet.index', compact('credits', 'summary'));
        }

        try {
            $credits = WalletCredit::query()
                ->where('user_id', $user->id)
                ->latest('id')
                ->paginate(20);

            $activeSum = WalletCredit::query()
                ->where('user_id', $user->id)
                ->where('status', 'active')
                ->sum('amount');
            $expiringSum = WalletCredit::query()
                ->where('user_id', $user->id)
                ->where('status', 'active')
                ->whereNotNull('expires_at')
                ->whereBetween('expires_at', [now(), now()->copy()->addDays(7)])
                ->sum('amount');

            $summary = [
                'balance' => $balanceCol ? (float) ($user->wallet_balance ?? 0) : 0.0,
                'forfeited_total' => $forfeitedCol ? (float) ($user->wallet_forfeited_total ?? 0) : 0.0,
                'active_credits' => (float) ($activeSum ?? 0),
                'expiring_soon_7d' => (float) ($expiringSum ?? 0),
                'wallet_validity_months' => RefundPolicy::walletValidityMonths(),
                'next_active_expiry_at' => WalletCredit::query()
                    ->where('user_id', $user->id)
                    ->where('status', 'active')
                    ->whereNotNull('expires_at')
                    ->where('expires_at', '>', now())
                    ->min('expires_at'),
            ];
        } catch (Throwable $e) {
            Log::warning('Client wallet page: ledger query failed', [
                'user_id' => $user->id,
                'message' => $e->getMessage(),
            ]);
            $credits = new LengthAwarePaginator([], 0, 20, $page, ['path' => $path]);
            $summary = [
                'balance' => $balanceCol ? (float) ($user->wallet_balance ?? 0) : 0.0,
                'forfeited_total' => $forfeitedCol ? (float) ($user->wallet_forfeited_total ?? 0) : 0.0,
                'active_credits' => 0.0,
                'expiring_soon_7d' => 0.0,
                'wallet_validity_months' => RefundPolicy::walletValidityMonths(),
                'next_active_expiry_at' => null,
            ];
        }

        return view('client.wallet.index', compact('credits', 'summary'));
    }
}

