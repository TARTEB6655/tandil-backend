<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
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
     * Body: { "instant_order_fee_amount": 15 } — use 0 to disable.
     */
    public function update(Request $request): JsonResponse
    {
        $request->validate([
            'instant_order_fee_amount' => 'required|numeric|min:0',
        ]);

        Setting::set(
            InstantOrderFee::SETTING_KEY,
            (string) $request->input('instant_order_fee_amount'),
            'text',
            'shop'
        );

        return response()->json([
            'success' => true,
            'message' => 'Instant order fee updated.',
            'data' => InstantOrderFee::adminApiPayload(),
        ]);
    }
}
