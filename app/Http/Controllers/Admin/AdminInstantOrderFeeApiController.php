<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\InstantOrderFee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Dedicated admin API for Instant Order surcharge (separate from shop shipping/tax settings).
 */
class AdminInstantOrderFeeApiController extends Controller
{
    /**
     * GET /api/admin/settings/instant-order-fee
     */
    public function show(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => InstantOrderFee::adminApiPayload(),
        ]);
    }

    /**
     * PUT /api/admin/settings/instant-order-fee
     * Body: { "instant_order_fee_amount": 20, "instant_order_fee_enabled": true }
     * Aliases: amount / extra_fee_amount / enabled / extra_fee_enabled.
     * Use amount 0 or enabled false to disable at checkout.
     */
    public function update(Request $request): JsonResponse
    {
        $request->validate([
            'instant_order_fee_amount' => 'nullable|numeric|min:0',
            'extra_fee_amount' => 'nullable|numeric|min:0',
            'amount' => 'nullable|numeric|min:0',
            'fee_amount' => 'nullable|numeric|min:0',
            'instant_order_fee_enabled' => 'nullable|boolean',
            'extra_fee_enabled' => 'nullable|boolean',
            'enabled' => 'nullable|boolean',
        ]);

        $hasAmount = $request->filled('instant_order_fee_amount')
            || $request->filled('extra_fee_amount')
            || $request->filled('amount')
            || $request->filled('fee_amount');
        $hasEnabled = $request->exists('instant_order_fee_enabled')
            || $request->exists('extra_fee_enabled')
            || $request->exists('enabled');

        if (! $hasAmount && ! $hasEnabled) {
            return response()->json([
                'success' => false,
                'message' => 'Provide instant_order_fee_amount and/or instant_order_fee_enabled.',
            ], 422);
        }

        InstantOrderFee::saveFromRequest($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Instant order fee updated.',
            'data' => InstantOrderFee::adminApiPayload(),
        ]);
    }
}
