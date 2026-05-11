<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\WalletCredit;
use App\Support\RefundPolicy;
use Illuminate\Http\Request;

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
}

