<?php

namespace App\Http\Controllers\Api\Vendor;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Vendor\VendorRegistrationRequest;
use App\Services\Vendor\VendorRegistrationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VendorAuthController extends Controller
{
    public function __construct(
        private readonly VendorRegistrationService $registration
    ) {}

    public function register(VendorRegistrationRequest $request): JsonResponse
    {
        $vendor = $this->registration->register(
            $request->validated(),
            $request->file('logo'),
            VendorRegistrationService::documentFilesFromRequest($request)
        );

        return ApiResponse::success('Vendor registration submitted. Your application is pending admin review.', [
            'vendor_id' => $vendor->id,
            'status' => $vendor->status,
            'profile' => $vendor->profile,
            'documents' => $vendor->documents,
            'completion_percent' => app(\App\Services\Vendor\VendorApplicationService::class)->completionPercent($vendor),
        ], 201);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return ApiResponse::success('Logged out successfully.');
    }
}
