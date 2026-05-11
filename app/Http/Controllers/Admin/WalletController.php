<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\WalletCredit;
use App\Support\RefundPolicy;
use Illuminate\Http\Request;

class WalletController extends Controller
{
    public function index(Request $request)
    {
        $status = (string) $request->query('status', '');
        $q = trim((string) $request->query('q', ''));
        $perPage = min(max((int) $request->query('per_page', 20), 10), 100);

        $creditsQuery = WalletCredit::query()
            ->with(['user:id,name,email', 'order:id'])
            ->latest('id');

        if ($status !== '' && in_array($status, ['active', 'forfeited', 'used', 'expired'], true)) {
            $creditsQuery->where('status', $status);
        }

        if ($q !== '') {
            $creditsQuery->whereHas('user', function ($sub) use ($q) {
                $sub->where('name', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%");
            });
        }

        $credits = $creditsQuery->paginate($perPage)->withQueryString();

        $summary = [
            'active_wallet_liability' => (float) WalletCredit::query()->where('status', 'active')->sum('amount'),
            'forfeited_total' => (float) WalletCredit::query()->where('status', 'forfeited')->sum('amount'),
            'total_wallet_balance' => (float) User::query()->sum('wallet_balance'),
            'expiring_soon_7d' => (float) WalletCredit::query()
                ->where('status', 'active')
                ->whereNotNull('expires_at')
                ->whereBetween('expires_at', [now(), now()->copy()->addDays(7)])
                ->sum('amount'),
            'wallet_validity_months' => RefundPolicy::walletValidityMonths(),
            'next_active_expiry_at' => WalletCredit::query()
                ->where('status', 'active')
                ->whereNotNull('expires_at')
                ->where('expires_at', '>', now())
                ->min('expires_at'),
        ];

        return view('admin.wallet.index', compact('credits', 'summary', 'status', 'q', 'perPage'));
    }
}

