<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\Loyalty\LoyaltyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class LoyaltyApiController extends Controller
{
    public function __construct(
        private readonly LoyaltyService $loyalty
    ) {
        $this->middleware(['auth:sanctum', 'role:client']);
    }

    /**
     * GET /api/client/loyalty
     * GET /api/user/loyalty
     */
    public function index(Request $request): JsonResponse
    {
        return ApiResponse::success(
            'Loyalty points retrieved.',
            $this->loyalty->getScreenPayload($request->user())
        );
    }

    /**
     * GET /api/client/loyalty/campaigns
     */
    public function campaigns(Request $request): JsonResponse
    {
        return ApiResponse::success(
            'Loyalty campaigns retrieved.',
            $this->loyalty->campaignsPayload($request->user())
        );
    }

    /**
     * POST /api/client/loyalty/rewards/{id}/redeem
     */
    public function redeem(Request $request, int $id): JsonResponse
    {
        try {
            $payload = $this->loyalty->redeemReward($request->user(), $id);
        } catch (InvalidArgumentException $e) {
            $code = str_contains($e->getMessage(), 'Not enough') ? 422 : 404;

            return ApiResponse::error($e->getMessage(), $code);
        }

        return ApiResponse::success('Reward redeemed successfully.', $payload);
    }
}
