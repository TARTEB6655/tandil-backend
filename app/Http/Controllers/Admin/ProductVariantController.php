<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductOption;
use App\Models\ProductOptionGroup;
use App\Models\ProductVariant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Admin API for variable product option groups, options, and variants.
 *
 * Routes (registered in routes/api.php under admin middleware):
 *   GET    /admin/products/{product}/option-groups
 *   POST   /admin/products/{product}/option-groups
 *   PUT    /admin/products/{product}/option-groups/{group}
 *   DELETE /admin/products/{product}/option-groups/{group}
 *
 *   POST   /admin/products/{product}/option-groups/{group}/options
 *   PUT    /admin/products/{product}/option-groups/{group}/options/{option}
 *   DELETE /admin/products/{product}/option-groups/{group}/options/{option}
 *
 *   GET    /admin/products/{product}/variants
 *   POST   /admin/products/{product}/variants
 *   PUT    /admin/products/{product}/variants/{variant}
 *   DELETE /admin/products/{product}/variants/{variant}
 *
 *   POST   /admin/products/{product}/variants/sync  — auto-generate from option matrix
 */
class ProductVariantController extends Controller
{
    public function __construct()
    {
        $this->middleware('role:admin');
    }

    // ── Option Groups ───────────────────────────────────────────────────────

    public function listGroups(Product $product): JsonResponse
    {
        $groups = $product->optionGroups()->with('options')->get();
        return response()->json(['success' => true, 'data' => $groups]);
    }

    public function storeGroup(Request $request, Product $product): JsonResponse
    {
        $data = $request->validate([
            'name'       => 'required|string|max:200',
            'input_type' => ['sometimes', Rule::in(['single', 'multi'])],
            'is_required' => 'sometimes|boolean',
            'sort_order'  => 'sometimes|integer|min:0',
            'options'     => 'sometimes|array',
            'options.*.label'          => 'required_with:options|string|max:200',
            'options.*.price_modifier' => 'sometimes|numeric',
            'options.*.sort_order'     => 'sometimes|integer|min:0',
        ]);

        DB::transaction(function () use ($data, $product, &$group) {
            $group = $product->optionGroups()->create([
                'name'        => $data['name'],
                'input_type'  => $data['input_type'] ?? 'single',
                'is_required' => $data['is_required'] ?? true,
                'sort_order'  => $data['sort_order'] ?? 0,
            ]);

            foreach ($data['options'] ?? [] as $i => $opt) {
                $group->options()->create([
                    'label'          => $opt['label'],
                    'price_modifier' => $opt['price_modifier'] ?? 0,
                    'sort_order'     => $opt['sort_order'] ?? $i,
                ]);
            }
        });

        return response()->json(['success' => true, 'data' => $group->load('options')], 201);
    }

    public function updateGroup(Request $request, Product $product, ProductOptionGroup $group): JsonResponse
    {
        abort_unless($group->product_id === $product->id, 404);

        $data = $request->validate([
            'name'        => 'sometimes|string|max:200',
            'input_type'  => ['sometimes', Rule::in(['single', 'multi'])],
            'is_required' => 'sometimes|boolean',
            'sort_order'  => 'sometimes|integer|min:0',
        ]);

        $group->update($data);
        return response()->json(['success' => true, 'data' => $group->load('options')]);
    }

    public function destroyGroup(Product $product, ProductOptionGroup $group): JsonResponse
    {
        abort_unless($group->product_id === $product->id, 404);
        $group->delete();
        return response()->json(['success' => true]);
    }

    // ── Options ─────────────────────────────────────────────────────────────

    public function storeOption(Request $request, Product $product, ProductOptionGroup $group): JsonResponse
    {
        abort_unless($group->product_id === $product->id, 404);

        $data = $request->validate([
            'label'          => 'required|string|max:200',
            'price_modifier' => 'sometimes|numeric',
            'sort_order'     => 'sometimes|integer|min:0',
        ]);

        $option = $group->options()->create($data);
        return response()->json(['success' => true, 'data' => $option], 201);
    }

    public function updateOption(Request $request, Product $product, ProductOptionGroup $group, ProductOption $option): JsonResponse
    {
        abort_unless($group->product_id === $product->id, 404);
        abort_unless($option->product_option_group_id === $group->id, 404);

        $data = $request->validate([
            'label'          => 'sometimes|string|max:200',
            'price_modifier' => 'sometimes|numeric',
            'sort_order'     => 'sometimes|integer|min:0',
        ]);

        $option->update($data);
        return response()->json(['success' => true, 'data' => $option]);
    }

