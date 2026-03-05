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
    /**
     * GET /api/admin/technicians – All technicians for admin dashboard (e.g. "All Technicians" screen).
     * Returns: name, email, employee_id, service_areas, specializations; zone and supervisor the technician is linked with (empty if none).
     * Query: per_page (1–100), search (optional) – filters by name, email, employee_id, zone name, or supervisor name.
     */
    public function technicians(Request $request): JsonResponse
    {
        $perPage = min(max((int) $request->query('per_page', 50), 1), 100);
        $search = $request->query('search', '');
        $search = is_string($search) ? trim($search) : '';

        $query = User::role('technician')
            ->with(['employee', 'assignedAreas.supervisors'])
            ->orderBy('name');

        if ($search !== '') {
            $term = '%' . $search . '%';
            $query->where(function ($q) use ($term) {
                $q->where('users.name', 'like', $term)
                    ->orWhere('users.email', 'like', $term)
                    ->orWhereHas('employee', function ($eq) use ($term) {
                        $eq->where('employee_id', 'like', $term);
                    })
                    ->orWhereHas('assignedAreas', function ($eq) use ($term) {
                        $eq->where('name', 'like', $term);
                    })
                    ->orWhereHas('assignedAreas.supervisors', function ($eq) use ($term) {
                        $eq->where('name', 'like', $term);
                    });
            });
        }

        $technicians = $query->paginate($perPage);

        $data = $technicians->getCollection()->map(function (User $u) {
            $emp = $u->employee;
            $zones = $u->assignedAreas->map(fn ($a) => ['id' => $a->id, 'name' => $a->name])->values()->all();
            $supervisors = $u->assignedAreas->pluck('supervisors')->flatten(1)->unique('id')->values()->map(fn ($s) => ['id' => $s->id, 'name' => $s->name])->all();
            $firstZone = $zones[0] ?? null;
            $firstSupervisor = $supervisors[0] ?? null;
            return [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email ?? '',
                'employee_id' => $emp?->employee_id ?? ('TECH-' . $u->id),
                'service_areas' => $emp?->service_areas ?? [],
                'specializations' => $emp?->specializations ?? [],
                'zone' => $firstZone,
                'supervisor' => $firstSupervisor,
            ];
        })->all();

        return response()->json([
            'success' => true,
            'message' => $search !== '' ? "Technicians matching \"{$search}\"." : 'All technicians with zone and supervisor assignment. Zone and supervisor are empty when not linked.',
            'data' => $data,
            'pagination' => [
                'current_page' => $technicians->currentPage(),
                'last_page' => $technicians->lastPage(),
                'per_page' => $technicians->perPage(),
                'total' => $technicians->total(),
            ],
            'search' => $search !== '' ? $search : null,
        ]);
    }

    /**
     * GET /api/admin/technicians-for-zones – List technicians with region, specializations, and assigned zones.
     * So admin knows who is available where and what work they specialize in when assigning to a zone.
     */
    public function techniciansForZones(Request $request): JsonResponse
    {
        $perPage = min(max((int) $request->query('per_page', 50), 1), 100);
        $technicians = User::role('technician')
            ->with(['employee', 'assignedAreas'])
            ->orderBy('name')
            ->paginate($perPage);

        $data = $technicians->getCollection()->map(function (User $u) {
            $emp = $u->employee;
            $zones = $u->assignedAreas->map(fn ($a) => ['id' => $a->id, 'name' => $a->name, 'country' => $a->country ?? 'UAE'])->values()->all();
            return [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'phone' => $u->phone,
                'employee_id' => $emp?->employee_id ?? ('TECH-' . $u->id),
                'region' => $emp?->region,
                'specializations' => $emp?->specializations ?? [],
                'designation' => $emp?->designation,
                'assigned_zone_ids' => $u->assignedAreas->pluck('id')->values()->all(),
                'assigned_zones' => $zones,
            ];
        })->all();

        return response()->json([
            'success' => true,
            'message' => 'Technicians with region, specializations and assigned zones. Use when assigning technicians to a zone.',
            'data' => $data,
            'pagination' => [
                'current_page' => $technicians->currentPage(),
                'last_page' => $technicians->lastPage(),
                'per_page' => $technicians->perPage(),
                'total' => $technicians->total(),
            ],
        ]);
    }

    /**
     * GET /api/admin/supervisors-for-zones – List supervisors with assigned zones.
     * So admin knows which supervisor is in which zone when assigning.
     */
    public function supervisorsForZones(Request $request): JsonResponse
    {
        $perPage = min(max((int) $request->query('per_page', 50), 1), 100);
        $supervisors = User::role('supervisor')
            ->with(['employee', 'supervisedAreas'])
            ->orderBy('name')
            ->paginate($perPage);

        $data = $supervisors->getCollection()->map(function (User $u) {
            $zones = $u->supervisedAreas->map(fn ($a) => ['id' => $a->id, 'name' => $a->name, 'country' => $a->country ?? 'UAE'])->values()->all();
            return [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'phone' => $u->phone,
                'employee_id' => $u->employee?->employee_id ?? ('SUP-' . $u->id),
                'region' => $u->employee?->region,
                'assigned_zone_ids' => $u->supervisedAreas->pluck('id')->values()->all(),
                'assigned_zones' => $zones,
            ];
        })->all();

        return response()->json([
            'success' => true,
            'message' => 'Supervisors with assigned zones. Use when assigning supervisors to a zone.',
            'data' => $data,
            'pagination' => [
                'current_page' => $supervisors->currentPage(),
                'last_page' => $supervisors->lastPage(),
                'per_page' => $supervisors->perPage(),
                'total' => $supervisors->total(),
            ],
        ]);
    }

    private function areaToArray(Area $area, array $extra = []): array
    {
        $data = [
            'id' => $area->id,
            'name' => $area->name,
            'description' => $area->description ?? null,
            'location' => $area->location ?? null,
            'country' => $area->country ?? 'UAE',
            'created_at' => $area->created_at?->toIso8601String(),
            'updated_at' => $area->updated_at?->toIso8601String(),
        ];

        if ($area->relationLoaded('supervisors')) {
            $supervisors = $area->supervisors->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'employee_id' => $u->employee?->employee_id ?? ('SUP-' . $u->id),
                'email' => $u->email,
            ])->values()->all();
            $data['supervisors'] = $supervisors;
            $data['supervisor'] = $supervisors[0] ?? null;
        }
        if ($area->relationLoaded('technicians')) {
            $data['technicians'] = $area->technicians->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'employee_id' => $u->employee?->employee_id ?? ('TECH-' . $u->id),
                'email' => $u->email,
            ])->values()->all();
        }

        return array_merge($data, $extra);
    }

    /**
     * GET /api/admin/areas – List zones with supervisor name. ?with=supervisors,technicians&per_page=15
     * Default includes supervisors so each zone has supervisor (id, name, employee_id) or null.
     */
    public function index(Request $request): JsonResponse
    {
        $with = ['supervisors'];
        if ($request->filled('with')) {
            $parts = array_map('trim', explode(',', $request->input('with')));
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

    /** Normalize IDs from form-data (comma-separated string or array) to array of integers. */
    private function normalizeIds($value): array
    {
        if (is_array($value)) {
            return array_values(array_filter(array_map('intval', $value)));
        }
        if (is_string($value) && $value !== '') {
            return array_values(array_filter(array_map('intval', preg_split('/\s*,\s*/', $value))));
        }
        return [];
    }

    /**
     * POST /api/admin/areas – Add zone and assign supervisor(s). Form-data only: location (required), supervisor_ids (required; comma-separated or array, one or many).
     */
    public function store(Request $request): JsonResponse
    {
        $input = $request->all();
        $supervisorIds = $this->normalizeIds($input['supervisor_ids'] ?? []);

        $validator = Validator::make(array_merge($input, ['supervisor_ids' => $supervisorIds]), [
            'location' => 'required|string|max:255',
            'supervisor_ids' => 'required|array|min:1',
            'supervisor_ids.*' => 'integer|exists:users,id',
        ]);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $location = $request->input('location');
        $baseName = $location;
        $name = $baseName;
        $n = 0;
        while (Area::where('name', $name)->exists()) {
            $n++;
            $name = $baseName . ' (' . $n . ')';
        }
        $area = Area::create([
            'name' => $name,
            'description' => null,
            'location' => $location,
            'country' => 'UAE',
        ]);

        $this->ensureSupervisorsAndSync($area, $supervisorIds);

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
     * PUT/POST /api/admin/areas/{id} – Update zone. Form-data only: location (optional), supervisor_ids (optional; comma-separated or array, one or many).
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $input = $request->all();
        $supervisorIds = array_key_exists('supervisor_ids', $input) ? $this->normalizeIds($input['supervisor_ids']) : null;

        $rules = [
            'location' => 'nullable|string|max:255',
        ];
        if ($supervisorIds !== null) {
            $rules['supervisor_ids'] = 'nullable|array';
            $rules['supervisor_ids.*'] = 'integer|exists:users,id';
        }
        $validator = Validator::make(array_merge($input, array_filter(['supervisor_ids' => $supervisorIds])), $rules);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $area = Area::findOrFail($id);
        if ($request->filled('location')) {
            $area->location = $request->input('location');
            $area->name = $request->input('location');
        }
        $area->save();

        if ($supervisorIds !== null) {
            $this->ensureSupervisorsAndSync($area, $supervisorIds);
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
