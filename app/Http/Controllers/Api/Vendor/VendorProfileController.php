<?php

namespace App\Http\Controllers\Api\Vendor;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\Vendor\VendorRegistrationService;
use App\Support\VendorContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VendorProfileController extends Controller
{
    public function __construct(
        private readonly VendorRegistrationService $registration
    ) {}

    public function show(Request $request): JsonResponse
    {
        $vendor = VendorContext::vendorForUser($request->user());

        return ApiResponse::success('Vendor profile retrieved.', [
            'vendor' => $this->vendorPayload($vendor),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $vendor = VendorContext::vendorForUser($request->user());
        if ($vendor === null) {
            return ApiResponse::error('Vendor not found.', 404);
        }

        $data = $request->validate([
            'business_name' => 'sometimes|string|max:255',
            'owner_name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|max:255',
            'phone' => 'nullable|string|max:32',
            'address' => 'nullable|string|max:2000',
            'tax_vat_number' => 'nullable|string|max:64',
            'description' => 'nullable|string|max:5000',
            'logo' => 'nullable|image|max:5120',
            'logo_remove' => 'nullable|boolean',
        ]);

        $vendor = $this->registration->updateProfile(
            $vendor,
            $data,
            $request->file('logo'),
            $request->boolean('logo_remove')
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
        $vendor->loadMissing('profile', 'user', 'approvalLogs');

        return [
            'id' => $vendor->id,
            'status' => $vendor->status,
            'logo_url' => $vendor->logo_url,
            'profile' => $vendor->profile,
            'user' => [
                'id' => $vendor->user->id,
                'name' => $vendor->user->name,
                'email' => $vendor->user->email,
            ],
            'approval_logs' => $vendor->approvalLogs->take(20),
        ];
    }
}
