<?php

namespace App\Http\Controllers\Api\Vendor;

use App\Enums\VendorType;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Vendor\UpdateVendorProfileRequest;
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
        $sections = $this->profileScreen->normalizeSections($this->requestedSections($request));

        $payload = [
            'profile' => $this->profileScreen->build($vendor, $sections),
        ];

        if ($request->boolean('options')) {
            $payload['options'] = [
                'vendor_types' => VendorType::options(),
                'emirates' => VendorProfile::emirates(),
            ];
        }

        return ApiResponse::success('Vendor profile retrieved.', $payload);
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

        return ApiResponse::success('Profile updated.', [
            'profile' => $this->profileScreen->buildAfterUpdate($vendor->fresh(['profile', 'user'])),
        ]);
    }

    /**
     * @return list<string>
     */
    private function requestedSections(Request $request): array
    {
        $sections = [];

        if ($request->filled('section')) {
            $sections[] = (string) $request->query('section');
        }

        if ($request->filled('sections')) {
            $raw = $request->query('sections');
            if (is_array($raw)) {
                $sections = array_merge($sections, $raw);
            } else {
                foreach (explode(',', (string) $raw) as $part) {
                    $sections[] = trim($part);
                }
            }
        }

        return $sections;
    }
}
