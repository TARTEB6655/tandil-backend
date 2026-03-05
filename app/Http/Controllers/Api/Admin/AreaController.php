<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Area;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Admin Zones (Areas) API: assign supervisors and technicians to zones at setup.
 * Client: "Admin assigns Supervisors to specific zones. Technicians are linked to a Supervisor during setup."
 * Pivot: area_supervisor, area_technician.
 */
class AreaController extends Controller
{
    private function areaToArray(Area $area, array $extra = []): array
    {
        $data = [
            'id' => $area->id,
            'name' => $area->name,
            'description' => $area->description ?? null,
            'country' => $area->country ?? 'UAE',
            'created_at' => $area->created_at?->toIso8601String(),
            'updated_at' => $area->updated_at?->toIso8601String(),
        ];

        if ($area->relationLoaded('supervisors')) {
            $data['supervisors'] = $area->supervisors->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
            ])->values()->all();
        }
        if ($area->relationLoaded('technicians')) {
            $data['technicians'] = $area->technicians->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
            ])->values()->all();
        }

        return array_merge($data, $extra);
    }

    /**
     * GET /api/admin/areas – List zones. ?with=supervisors,technicians&per_page=15
     */
    public function index(Request $request): JsonResponse
    {
        $with = [];
        if ($request->filled('with')) {
            $parts = array_map('trim', explode(',', $request->input('with')));
            if (in_array('supervisors', $parts)) {
                $with[] = 'supervisors';
            }
            if (in_array('technicians', $parts)) {
                $with[] = 'technicians';
            }
        }

        $perPage = min(max((int) $request->query('per_page', 15), 1), 100);
        $query = Area::query()->with($with)->orderBy('name');

        if ($request->filled('country')) {
            $query->where('country', $request->input('country'));
        }
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $areas = $query->paginate($perPage);
        $data = $areas->getCollection()->map(fn (Area $a) => $this->areaToArray($a))->all();

        return response()->json([
            'success' => true,
            'message' => 'Areas retrieved successfully.',
            'data' => $data,
            'pagination' => [
                'current_page' => $areas->currentPage(),
                'last_page' => $areas->lastPage(),
                'per_page' => $areas->perPage(),
                'total' => $areas->total(),
            ],
        ]);
    }

    /**
     * POST /api/admin/areas – Create zone. Body: name (required), description (optional), supervisor_ids[] (optional), technician_ids[] (optional).
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'country' => 'nullable|string|max:100',
            'supervisor_ids' => 'nullable|array',
            'supervisor_ids.*' => 'integer|exists:users,id',
            'technician_ids' => 'nullable|array',
            'technician_ids.*' => 'integer|exists:users,id',
        ]);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $area = Area::create([
            'name' => $request->input('name'),
            'description' => $request->input('description'),
            'country' => $request->input('country', 'UAE'),
        ]);

        $supervisorIds = $request->input('supervisor_ids', []);
        $technicianIds = $request->input('technician_ids', []);
        $this->ensureSupervisorsAndSync($area, $supervisorIds);
        $this->ensureTechniciansAndSync($area, $technicianIds);

        $area->load(['supervisors', 'technicians']);
        return response()->json([
            'success' => true,
            'message' => 'Area created successfully.',
            'data' => $this->areaToArray($area),
        ], 201);
    }

    /**
     * GET /api/admin/areas/{id} – Show one zone with supervisors and technicians.
     */
    public function show(int $id): JsonResponse
    {
        $area = Area::with(['supervisors', 'technicians'])->findOrFail($id);
        return response()->json([
            'success' => true,
            'message' => 'Area retrieved successfully.',
            'data' => $this->areaToArray($area),
        ]);
    }

    /**
     * PUT /api/admin/areas/{id} – Update zone and sync supervisors/technicians.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'country' => 'nullable|string|max:100',
            'supervisor_ids' => 'nullable|array',
            'supervisor_ids.*' => 'integer|exists:users,id',
            'technician_ids' => 'nullable|array',
            'technician_ids.*' => 'integer|exists:users,id',
        ]);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $area = Area::findOrFail($id);
        if ($request->has('name')) {
            $area->name = $request->input('name');
        }
        if (array_key_exists('description', $request->all())) {
            $area->description = $request->input('description');
        }
        if (array_key_exists('country', $request->all())) {
            $area->country = $request->input('country', 'UAE');
        }
        $area->save();

        if (array_key_exists('supervisor_ids', $request->all())) {
            $this->ensureSupervisorsAndSync($area, $request->input('supervisor_ids', []));
        }
        if (array_key_exists('technician_ids', $request->all())) {
            $this->ensureTechniciansAndSync($area, $request->input('technician_ids', []));
        }

        $area->load(['supervisors', 'technicians']);
        return response()->json([
            'success' => true,
            'message' => 'Area updated successfully.',
            'data' => $this->areaToArray($area),
        ]);
    }

    /**
     * DELETE /api/admin/areas/{id}
     */
    public function destroy(int $id): JsonResponse
    {
        $area = Area::findOrFail($id);
        $area->supervisors()->detach();
        $area->technicians()->detach();
        $area->delete();
        return response()->json([
            'success' => true,
            'message' => 'Area deleted successfully.',
        ]);
    }

    private function ensureSupervisorsAndSync(Area $area, array $ids): void
    {
        $valid = User::role('supervisor')->whereIn('id', $ids)->pluck('id')->toArray();
        $area->supervisors()->sync($valid);
    }

    private function ensureTechniciansAndSync(Area $area, array $ids): void
    {
        $valid = User::role('technician')->whereIn('id', $ids)->pluck('id')->toArray();
        $area->technicians()->sync($valid);
    }
}
