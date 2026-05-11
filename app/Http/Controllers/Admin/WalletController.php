<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\User;
use App\Models\WalletCredit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

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
        ];

        $focusUser = null;
        $userMatchCount = 0;
        $userInsight = null;

        if ($q !== '') {
            $userMatchCount = (int) User::query()
                ->where(function ($sub) use ($q) {
                    $sub->where('name', 'like', "%{$q}%")
                        ->orWhere('email', 'like', "%{$q}%");
                })
                ->count();

            $focusUser = User::query()
                ->where(function ($sub) use ($q) {
                    $sub->where('name', 'like', "%{$q}%")
                        ->orWhere('email', 'like', "%{$q}%");
                })
                ->orderBy('id')
                ->first();

            if ($focusUser !== null && Schema::hasTable('orders')) {
                $uid = (int) $focusUser->id;

                $paidQuery = Order::query()
                    ->where('user_id', $uid)
                    ->where('payment_status', 'paid')
                    ->where(function ($w) {
                        $w->whereNull('order_status')
                            ->orWhere('order_status', '<>', 'cancelled');
                    });

                $userInsight = [
                    'wallet_balance' => (float) ($focusUser->wallet_balance ?? 0),
                    'active_credits_aed' => (float) WalletCredit::query()
                        ->where('user_id', $uid)
                        ->where('status', 'active')
                        ->sum('amount'),
                    'wallet_credit_rows' => (int) WalletCredit::query()->where('user_id', $uid)->count(),
                    'paid_orders_count' => (int) (clone $paidQuery)->count(),
                    'paid_orders_total_aed' => (float) (clone $paidQuery)->sum('total_amount'),
                    'cancelled_orders_count' => (int) Order::query()
                        ->where('user_id', $uid)
                        ->where('order_status', 'cancelled')
                        ->count(),
                    'cancelled_orders_total_aed' => (float) Order::query()
                        ->where('user_id', $uid)
                        ->where('order_status', 'cancelled')
                        ->sum('total_amount'),
                ];
            }
        }

        return view('admin.wallet.index', compact(
            'credits',
            'summary',
            'status',
            'q',
            'perPage',
            'focusUser',
            'userInsight',
            'userMatchCount'
        ));
    }
}
