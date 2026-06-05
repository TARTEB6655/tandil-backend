<?php

namespace App\Http\Controllers\Api\Vendor;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\Vendor\VendorApplicationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VendorApplicationController extends Controller
{
    public function __construct(
        private readonly VendorApplicationService $application
    ) {}

    public function show(Request $request): JsonResponse
    {
        $vendor = $request->attributes->get('vendor');

        return ApiResponse::success('Application status.', $this->application->applicationPayload($vendor));
    }

    public function resubmit(Request $request): JsonResponse
    {
        $vendor = $request->attributes->get('vendor');

        try {
            $vendor = $this->application->resubmit($vendor);
        } catch (\InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }

        return ApiResponse::success('Application resubmitted for review.', $this->application->applicationPayload($vendor));
    }

    public function submit(Request $request): JsonResponse
    {
        $vendor = $request->attributes->get('vendor');

        try {
            if ($this->application->missingRequiredDocuments($vendor)->isNotEmpty()
                || ! $this->application->isProfileComplete($vendor)
                || $vendor->categories()->count() === 0) {
                return ApiResponse::error('Complete profile, categories, and all required documents before submitting.', 422, [
                    'application' => $this->application->applicationPayload($vendor),
                ]);
            }
            $vendor = $this->application->markOnboardingSubmitted($vendor);
        } catch (\InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }

        return ApiResponse::success('Application submitted for review.', $this->application->applicationPayload($vendor));
    }
}
