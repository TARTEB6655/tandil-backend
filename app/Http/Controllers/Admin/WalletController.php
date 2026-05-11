<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\User;
use App\Models\WalletCredit;
use App\Support\RefundPolicy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class WalletController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $perPage = min(max((int) $request->query('per_page', 20), 10), 100);

        $usersQuery = User::query()
            ->where('role', 'client')
            ->orderBy('name');

        if ($q !== '') {
            $usersQuery->where(function ($sub) use ($q) {
                $sub->where('name', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%");
            });
        }

        $users = $usersQuery->paginate($perPage)->withQueryString();

        $summary = [
            'active_wallet_liability' => (float) WalletCredit::query()->where('status', 'active')->sum('amount'),
            'forfeited_total' => (float) WalletCredit::query()->where('status', 'forfeited')->sum('amount'),
            'total_wallet_balance' => (float) User::query()->sum('wallet_balance'),
        ];

        return view('admin.wallet.index', compact('users', 'summary', 'q', 'perPage'));
    }

    public function show(User $user)
    {
        abort_unless($user->role === 'client', 404);

        $orders = Order::query()
            ->where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->paginate(20, ['*'], 'orders_page')
            ->withQueryString();

        $paidQuery = Order::query()
            ->where('user_id', $user->id)
            ->where('payment_status', 'paid')
            ->where(function ($w) {
                $w->whereNull('order_status')
                    ->orWhere('order_status', '<>', 'cancelled');
            });

        $orderStats = [
            'paid_orders_count' => (int) (clone $paidQuery)->count(),
            'paid_orders_total_aed' => (float) (clone $paidQuery)->sum('total_amount'),
            'cancelled_orders_count' => (int) Order::query()
                ->where('user_id', $user->id)
                ->where('order_status', 'cancelled')
                ->count(),
            'cancelled_orders_total_aed' => (float) Order::query()
                ->where('user_id', $user->id)
                ->where('order_status', 'cancelled')
                ->sum('total_amount'),
        ];

        $walletCreditRows = (int) WalletCredit::query()->where('user_id', $user->id)->count();

        $firstWalletCreditAt = null;
        $nextActiveCreditExpiresAt = null;
        if (Schema::hasTable('wallet_credits')) {
            $firstWalletCreditAt = WalletCredit::query()
                ->where('user_id', $user->id)
                ->min('credited_at');
            $nextActiveCreditExpiresAt = WalletCredit::query()
                ->where('user_id', $user->id)
                ->where('status', 'active')
                ->whereNotNull('expires_at')
                ->where('expires_at', '>', now())
                ->min('expires_at');
        }

        $walletValidityMonths = RefundPolicy::walletValidityMonths();

        return view('admin.wallet.show', compact(
            'user',
            'orders',
            'orderStats',
            'walletCreditRows',
            'firstWalletCreditAt',
            'nextActiveCreditExpiresAt',
            'walletValidityMonths'
        ));
    }
}
