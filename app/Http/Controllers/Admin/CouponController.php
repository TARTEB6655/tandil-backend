<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\Service;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CouponController extends Controller
{
    public function __construct()
    {
        $this->middleware('role:admin');
    }

    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search', ''));

        $query = Coupon::query()->with(['categories', 'services'])->orderByDesc('id');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', '%'.strtoupper($search).'%')
                    ->orWhere('title', 'like', '%'.$search.'%');
            });
        }

        $coupons = $query->paginate(20)->withQueryString();

        return view('admin.coupons.index', compact('coupons', 'search'));
    }

    public function create(): View
    {
        return view('admin.coupons.create', $this->formOptions());
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $categoryIds = $data['category_ids'];
        $serviceIds = $data['service_ids'];
        unset($data['category_ids'], $data['service_ids']);

        $coupon = Coupon::create($data);
        $coupon->categories()->sync($categoryIds);
        $coupon->services()->sync($serviceIds);

        return redirect()->route('admin.coupons.index')->with('success', 'Coupon created successfully.');
    }

    public function edit(int $id): View
    {
        $coupon = Coupon::with(['categories', 'services'])->findOrFail($id);

        return view('admin.coupons.edit', array_merge($this->formOptions(), [
            'coupon' => $coupon,
            'selectedCategoryIds' => $coupon->categories->pluck('id')->map(fn ($i) => (int) $i)->all(),
            'selectedServiceIds' => $coupon->services->pluck('id')->map(fn ($i) => (int) $i)->all(),
        ]));
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $coupon = Coupon::findOrFail($id);
        $data = $this->validated($request, $coupon->id, true);
        $categoryIds = $data['category_ids'];
        $serviceIds = $data['service_ids'];
        unset($data['category_ids'], $data['service_ids']);

        $coupon->update($data);
        $coupon->categories()->sync($categoryIds);
        $coupon->services()->sync($serviceIds);

        return redirect()->route('admin.coupons.index')->with('success', 'Coupon updated successfully.');
    }

    public function destroy(int $id): RedirectResponse
    {
        $coupon = Coupon::findOrFail($id);
        $coupon->categories()->detach();
        $coupon->services()->detach();
        $coupon->delete();

        return redirect()->route('admin.coupons.index')->with('success', 'Coupon deleted.');
    }

    public function toggleStatus(int $id): RedirectResponse
    {
        $coupon = Coupon::findOrFail($id);
        $coupon->is_active = ! $coupon->is_active;
        $coupon->save();

        return redirect()->route('admin.coupons.index')
            ->with('success', $coupon->is_active ? 'Coupon activated.' : 'Coupon deactivated.');
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        return [
            'categories' => Category::query()->orderBy('name')->get(['id', 'name']),
            'services' => Service::query()->orderBy('name')->get(['id', 'name']),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?int $ignoreId = null, bool $isUpdate = false): array
    {
        if ($request->has('is_active')) {
            $request->merge(['is_active' => $request->boolean('is_active')]);
        }

        $rules = [
            'title' => ['required', 'string', 'max:255'],
            'description' => 'nullable|string|max:5000',
            'discount_type' => ['required', Rule::in(['percentage', 'fixed_amount'])],
            'discount_value' => 'nullable|numeric|min:0',
            'min_order_amount' => ['required', 'numeric', 'min:0'],
            'max_discount_amount' => 'nullable|numeric|min:0',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after_or_equal:starts_at',
            'is_active' => 'nullable|boolean',
            'usage_limit' => 'nullable|integer|min:1',
            'usage_limit_per_user' => 'nullable|integer|min:1',
            'applies_to' => ['required', Rule::in([
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
                Rule::unique('coupons', 'code'),
            ];
        }

        $validated = $request->validate($rules);

        $appliesTo = strtolower((string) ($validated['applies_to'] ?? Coupon::APPLIES_ALL));
        $categoryIds = array_map('intval', $validated['category_ids'] ?? []);
        $serviceIds = array_map('intval', $validated['service_ids'] ?? []);

        if ($appliesTo === Coupon::APPLIES_CATEGORIES && $categoryIds === []) {
            throw ValidationException::withMessages(['category_ids' => 'Select at least one category.']);
        }
        if ($appliesTo === Coupon::APPLIES_SERVICES && $serviceIds === []) {
            throw ValidationException::withMessages(['service_ids' => 'Select at least one service.']);
        }
        if ($appliesTo === Coupon::APPLIES_CATEGORIES) {
            $serviceIds = [];
        } elseif ($appliesTo === Coupon::APPLIES_SERVICES) {
            $categoryIds = [];
        } else {
            $categoryIds = [];
            $serviceIds = [];
            $appliesTo = Coupon::APPLIES_ALL;
        }

        $type = $validated['discount_type'] ?? 'percentage';
        if (in_array($type, ['percentage', 'fixed_amount'], true)
            && (! isset($validated['discount_value']) || $validated['discount_value'] === '' || $validated['discount_value'] === null)) {
            throw ValidationException::withMessages(['discount_value' => 'Discount value is required.']);
        }

        $payload = [
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'discount_type' => $type,
            'discount_value' => isset($validated['discount_value']) ? (float) $validated['discount_value'] : null,
            'min_order_amount' => (float) ($validated['min_order_amount'] ?? 0),
            'max_discount_amount' => isset($validated['max_discount_amount']) && $validated['max_discount_amount'] !== ''
                ? (float) $validated['max_discount_amount'] : null,
            'starts_at' => ! empty($validated['starts_at']) ? $validated['starts_at'] : null,
            'ends_at' => ! empty($validated['ends_at']) ? $validated['ends_at'] : null,
            'is_active' => array_key_exists('is_active', $validated) ? (bool) $validated['is_active'] : true,
            'usage_limit' => ! empty($validated['usage_limit']) ? (int) $validated['usage_limit'] : null,
            'usage_limit_per_user' => ! empty($validated['usage_limit_per_user']) ? (int) $validated['usage_limit_per_user'] : null,
            'applies_to' => $appliesTo,
            'catalog_scope' => Coupon::catalogScopeForAppliesTo($appliesTo),
            'category_ids' => $categoryIds,
            'service_ids' => $serviceIds,
        ];

        if (! $isUpdate) {
            $payload['code'] = strtoupper(trim($validated['code']));
        }

        return $payload;
    }
}
