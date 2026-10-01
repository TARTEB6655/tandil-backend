<?php

namespace App\Http\Controllers\Api\Vendor;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\Vendor\ContractorRegistrationSchemaService;

/**
 * Dynamic contractor registration form schema for the mobile app.
 *
 * Preferred: GET /api/contractor/auth/registration-options
 * Legacy alias: GET /api/vendor/auth/registration-options (same payload)
 */
class VendorRegistrationOptionsController extends Controller
{
    public function __construct(
        private readonly ContractorRegistrationSchemaService $schema
    ) {}

    public function __invoke()
    {
        $data = $this->schema->publicSchema();

        // Backward-compatible flat keys used by older apps.
        $data['vendor_types'] = $data['options']['vendor_types']
            ?? \App\Models\VendorType::query()->active()->orderBy('name')->get()->map->toApiArray()->values();
        $data['emirates'] = $data['options']['emirates'] ?? [];
        $data['banks'] = $data['options']['banks'] ?? [];
        $data['cities'] = $data['options']['cities'] ?? [];
        $data['categories'] = $data['options']['categories'] ?? [];
        $data['services'] = $data['options']['services'] ?? [];
        $data['areas'] = $data['options']['areas'] ?? [];

        return ApiResponse::success('Registration options retrieved successfully.', $data)
            ->header('Cache-Control', 'public, max-age=60, stale-while-revalidate=300');
    }
}
