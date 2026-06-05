<?php

namespace App\Http\Controllers\Api\Vendor;

use App\Enums\VendorType;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Vendor\UpdateVendorProfileRequest;
use App\Models\VendorProfile;
use App\Services\Vendor\VendorApplicationService;
use App\Services\Vendor\VendorRegistrationService;
use App\Support\VendorContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VendorProfileController extends Controller
{
    public function __construct(
        private readonly VendorRegistrationService $registration,
        private readonly VendorApplicationService $application
    ) {}

    public function show(Request $request): JsonResponse
    {
        $vendor = VendorContext::vendorForUser($request->user());

        return ApiResponse::success('Vendor profile retrieved.', [
            'vendor' => $this->vendorPayload($vendor),
            'options' => [
                'vendor_types' => VendorType::options(),
                'emirates' => VendorProfile::emirates(),
            ],
        ]);
    }

    public function update(UpdateVendorProfileRequest $request): JsonResponse
    {
        $vendor = VendorContext::vendorForUser($request->user());
        if ($vendor === null) {
            return ApiResponse::error('Vendor not found.', 404);
        }

        $vendor = $this->registration->updateProfile(
            $vendor,
            $request->validated(),
            $request->file('logo'),
            $request->boolean('logo_remove'),
            VendorRegistrationService::documentFilesFromRequest($request)
        );

        return ApiResponse::success('Profile updated.', ['vendor' => $this->vendorPayload($vendor)]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function vendorPayload(?\App\Models\Vendor $vendor): ?array
    {
        if ($vendor === null) {
            return null;
        }
        $vendor->loadMissing('profile', 'user', 'approvalLogs', 'documents', 'categories');

        return [
            'id' => $vendor->id,
            'status' => $vendor->status,
            'status_label' => $vendor->statusEnum()->label(),
            'rejection_reason' => $vendor->rejection_reason,
            'logo_url' => $vendor->logo_url,
            'profile' => $vendor->profile,
            'application' => $this->application->applicationPayload($vendor),
            'user' => [
                'id' => $vendor->user->id,
                'name' => $vendor->user->name,
                'email' => $vendor->user->email,
            ],
            'approval_logs' => $vendor->approvalLogs->take(20),
        ];
    }
}
