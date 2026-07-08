<?php

namespace App\Http\Controllers\Api\Vendor;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Vendor\UpdateVendorProfileApiRequest;
use App\Models\VendorProfile;
use App\Services\Vendor\VendorProfileScreenService;
use App\Services\Vendor\VendorRegistrationService;
use App\Support\VendorContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VendorProfileController extends Controller
{
    public function __construct(
        private readonly VendorRegistrationService $registration,
        private readonly VendorProfileScreenService $profileScreen
    ) {}

    public function show(Request $request): JsonResponse
    {
        $vendor = VendorContext::vendorForUser($request->user());

        return ApiResponse::success('Vendor profile retrieved.', [
            'profile' => $this->profileScreen->build($vendor),
            'options' => [
                'emirates' => VendorProfile::emirates(),
            ],
        ]);
    }

    public function update(UpdateVendorProfileApiRequest $request): JsonResponse
    {
        $vendor = VendorContext::vendorForUser($request->user());
        if ($vendor === null) {
            return ApiResponse::error('Vendor not found.', 404);
        }

        $vendor = $this->registration->updateEditableProfile(
            $vendor,
            $request->validated(),
            $request->file('logo')
        );

        return ApiResponse::success('Profile updated.', [
            'profile' => $this->profileScreen->build($vendor),
            'options' => [
                'emirates' => VendorProfile::emirates(),
            ],
        ]);
    }
}
