<?php

namespace App\Http\Controllers\Api\Admin;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\VendorType;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class VendorTypeController extends Controller
{
    public function index(Request $request)
    {
        $query = VendorType::query()->orderBy('name');

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $items = $query->get()->map(fn (VendorType $row) => $row->toApiArray())->values();

        return ApiResponse::success('Vendor types retrieved successfully.', $items);
    }

    public function store(Request $request)
    {
        $validated = $this->validatePayload($request);

        $row = VendorType::create([
            'name' => $validated['name'],
            'slug' => VendorType::makeUniqueSlug($validated['name'], $validated['slug'] ?? null),
            'is_active' => $request->boolean('is_active', true),
        ]);

        return ApiResponse::success('Vendor type created successfully.', $row->toApiArray(), 201);
    }

    public function show(int $id)
    {
        $row = VendorType::query()->findOrFail($id);

        return ApiResponse::success('Vendor type retrieved successfully.', $row->toApiArray());
    }

    public function update(Request $request, int $id)
    {
        $row = VendorType::query()->findOrFail($id);
        $validated = $this->validatePayload($request, $row->id);

        if (array_key_exists('name', $validated)) {
            $row->name = $validated['name'];
        }

        // Only change slug when the client sends the slug field (empty = auto from name).
        if ($request->exists('slug')) {
            $slugInput = trim((string) ($validated['slug'] ?? ''));
            $nameForSlug = $validated['name'] ?? $row->name;
            $row->slug = VendorType::makeUniqueSlug(
                $nameForSlug,
                $slugInput !== '' ? $slugInput : null,
                $row->id
            );
        }

        if ($request->exists('is_active')) {
            $row->is_active = $request->boolean('is_active');
        }

        $row->save();

        return ApiResponse::success('Vendor type updated successfully.', $row->fresh()->toApiArray());
    }

    public function toggleStatus(int $id)
    {
        $row = VendorType::query()->findOrFail($id);
        $row->is_active = ! $row->is_active;
        $row->save();

        return ApiResponse::success(
            $row->is_active ? 'Vendor type activated.' : 'Vendor type deactivated.',
            $row->toApiArray()
        );
    }

    public function destroy(int $id)
    {
        $row = VendorType::query()->findOrFail($id);
        $row->delete();

        return ApiResponse::success('Vendor type deleted successfully.', [
            'id' => $id,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePayload(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'name' => [$ignoreId ? 'sometimes' : 'required', 'string', 'max:100'],
            'slug' => [
                'nullable',
                'string',
                'max:120',
                Rule::unique('vendor_types', 'slug')->ignore($ignoreId),
            ],
            'is_active' => ['sometimes', 'nullable'],
        ], [
            'name.required' => 'Name is required.',
            'slug.unique' => 'This slug is already used by another vendor type.',
        ]);
    }
}
