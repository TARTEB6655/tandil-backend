<?php

namespace App\Http\Controllers\Api\Admin;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Coupon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CouponController extends Controller
{
    public function index()
    {
        $rows = Coupon::query()->orderByDesc('id')->get()->map(fn (Coupon $c) => $this->toArray($c));

        return ApiResponse::success('Coupons retrieved successfully.', $rows);
    }

    public function store(Request $request)
    {
        $data = $this->validatedData($request);

        $coupon = Coupon::create($data);

        return ApiResponse::success('Coupon created successfully.', $this->toArray($coupon), 201);
    }

    public function show(int $id)
    {
        $coupon = Coupon::findOrFail($id);

        return ApiResponse::success('Coupon retrieved successfully.', $this->toArray($coupon));
    }

    public function update(Request $request, int $id)
    {
        $coupon = Coupon::findOrFail($id);
        $data = $this->validatedData($request, $coupon->id, true);
        $coupon->update($data);

        return ApiResponse::success('Coupon updated successfully.', $this->toArray($coupon->fresh()));
    }

    public function destroy(int $id)
    {
        $coupon = Coupon::findOrFail($id);
        $coupon->delete();

        return ApiResponse::success('Coupon deleted successfully.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedData(Request $request, ?int $ignoreId = null, bool $isUpdate = false): array
    {
        if ($request->has('is_active')) {
            $request->merge([
                'is_active' => filter_var($request->input('is_active'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false,
            ]);
        }

        $rules = [
            'code' => [
                $isUpdate ? 'sometimes' : 'required',
                'string',
                'max:64',
                'regex:/^[A-Za-z0-9_-]+$/',
                Rule::unique('coupons', 'code')->ignore($ignoreId),
            ],
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:5000',
            'discount_type' => [$isUpdate ? 'sometimes' : 'required', Rule::in(['percentage', 'fixed_amount', 'free_shipping'])],
            'discount_value' => 'nullable|numeric|min:0',
            'min_order_amount' => 'nullable|numeric|min:0',
            'max_discount_amount' => 'nullable|numeric|min:0',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after_or_equal:starts_at',
            'is_active' => 'sometimes|boolean',
            'usage_limit' => 'nullable|integer|min:1',
            'usage_limit_per_user' => 'nullable|integer|min:1',
        ];

        $validated = $request->validate($rules);

        $type = $validated['discount_type'] ?? null;
        if ($type !== null && in_array($type, ['percentage', 'fixed_amount'], true)
            && (! array_key_exists('discount_value', $validated) || $validated['discount_value'] === null || $validated['discount_value'] === '')) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'discount_value' => ['discount_value is required for percentage and fixed_amount coupons.'],
            ]);
        }

        $payload = [];

        if (array_key_exists('code', $validated)) {
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
        }
        if (array_key_exists('discount_value', $validated)) {
            $payload['discount_value'] = $validated['discount_value'] !== '' && $validated['discount_value'] !== null
                ? $validated['discount_value']
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
            $payload['is_active'] = filter_var($validated['is_active'], FILTER_VALIDATE_BOOLEAN);
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

        if (! $isUpdate) {
            $payload['min_order_amount'] = (float) ($payload['min_order_amount'] ?? $validated['min_order_amount'] ?? 0);
            if (! array_key_exists('is_active', $payload)) {
                $payload['is_active'] = true;
            }
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    private function toArray(Coupon $c): array
    {
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
            'paid_redemptions' => $c->paidOrdersCount(),
            'created_at' => $c->created_at?->toIso8601String(),
            'updated_at' => $c->updated_at?->toIso8601String(),
        ];
    }
}
