<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\User;
use App\Models\WalletCredit;
use App\Support\RefundPolicy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class WalletController extends Controller
{
    public function overview(): \Illuminate\Http\JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Wallet overview retrieved successfully.',
            'data' => [
                'total_wallet_balance' => (float) User::query()->sum('wallet_balance'),
                'active_wallet_liability' => (float) WalletCredit::query()->where('status', 'active')->sum('amount'),
                'forfeited_total' => (float) WalletCredit::query()->where('status', 'forfeited')->sum('amount'),
                'credits_count' => (int) WalletCredit::query()->count(),
                'active_credits_count' => (int) WalletCredit::query()->where('status', 'active')->count(),
                'expiring_soon_7d_amount' => (float) WalletCredit::query()
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
            ],
        ]);
    }

    public function credits(Request $request): \Illuminate\Http\JsonResponse
    {
        $status = (string) $request->query('status', '');
        $q = trim((string) $request->query('q', ''));
        $perPage = min(max((int) $request->query('per_page', 20), 1), 100);

        $query = WalletCredit::query()
            ->with(['user:id,name,email', 'order:id'])
            ->latest('id');

        if ($status !== '' && in_array($status, ['active', 'forfeited', 'used', 'expired'], true)) {
            $query->where('status', $status);
        }

        if ($q !== '') {
            $query->whereHas('user', function ($sub) use ($q) {
                $sub->where('name', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%");
            });
        }

        $credits = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Wallet credits retrieved successfully.',
            'data' => $credits->items(),
            'pagination' => [
                'current_page' => $credits->currentPage(),
                'last_page' => $credits->lastPage(),
                'per_page' => $credits->perPage(),
                'total' => $credits->total(),
            ],
        ]);
    }

    /**
     * Same payload as admin web "Client wallet & history" (wallet/users/{user}).
     * Query: orders_per_page (1–100, default 20), orders_page (pagination page name matches web).
     */
    public function userDetail(Request $request, User $user): \Illuminate\Http\JsonResponse
    {
        abort_unless($user->role === 'client', 404);

        $perPage = min(max((int) $request->query('orders_per_page', 20), 1), 100);

        $orders = Order::query()
            ->where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->paginate($perPage, ['*'], 'orders_page')
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

        $orders->through(function (Order $order) {
            return [
                'id' => $order->id,
                'placed_at' => $order->created_at?->toIso8601String(),
                'updated_at' => $order->updated_at?->toIso8601String(),
                'order_status' => $order->order_status,
                'order_status_label' => $order->order_status
                    ? ucfirst(str_replace('_', ' ', (string) $order->order_status))
                    : null,
                'payment_status' => $order->payment_status,
                'payment_status_label' => $order->payment_status
                    ? ucfirst((string) $order->payment_status)
                    : null,
                'total_amount' => (float) $order->total_amount,
            ];
        });

        return response()->json([
            'success' => true,
            'message' => 'Client wallet detail retrieved successfully.',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'wallet_balance' => (float) $user->wallet_balance,
                ],
                'wallet_validity_months' => $walletValidityMonths,
                'first_wallet_credit_at' => $firstWalletCreditAt
                    ? \Carbon\Carbon::parse($firstWalletCreditAt)->toIso8601String()
                    : null,
                'next_active_credit_expires_at' => $nextActiveCreditExpiresAt
                    ? \Carbon\Carbon::parse($nextActiveCreditExpiresAt)->toIso8601String()
                    : null,
                'wallet_credit_rows' => $walletCreditRows,
                'order_stats' => $orderStats,
                'orders' => $orders->items(),
                'orders_pagination' => [
                    'page_query' => 'orders_page',
                    'current_page' => $orders->currentPage(),
                    'last_page' => $orders->lastPage(),
                    'per_page' => $orders->perPage(),
                    'total' => $orders->total(),
                ],
            ],
        ]);
    }
}