    public function destroyOption(Product $product, ProductOptionGroup $group, ProductOption $option): JsonResponse
    {
        abort_unless($group->product_id === $product->id, 404);
        abort_unless($option->product_option_group_id === $group->id, 404);

        $option->delete();
        return response()->json(['success' => true]);
    }

    // ── Variants ────────────────────────────────────────────────────────────

    public function listVariants(Product $product): JsonResponse
    {
        $variants = $product->variants()->with('options.group')->get();
        return response()->json(['success' => true, 'data' => $variants]);
    }

    public function storeVariant(Request $request, Product $product): JsonResponse
    {
        $data = $request->validate([
            'sku'        => 'nullable|string|max:100',
            'price'      => 'nullable|numeric|min:0',
            'stock'      => 'sometimes|integer|min:0',
            'is_default' => 'sometimes|boolean',
            'label'      => 'nullable|string|max:500',
            'option_ids' => 'sometimes|array',
            'option_ids.*' => 'integer|exists:product_options,id',
        ]);

        $variant = DB::transaction(function () use ($data, $product) {
            $v = $product->variants()->create([
                'sku'        => $data['sku'] ?? null,
                'price'      => $data['price'] ?? null,
                'stock'      => $data['stock'] ?? 0,
                'is_default' => $data['is_default'] ?? false,
                'label'      => $data['label'] ?? null,
            ]);
            if (! empty($data['option_ids'])) {
                $v->options()->sync($data['option_ids']);
            }
            return $v->load('options');
        });

        return response()->json(['success' => true, 'data' => $variant], 201);
    }

    public function updateVariant(Request $request, Product $product, ProductVariant $variant): JsonResponse
    {
        abort_unless($variant->product_id === $product->id, 404);

        $data = $request->validate([
            'sku'        => 'nullable|string|max:100',
            'price'      => 'nullable|numeric|min:0',
            'stock'      => 'sometimes|integer|min:0',
            'is_default' => 'sometimes|boolean',
            'label'      => 'nullable|string|max:500',
            'option_ids' => 'sometimes|array',
            'option_ids.*' => 'integer|exists:product_options,id',
        ]);

        DB::transaction(function () use ($data, $variant) {
            $variant->update(collect($data)->except('option_ids')->toArray());
            if (array_key_exists('option_ids', $data)) {
                $variant->options()->sync($data['option_ids']);
            }
        });

        return response()->json(['success' => true, 'data' => $variant->load('options')]);
    }

    public function destroyVariant(Product $product, ProductVariant $variant): JsonResponse
    {
        abort_unless($variant->product_id === $product->id, 404);
        $variant->delete();
        return response()->json(['success' => true]);
    }

    /**
     * Sync: Save full option groups + options payload from admin UI in one request.
     * Replaces all existing groups/options for this product.
     */
    public function syncOptionGroups(Request $request, Product $product): JsonResponse
    {
        $data = $request->validate([
            'groups'                             => 'required|array',
            'groups.*.name'                      => 'required|string|max:200',
            'groups.*.input_type'                => ['required', Rule::in(['single', 'multi'])],
            'groups.*.is_required'               => 'required|boolean',
            'groups.*.sort_order'                => 'sometimes|integer|min:0',
            'groups.*.options'                   => 'required|array|min:1',
            'groups.*.options.*.label'           => 'required|string|max:200',
            'groups.*.options.*.price_modifier'  => 'sometimes|numeric',
            'groups.*.options.*.sort_order'      => 'sometimes|integer|min:0',
        ]);

        DB::transaction(function () use ($data, $product) {
            $product->optionGroups()->delete();
            foreach ($data['groups'] as $gi => $groupData) {
                $group = $product->optionGroups()->create([
                    'name'        => $groupData['name'],
                    'input_type'  => $groupData['input_type'],
                    'is_required' => $groupData['is_required'],
                    'sort_order'  => $groupData['sort_order'] ?? $gi,
                ]);
                foreach ($groupData['options'] as $oi => $optData) {
                    $group->options()->create([
                        'label'          => $optData['label'],
                        'price_modifier' => $optData['price_modifier'] ?? 0,
                        'sort_order'     => $optData['sort_order'] ?? $oi,
                    ]);
                }
            }
        });

        return response()->json([
            'success' => true,
            'data'    => $product->optionGroups()->with('options')->get(),
        ]);
    }
}
