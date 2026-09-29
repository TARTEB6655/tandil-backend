<?php

namespace App\Http\Controllers\Api\Admin;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\ContractorBank;
use App\Models\ContractorCity;
use App\Models\ContractorRegistrationField;
use App\Models\Vendor;
use App\Services\Vendor\ContractorRegistrationSchemaService;
use App\Services\Vendor\VendorApprovalService;
use App\Services\Vendor\VendorVendorNotifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ContractorRegistrationAdminController extends Controller
{
    public function __construct(
        private readonly ContractorRegistrationSchemaService $schema,
        private readonly VendorApprovalService $approval,
        private readonly VendorVendorNotifier $vendorNotifier
    ) {}

    public function fields(): JsonResponse
    {
        $this->schema->ensureDefaultsSeeded();

        $fields = ContractorRegistrationField::query()->ordered()->get()
            ->map(fn (ContractorRegistrationField $f) => $f->toAdminArray())
            ->values();

        return ApiResponse::success('Registration fields retrieved.', [
            'fields' => $fields,
            'preview' => $this->schema->publicSchema(),
        ]);
    }

    public function updateField(Request $request, int $id): JsonResponse
    {
        $field = ContractorRegistrationField::query()->findOrFail($id);
        $data = $request->validate([
            'label' => ['sometimes', 'string', 'max:255'],
            'label_ar' => ['sometimes', 'nullable', 'string', 'max:255'],
            'is_required' => ['sometimes', 'boolean'],
            'is_enabled' => ['sometimes', 'boolean'],
            'is_multiple' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:9999'],
            'placeholder' => ['sometimes', 'nullable', 'string', 'max:255'],
            'placeholder_ar' => ['sometimes', 'nullable', 'string', 'max:255'],
            'meta' => ['sometimes', 'nullable', 'array'],
        ]);

        $field->update($data);

        return ApiResponse::success('Field updated.', ['field' => $field->fresh()->toAdminArray()]);
    }

    public function reorderFields(Request $request): JsonResponse
    {
        $data = $request->validate([
            'fields' => ['required', 'array', 'min:1'],
            'fields.*.id' => ['required', 'integer', 'exists:contractor_registration_fields,id'],
            'fields.*.sort_order' => ['required', 'integer', 'min:0'],
        ]);

        DB::transaction(function () use ($data) {
            foreach ($data['fields'] as $row) {
                ContractorRegistrationField::query()->whereKey($row['id'])->update([
                    'sort_order' => (int) $row['sort_order'],
                ]);
            }
        });

        return ApiResponse::success('Field order updated.', [
            'fields' => ContractorRegistrationField::query()->ordered()->get()->map->toAdminArray()->values(),
        ]);
    }

    public function banks(): JsonResponse
    {
        $this->schema->ensureDefaultsSeeded();

        return ApiResponse::success('Banks retrieved.', [
            'banks' => ContractorBank::query()->ordered()->get()->map->toApiArray()->values(),
        ]);
    }

    public function storeBank(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'name_ar' => ['nullable', 'string', 'max:191'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ]);

        $bank = ContractorBank::query()->create([
            'name' => $data['name'],
            'name_ar' => $data['name_ar'] ?? null,
            'slug' => ContractorBank::makeUniqueSlug($data['name']),
            'is_active' => $data['is_active'] ?? true,
            'sort_order' => $data['sort_order'] ?? 0,
        ]);

        return ApiResponse::success('Bank created.', ['bank' => $bank->toApiArray()], 201);
    }

    public function updateBank(Request $request, int $id): JsonResponse
    {
        $bank = ContractorBank::query()->findOrFail($id);
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:191'],
            'name_ar' => ['sometimes', 'nullable', 'string', 'max:191'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ]);

        if (isset($data['name']) && $data['name'] !== $bank->name) {
            $data['slug'] = ContractorBank::makeUniqueSlug($data['name'], $bank->id);
        }

        $bank->update($data);

        return ApiResponse::success('Bank updated.', ['bank' => $bank->fresh()->toApiArray()]);
    }

    public function destroyBank(int $id): JsonResponse
    {
        $bank = ContractorBank::query()->findOrFail($id);
        $bank->update(['is_active' => false]);

        return ApiResponse::success('Bank deactivated.', ['bank' => $bank->fresh()->toApiArray()]);
    }

    public function cities(Request $request): JsonResponse
    {
        $query = ContractorCity::query()->with('emirate:id,name')->ordered();
        if ($request->filled('emirate_id')) {
            $query->where('emirate_id', (int) $request->query('emirate_id'));
        }

        return ApiResponse::success('Cities retrieved.', [
            'cities' => $query->get()->map(fn (ContractorCity $c) => array_merge($c->toApiArray(), [
                'emirate_name' => $c->emirate?->name,
            ]))->values(),
        ]);
    }

    public function storeCity(Request $request): JsonResponse
    {
        $data = $request->validate([
            'emirate_id' => ['required', 'integer', 'exists:emirates,id'],
            'name' => ['required', 'string', 'max:191'],
            'name_ar' => ['nullable', 'string', 'max:191'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ]);

        $city = ContractorCity::query()->create([
            'emirate_id' => $data['emirate_id'],
            'name' => $data['name'],
            'name_ar' => $data['name_ar'] ?? null,
            'is_active' => $data['is_active'] ?? true,
            'sort_order' => $data['sort_order'] ?? 0,
        ]);

        return ApiResponse::success('City created.', ['city' => $city->toApiArray()], 201);
    }

    public function updateCity(Request $request, int $id): JsonResponse
    {
        $city = ContractorCity::query()->findOrFail($id);
        $data = $request->validate([
            'emirate_id' => ['sometimes', 'integer', 'exists:emirates,id'],
            'name' => ['sometimes', 'string', 'max:191'],
            'name_ar' => ['sometimes', 'nullable', 'string', 'max:191'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ]);

        $city->update($data);

        return ApiResponse::success('City updated.', ['city' => $city->fresh()->toApiArray()]);
    }

    /**
     * Ask contractor for missing documents / info (keeps account pending, notifies EN+AR).
     */
    public function requestDocuments(Request $request, int $id): JsonResponse
    {
        $vendor = Vendor::query()->with(['profile', 'user'])->findOrFail($id);
        $data = $request->validate([
            'message' => ['required', 'string', 'max:2000'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $vendor = $this->approval->underReview(
            $vendor,
            $request->user(),
            $data['notes'] ?? 'Additional documents requested.'
        );

        if ($vendor->profile) {
            $vendor->profile->update([
                'admin_review_message' => $data['message'],
                'documents_requested_at' => now(),
            ]);
        }

        $this->vendorNotifier->missingDocuments($vendor->fresh(['profile', 'user']), $data['message']);

        return ApiResponse::success('Missing documents requested from contractor.', [
            'vendor' => $vendor->fresh(['profile', 'user', 'documents']),
        ]);
    }
}
