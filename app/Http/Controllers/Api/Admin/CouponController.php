<?php

namespace App\Http\Controllers\Api\Admin;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Traits\ParsesPutMultipartFormFields;
use App\Models\Coupon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CouponController extends Controller
{
    use ParsesPutMultipartFormFields;
    public function index(Request $request): JsonResponse
    {
        $perPage = min(max((int) $request->query('per_page', 50), 1), 100);
        $search = trim((string) $request->query('search', ''));

        $query = Coupon::query()->with(['categories', 'services'])->orderByDesc('id');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', '%'.strtoupper($search).'%')
                    ->orWhere('title', 'like', '%'.$search.'%');
            });
        }

        $paginator = $query->paginate($perPage);
        $rows = $paginator->getCollection()->map(fn (Coupon $c) => $this->toArray($c))->values()->all();

        return response()->json([
            'success' => true,
            'message' => 'Coupons loaded.',
            'data' => $rows,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->mergePutMultipartFormFields($request);

        $data = $this->validatedData($request);
        $categoryIds = $data['category_ids'];
        $serviceIds = $data['service_ids'];
        unset($data['category_ids'], $data['service_ids']);

        $coupon = Coupon::create($data);
        $coupon->categories()->sync($categoryIds);
        $coupon->services()->sync($serviceIds);

        return ApiResponse::success('Coupon created.', $this->toArray($coupon->fresh()->load(['categories', 'services'])), 201);
    }

    public function show(int $id): JsonResponse
    {
        $coupon = Coupon::with(['categories', 'services'])->findOrFail($id);

        return ApiResponse::success('Coupon retrieved.', $this->toArray($coupon));
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $this->mergePutMultipartFormFields($request);

        $coupon = Coupon::with(['categories', 'services'])->findOrFail($id);

        if ($request->filled('code') && strtoupper(trim((string) $request->input('code'))) !== $coupon->code) {
            throw ValidationException::withMessages([
                'code' => ['Coupon code cannot be changed.'],
            ]);
        }

        $data = $this->validatedData($request, $coupon->id, true);
        $categoryIds = $data['category_ids'] ?? null;
        $serviceIds = $data['service_ids'] ?? null;
        unset($data['category_ids'], $data['service_ids']);

        $coupon->update($data);
        if ($categoryIds !== null) {
            $coupon->categories()->sync($categoryIds);
        }
        if ($serviceIds !== null) {
            $coupon->services()->sync($serviceIds);
        }

        return ApiResponse::success('Coupon updated.', $this->toArray($coupon->fresh()->load(['categories', 'services'])));
    }

    public function destroy(int $id): JsonResponse
    {
        $coupon = Coupon::findOrFail($id);
        $coupon->categories()->detach();
        $coupon->services()->detach();
        $coupon->delete();

        return ApiResponse::success('Coupon deleted.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedData(Request $request, ?int $ignoreId = null, bool $isUpdate = false): array
    {
        $this->normalizeRequestScalars($request);

        $this->normalizeIsActiveInput($request);

        $rules = [
            'title' => [$isUpdate ? 'sometimes' : 'required', 'string', 'max:255'],
            'description' => 'nullable|string|max:5000',
            'discount_type' => [$isUpdate ? 'sometimes' : 'required', Rule::in(['percentage', 'fixed_amount'])],
            'discount_value' => 'nullable|numeric|min:0',
            'min_order_amount' => [$isUpdate ? 'sometimes' : 'required', 'numeric', 'min:0'],
            'max_discount_amount' => 'nullable|numeric|min:0',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after_or_equal:starts_at',
            'is_active' => [$isUpdate ? 'sometimes' : 'required', 'boolean'],
            'usage_limit' => 'nullable|integer|min:1',
            'usage_limit_per_user' => 'nullable|integer|min:1',
            'applies_to' => [$isUpdate ? 'sometimes' : 'required', Rule::in([
                Coupon::APPLIES_ALL,
                Coupon::APPLIES_CATEGORIES,
                Coupon::APPLIES_SERVICES,
            ])],
            'category_ids' => 'nullable|array',
            'category_ids.*' => 'integer|exists:categories,id',
            'service_ids' => 'nullable|array',
            'service_ids.*' => 'integer|exists:services,id',
        ];

        if (! $isUpdate) {
            $rules['code'] = [
                'required',
                'string',
                'max:64',
                'regex:/^[A-Za-z0-9_-]+$/',
                Rule::unique('coupons', 'code')->ignore($ignoreId),
            ];
        }

        $validated = $request->validate($rules);

        $type = $validated['discount_type'] ?? null;
        if ($type !== null && in_array($type, ['percentage', 'fixed_amount'], true)
            && (! array_key_exists('discount_value', $validated) || $validated['discount_value'] === null || $validated['discount_value'] === '')) {
            throw ValidationException::withMessages([
                'discount_value' => ['discount_value is required for percentage and fixed_amount coupons.'],
            ]);
        }

        $scopeFieldsSent = $this->scopeFieldsWereSent($request, $validated, $isUpdate);

        $appliesTo = strtolower((string) ($validated['applies_to'] ?? Coupon::APPLIES_ALL));
        $categoryIds = array_map('intval', $validated['category_ids'] ?? []);
        $serviceIds = array_map('intval', $validated['service_ids'] ?? []);

        if ($appliesTo === Coupon::APPLIES_CATEGORIES) {
            if ($categoryIds === []) {
                throw ValidationException::withMessages([
                    'category_ids' => ['Select at least one category when applies_to is categories.'],
                ]);
            }
            $serviceIds = [];
        } elseif ($appliesTo === Coupon::APPLIES_SERVICES) {
            if ($serviceIds === []) {
                throw ValidationException::withMessages([
                    'service_ids' => ['Select at least one service when applies_to is services.'],
                ]);
            }
            $categoryIds = [];
        } else {
            $categoryIds = [];
            $serviceIds = [];
            $appliesTo = Coupon::APPLIES_ALL;
        }

        $payload = [];

        if (! $isUpdate) {
            $payload['code'] = strtoupper(trim($validated['code']));
        }
        if (array_key_exists('title', $validated)) {
            $payload['title'] = $validated['title'];
        }
        if (array_key_exists('description', $validated)) {
            $payload['description'] = $validated['description'];
        }
        if ($type !== null) {
            $payload['discount_type'] = $type;
            if (array_key_exists('discount_value', $validated)) {
                $payload['discount_value'] = (float) $validated['discount_value'];
            }
        } elseif (array_key_exists('discount_value', $validated)) {
            $payload['discount_value'] = $validated['discount_value'] !== '' && $validated['discount_value'] !== null
                ? (float) $validated['discount_value']
                : null;
        }
        if (array_key_exists('min_order_amount', $validated)) {
            $payload['min_order_amount'] = (float) $validated['min_order_amount'];
        }
        if (array_key_exists('max_discount_amount', $validated)) {
            $payload['max_discount_amount'] = $validated['max_discount_amount'] !== '' && $validated['max_discount_amount'] !== null
                ? (float) $validated['max_discount_amount']
                : null;
        }
        if (array_key_exists('starts_at', $validated)) {
            $payload['starts_at'] = $validated['starts_at'] !== '' ? $validated['starts_at'] : null;
        }
        if (array_key_exists('ends_at', $validated)) {
            $payload['ends_at'] = $validated['ends_at'] !== '' ? $validated['ends_at'] : null;
        }
        if (array_key_exists('is_active', $validated)) {
            $payload['is_active'] = (bool) $validated['is_active'];
        } elseif (! $isUpdate) {
            $payload['is_active'] = true;
        }
        if (array_key_exists('usage_limit', $validated)) {
            $payload['usage_limit'] = $validated['usage_limit'] !== '' && $validated['usage_limit'] !== null
                ? (int) $validated['usage_limit']
                : null;
        }
        if (array_key_exists('usage_limit_per_user', $validated)) {
            $payload['usage_limit_per_user'] = $validated['usage_limit_per_user'] !== '' && $validated['usage_limit_per_user'] !== null
                ? (int) $validated['usage_limit_per_user']
                : null;
        }
        if (array_key_exists('applies_to', $validated) || ! $isUpdate) {
            $payload['applies_to'] = $appliesTo;
            $payload['catalog_scope'] = Coupon::catalogScopeForAppliesTo($appliesTo);
        }

        if ($scopeFieldsSent) {
            $payload['category_ids'] = $categoryIds;
            $payload['service_ids'] = $serviceIds;
        }

        return $payload;
    }

    private function normalizeIsActiveInput(Request $request): void
    {
        $all = $request->all();

        if (array_key_exists('isActive', $all) && ! array_key_exists('is_active', $all)) {
            $request->merge(['is_active' => $all['isActive']]);
        }

        if (array_key_exists('is_active', $request->all())) {
            $request->merge(['is_active' => $this->parseBooleanInput($request->input('is_active'))]);
        }
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function scopeFieldsWereSent(Request $request, array $validated, bool $isUpdate): bool
    {
        if (! $isUpdate) {
            return true;
        }

        return array_key_exists('applies_to', $validated)
            || array_key_exists('category_ids', $request->all())
            || array_key_exists('service_ids', $request->all());
    }

    private function parseBooleanInput(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_int($value) || is_float($value)) {
            return (int) $value !== 0;
        }

        $normalized = strtolower(trim((string) $value));

        return in_array($normalized, ['1', 'true', 'on', 'yes'], true);
    }

    private function normalizeRequestScalars(Request $request): void
    {
        foreach (['category_ids', 'service_ids'] as $key) {
            if ($request->has($key) && is_string($request->input($key))) {
                $decoded = json_decode($request->input($key), true);
                if (is_array($decoded)) {
                    $request->merge([$key => $decoded]);
                }
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function toArray(Coupon $c): array
    {
        $categoryIds = $c->relationLoaded('categories')
            ? $c->categories->pluck('id')->map(fn ($id) => (int) $id)->values()->all()
            : $c->categories()->pluck('categories.id')->map(fn ($id) => (int) $id)->all();

        $serviceIds = $c->relationLoaded('services')
            ? $c->services->pluck('id')->map(fn ($id) => (int) $id)->values()->all()
            : $c->services()->pluck('services.id')->map(fn ($id) => (int) $id)->all();

        return [
            'id' => $c->id,
            'code' => $c->code,
            'title' => $c->title,
            'description' => $c->description,
            'discount_type' => $c->discount_type,
            'discount_value' => $c->discount_value !== null ? (float) $c->discount_value : null,
            'min_order_amount' => (float) ($c->min_order_amount ?? 0),
            'max_discount_amount' => $c->max_discount_amount !== null ? (float) $c->max_discount_amount : null,
            'starts_at' => $c->starts_at?->toDateString(),
            'ends_at' => $c->ends_at?->toDateString(),
            'is_active' => (bool) $c->is_active,
            'usage_limit' => $c->usage_limit,
            'usage_limit_per_user' => $c->usage_limit_per_user,
            'applies_to' => $c->applies_to ?? Coupon::APPLIES_ALL,
            'catalog_scope' => $c->catalog_scope ?? Coupon::catalogScopeForAppliesTo((string) ($c->applies_to ?? Coupon::APPLIES_ALL)),
            'category_ids' => $categoryIds,
            'service_ids' => $serviceIds,
            'paid_redemptions' => $c->paidOrdersCount(),
            'created_at' => $c->created_at?->toIso8601String(),
            'updated_at' => $c->updated_at?->toIso8601String(),
        ];
    }
}
