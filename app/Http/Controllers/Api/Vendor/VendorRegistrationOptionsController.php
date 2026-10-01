<?php

namespace App\Http\Controllers\Api\Vendor;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\Vendor\ContractorRegistrationSchemaService;
use Illuminate\Http\Request;

/**
 * Dynamic contractor registration form schema for the mobile app.
 *
 * Preferred: GET /api/contractor/auth/registration-options
 *   → only signup dropdowns (categories / services / emirates / coverage)
 * Legacy alias: GET /api/vendor/auth/registration-options (full schema)
 */
class VendorRegistrationOptionsController extends Controller
{
    public function __construct(
        private readonly ContractorRegistrationSchemaService $schema
    ) {}

    public function __invoke(Request $request)
    {
        if ($this->isContractorRequest($request)) {
            $options = $this->schema->contractorSignupOptions();

            return ApiResponse::success('Registration options retrieved successfully.', [
                'main_service_categories' => $options['main_service_categories'],
                'service_subcategories' => $options['service_subcategories'],
                'available_services' => $options['available_services'],
                'emirates' => $options['emirates'],
                'service_coverage_areas' => $options['service_coverage_areas'],
            ])->header('Cache-Control', 'public, max-age=60, stale-while-revalidate=300');
        }

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

    private function isContractorRequest(Request $request): bool
    {
        $path = trim($request->path(), '/');

        return str_starts_with($path, 'api/contractor/')
            || str_contains($path, '/contractor/');
    }
}
