<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\User;
use App\Models\WalletCredit;
use Illuminate\Http\Request;

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

    public function show(Request $request, User $user)
    {
        abort_unless($user->role === 'client', 404);

        $creditStatus = (string) $request->query('credit_status', '');
        $creditsQuery = WalletCredit::query()
            ->where('user_id', $user->id)
            ->with('order')
            ->latest('id');

        if ($creditStatus !== '' && in_array($creditStatus, ['active', 'forfeited', 'used', 'expired'], true)) {
            $creditsQuery->where('status', $creditStatus);
        }

        $orders = Order::query()
            ->where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->paginate(20, ['*'], 'orders_page')
            ->withQueryString();

        $credits = $creditsQuery
            ->paginate(20, ['*'], 'credits_page')
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

        return view('admin.wallet.show', compact('user', 'orders', 'credits', 'orderStats', 'creditStatus', 'walletCreditRows'));
    }
}
