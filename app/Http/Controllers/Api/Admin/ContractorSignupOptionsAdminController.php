<?php

namespace App\Http\Controllers\Api\Admin;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\Supervisor\ContractorSignupOptionsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContractorSignupOptionsAdminController extends Controller
{
    public function __construct(
        private readonly ContractorSignupOptionsService $options
    ) {}

    /**
     * Contractor signup options screen (master toggle + selected tab list).
     */
    public function index(Request $request): JsonResponse
    {
        $tab = $request->query('tab', 'categories');

        return ApiResponse::success(
            'Contractor signup options retrieved.',
            $this->options->screen(is_string($tab) ? $tab : 'categories')
        );
    }

    public function updateSettings(Request $request): JsonResponse
    {
        $data = $request->validate([
            'is_open' => ['required', 'boolean'],
        ]);

        $registration = $this->options->setRegistrationOpen((bool) $data['is_open']);

        return ApiResponse::success('Contractor registration setting updated.', [
            'registration' => $registration,
            'screen' => $this->options->screen($request->query('tab', 'categories')),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $tab = $this->options->normalizeTab($request->input('tab', $request->query('tab')));
        $data = $this->validateItem($request, $tab, false);
        $item = $this->options->create($tab, $data);

        return ApiResponse::success('Signup option created.', [
            'item' => $item,
            'screen' => $this->options->screen($tab),
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $tab = $this->options->normalizeTab($request->input('tab', $request->query('tab')));
        $data = $this->validateItem($request, $tab, true);
        $item = $this->options->update($tab, $id, $data);

        return ApiResponse::success('Signup option updated.', [
            'item' => $item,
            'screen' => $this->options->screen($tab),
        ]);
    }

    public function toggle(Request $request, int $id): JsonResponse
    {
        $tab = $this->options->normalizeTab($request->input('tab', $request->query('tab')));
        $data = $request->validate([
            'is_active' => ['sometimes', 'boolean'],
        ]);
        $item = $this->options->toggle($tab, $id, array_key_exists('is_active', $data) ? (bool) $data['is_active'] : null);

        return ApiResponse::success('Signup option toggled.', [
            'item' => $item,
            'screen' => $this->options->screen($tab),
        ]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $tab = $this->options->normalizeTab($request->input('tab', $request->query('tab')));
        $result = $this->options->delete($tab, $id);

        return ApiResponse::success('Signup option deleted.', [
            'item' => $result,
            'screen' => $this->options->screen($tab),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validateItem(Request $request, string $tab, bool $partial): array
    {
        $nameRule = $partial ? ['sometimes', 'string', 'max:191'] : ['required', 'string', 'max:191'];

        $rules = [
            'name' => $nameRule,
            'slug' => ['sometimes', 'nullable', 'string', 'max:191'],
            'is_active' => ['sometimes', 'boolean'],
            'name_ar' => ['sometimes', 'nullable', 'string', 'max:191'],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];

        if ($tab === 'subcategories') {
            $rules['parent_id'] = $partial
                ? ['sometimes', 'integer', 'exists:categories,id']
                : ['required', 'integer', 'exists:categories,id'];
        }
        if ($tab === 'services') {
            $rules['category_id'] = ['sometimes', 'nullable', 'integer', 'exists:categories,id'];
        }
        if ($tab === 'cities') {
            $rules['emirate_id'] = ['sometimes', 'nullable', 'integer', 'exists:emirates,id'];
        }

        return $request->validate($rules);
    }
}
