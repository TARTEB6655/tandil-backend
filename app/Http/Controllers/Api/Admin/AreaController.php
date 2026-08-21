<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Area;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;

/**
 * Admin Zones (Areas) API: assign supervisors and technicians to zones at setup.
 * Client: "Admin assigns Supervisors to specific zones. Technicians are linked to a Supervisor during setup."
 * Pivot: area_supervisor, area_technician.
 */
class AreaController extends Controller
{
    /**
     * GET /api/admin/operational-areas
     * App-ready payload for Operational Areas screen (summary + paginated list).
     */
    public function operationalAreas(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('search', ''));
        $country = trim((string) $request->query('country', 'UAE'));
        $perPage = min(max((int) $request->query('per_page', 10), 1), 100);

        $hasName = Schema::hasColumn('areas', 'name');
        $hasLocation = Schema::hasColumn('areas', 'location');
        $hasCountry = Schema::hasColumn('areas', 'country');
        $hasDescription = Schema::hasColumn('areas', 'description');
        $hasIsActive = Schema::hasColumn('areas', 'is_active');
        $hasPriority = Schema::hasColumn('areas', 'priority');
        $hasLat = Schema::hasColumn('areas', 'latitude');
        $hasLng = Schema::hasColumn('areas', 'longitude');

        $base = Area::query()->with('supervisors');
        if ($country !== '' && $hasCountry) {
            $base->where('country', $country);
        }
        if ($search !== '') {
            $base->where(function ($q) use ($search, $hasName, $hasLocation, $hasCountry, $hasDescription) {
                if ($hasName) {
                    $q->orWhere('name', 'like', '%' . $search . '%');
                }
                if ($hasLocation) {
                    $q->orWhere('location', 'like', '%' . $search . '%');
                }
                if ($hasCountry) {
                    $q->orWhere('country', 'like', '%' . $search . '%');
                }
                if ($hasDescription) {
                    $q->orWhere('description', 'like', '%' . $search . '%');
                }
            });
        }

        $ordered = clone $base;
        if ($hasPriority) {
            $ordered->orderBy('priority');
        }
        if ($hasName) {
            $ordered->orderBy('name');
        } elseif ($hasLocation) {
            $ordered->orderBy('location');
        } else {
            $ordered->orderBy('id');
        }

        $all = (clone $ordered)->get();
        $summary = [
            'total_zones' => $all->count(),
            'operational_zones' => $hasIsActive ? $all->where('is_active', true)->count() : $all->count(),
            'pinned_on_map' => $all->filter(function (Area $a) use ($hasIsActive, $hasLat, $hasLng) {
                $activeOk = ! $hasIsActive || (bool) $a->is_active;
                $coordsOk = ($hasLat && $hasLng && $a->latitude !== null && $a->longitude !== null)
                    || $this->resolveUaeFallbackCenter($a) !== null;
                return $activeOk && $coordsOk;
            })->count(),
        ];

        $paginator = $ordered->paginate($perPage);
        $listData = $paginator->getCollection()->map(fn (Area $a) => $this->operationalAreaPayload($a))->values()->all();
        return response()->json([
            'success' => true,
            'message' => 'Operational areas retrieved successfully.',
            'data' => [
                'summary' => $summary,
                'areas' => $listData,
            ],
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
            'filters' => [
                'search' => $search !== '' ? $search : null,
                'country' => $country !== '' ? $country : null,
            ],
        ]);
    }

    /**
     * POST /api/admin/operational-areas/{id}/toggle-active
     * Toggle operational status for app switch control.
     */
    public function toggleOperationalArea(Request $request, int $id): JsonResponse
    {
        if (! Schema::hasColumn('areas', 'is_active')) {
            return response()->json([
                'success' => false,
                'message' => "The 'is_active' column is missing in areas table. Please run migrations.",
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'is_active' => 'nullable|boolean',
            'active' => 'nullable|boolean',
            'enabled' => 'nullable|boolean',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $area = Area::with('supervisors')->find($id);
        if (! $area) {
            return response()->json([
                'success' => false,
                'message' => 'Area not found.',
            ], 404);
        }

        $validated = $validator->validated();
        if (array_key_exists('is_active', $validated)) {
            $area->is_active = (bool) $validated['is_active'];
        } elseif (array_key_exists('active', $validated)) {
            $area->is_active = (bool) $validated['active'];
        } elseif (array_key_exists('enabled', $validated)) {
            $area->is_active = (bool) $validated['enabled'];
        } else {
            $area->is_active = ! (bool) $area->is_active;
        }
        $area->save();

        $payload = $this->operationalAreaPayload($area);

        return response()->json([
            'success' => true,
            'message' => $area->is_active ? 'Area enabled successfully.' : 'Area disabled successfully.',
            'area_id' => (int) $area->id,
            'is_active' => (bool) $area->is_active,
            'data' => $payload,
            // Compatibility alias for app clients expecting `area` key.
            'area' => $payload,
        ]);
    }

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
                'account_status' => $u->status ?? 'active',
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
                'account_status' => $u->status ?? 'active',
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

    /**
     * Area payload for admin zones APIs.
     * supervisor_id = first mapped supervisor (legacy / single-select UIs).
     * supervisor_ids + supervisors = full list (multi-supervisor zones).
     */
    private function areaToArray(Area $area, array $extra = []): array
    {
        $supervisorIds = [];
        $supervisors = [];
        if ($area->relationLoaded('supervisors')) {
            foreach ($area->supervisors as $u) {
                $id = (int) $u->id;
                $supervisorIds[] = $id;
                $supervisors[] = ['id' => $id, 'name' => (string) ($u->name ?? '')];
            }
        }
        $location = $area->location;
        if ($location === '' || $location === null) {
            $location = null;
        }
        return array_merge([
            'id' => (int) $area->id,
            'location' => $location,
            'supervisor_id' => $supervisorIds[0] ?? null,
            'supervisor_ids' => $supervisorIds,
            'supervisors' => $supervisors,
            'is_active' => (bool) ($area->is_active ?? true),
            'priority' => (int) ($area->priority ?? 100),
            'latitude' => $area->latitude !== null ? (float) $area->latitude : null,
            'longitude' => $area->longitude !== null ? (float) $area->longitude : null,
            'service_radius_km' => $area->service_radius_km !== null ? (float) $area->service_radius_km : null,
        ], $extra);
    }

    /**
     * Collect supervisor IDs from request.
     * Prefer supervisor_ids[] (multi). Fall back to singular supervisor_id.
     *
     * @return list<int>
     */
    private function supervisorIdsFromRequest(Request $request): array
    {
        $ids = [];
        if ($request->has('supervisor_ids')) {
            $raw = $request->input('supervisor_ids');
            if (is_array($raw)) {
                foreach ($raw as $id) {
                    $ids[] = (int) $id;
                }
            }
        } elseif ($request->has('supervisor_id')) {
            $id = (int) $request->input('supervisor_id');
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        return array_values(array_unique(array_filter($ids, fn (int $id) => $id > 0)));
    }

    /** Allow form-data JSON string / comma list for supervisor_ids before validation. */
    private function normalizeSupervisorIdsInput(Request $request): void
    {
        if (! $request->has('supervisor_ids')) {
            return;
        }
        $raw = $request->input('supervisor_ids');
        if (is_array($raw)) {
            return;
        }
        if (! is_string($raw)) {
            return;
        }
        $trimmed = trim($raw);
        if ($trimmed === '') {
            $request->merge(['supervisor_ids' => []]);

            return;
        }
        $decoded = json_decode($trimmed, true);
        if (is_array($decoded)) {
            $request->merge(['supervisor_ids' => $decoded]);

            return;
        }
        $parts = preg_split('/\s*,\s*/', $trimmed, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $request->merge(['supervisor_ids' => array_map('intval', $parts)]);
    }

    /**
     * GET /api/admin/areas – List zones. Response: id, location, supervisor_id only.
     * Query: per_page (1–100), search (by location), all=1 (return all areas, no pagination).
     */
    public function index(Request $request): JsonResponse
    {
        $query = Area::query()->with('supervisors')->orderBy('location');

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where('location', 'like', '%' . $search . '%');
        }

        $returnAll = $request->query('all') === '1' || $request->query('all') === 'true';
        if ($returnAll) {
            $areas = $query->get();
            $data = $areas->map(fn (Area $a) => $this->areaToArray($a))->values()->all();
            return response()->json([
                'success' => true,
                'message' => 'All areas retrieved successfully.',
                'data' => $data,
                'total' => count($data),
            ]);
        }

        $perPage = min(max((int) $request->query('per_page', 15), 1), 100);
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
     * POST /api/admin/areas – Create zone OR add supervisors to an existing same-location zone.
     * Never creates "Abu Dhabi (1)" duplicates when "Abu Dhabi" already exists — reuses that area_id.
     */
    public function store(Request $request): JsonResponse
    {
        $this->normalizeSupervisorIdsInput($request);

        $validator = Validator::make($request->all(), [
            'location' => 'required|string|max:255',
            'supervisor_id' => 'nullable|integer|exists:users,id',
            'supervisor_ids' => 'nullable|array|min:1',
            'supervisor_ids.*' => 'integer|exists:users,id',
            'is_active' => 'nullable|boolean',
            'priority' => 'nullable|integer|min:0|max:10000',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'service_radius_km' => 'nullable|numeric|min:0.1|max:1000',
        ]);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $supervisorIds = $this->supervisorIdsFromRequest($request);
        if ($supervisorIds === []) {
            return response()->json([
                'success' => false,
                'message' => 'At least one supervisor is required. Send supervisor_ids[] (multiple) or supervisor_id (single).',
            ], 422);
        }

        $validCount = User::role('supervisor')->whereIn('id', $supervisorIds)->count();
        if ($validCount !== count($supervisorIds)) {
            return response()->json([
                'success' => false,
                'message' => 'Every selected user must have the supervisor role. Use supervisor_ids[] or supervisor_id.',
            ], 422);
        }

        $location = trim((string) $request->input('location'));

        // Same location label already exists (incl. historical "Name (1)" dupes) → reuse one area_id.
        $existing = $this->findReusableAreaByLocationLabel($location);
        if ($existing) {
            $this->ensureSupervisorsAttached($existing, $supervisorIds);
            $existing = $this->mergeNumberedLocationDuplicates($existing, $location);
            $existing->load('supervisors');

            return response()->json([
                'success' => true,
                'message' => 'Supervisor(s) added to existing area. Same location keeps one area_id (no duplicate zone).',
                'data' => $this->areaToArray($existing),
                'reused' => true,
            ]);
        }

        $area = Area::create([
            'name' => $location,
            'description' => null,
            'location' => $location,
            'country' => 'UAE',
            'is_active' => $request->boolean('is_active', true),
            'priority' => (int) $request->input('priority', 100),
            'latitude' => $request->filled('latitude') ? (float) $request->input('latitude') : null,
            'longitude' => $request->filled('longitude') ? (float) $request->input('longitude') : null,
            'service_radius_km' => $request->filled('service_radius_km') ? (float) $request->input('service_radius_km') : 30,
        ]);

        $this->ensureSupervisorsAttached($area, $supervisorIds);

        $area->load('supervisors');
        return response()->json([
            'success' => true,
            'message' => 'Area created successfully.',
            'data' => $this->areaToArray($area),
            'reused' => false,
        ], 201);
    }

    /**
     * POST /api/admin/areas/consolidate-duplicates
     * Fix already-split zones like "Abu Dhabi" + "Abu Dhabi (1)" into one area_id.
     * Body: location (required) e.g. "Abu Dhabi". Moves supervisors + visits, deletes dupes.
     */
    public function consolidateDuplicates(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'location' => 'required|string|max:255',
        ]);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $location = trim((string) $request->input('location'));
        $canonical = $this->findReusableAreaByLocationLabel($location);
        if (! $canonical) {
            return response()->json([
                'success' => false,
                'message' => 'No area found for that location.',
            ], 404);
        }

        $mergedIds = $this->collectNumberedLocationDuplicateIds($canonical, $location);
        $canonical = $this->mergeNumberedLocationDuplicates($canonical, $location);
        $canonical->load('supervisors');

        return response()->json([
            'success' => true,
            'message' => 'Duplicate location zones consolidated into one area_id.',
            'data' => $this->areaToArray($canonical),
            'merged_area_ids' => $mergedIds,
        ]);
    }

    /**
     * GET /api/admin/areas/{id} – Show one zone. Response: id, location, supervisor_id only.
     */
    public function show(int $id): JsonResponse
    {
        $area = Area::with('supervisors')->findOrFail($id);
        return response()->json([
            'success' => true,
            'message' => 'Area retrieved successfully.',
            'data' => $this->areaToArray($area),
        ]);
    }

    /**
     * PUT/POST /api/admin/areas/{id} – Update zone.
     * Optional: location, supervisor_id / supervisor_ids — ADDS supervisors to the zone.
     * Existing supervisors are never removed by this endpoint (no overwrite / wipe).
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $this->normalizeSupervisorIdsInput($request);

        $validator = Validator::make($request->all(), [
            'location' => 'nullable|string|max:255',
            'supervisor_id' => 'nullable|integer|exists:users,id',
            'supervisor_ids' => 'nullable|array',
            'supervisor_ids.*' => 'integer|exists:users,id',
            'is_active' => 'nullable|boolean',
            'priority' => 'nullable|integer|min:0|max:10000',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'service_radius_km' => 'nullable|numeric|min:0.1|max:1000',
        ]);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $area = Area::findOrFail($id);
        if ($request->filled('location')) {
            $area->location = $request->input('location');
            $area->name = $request->input('location');
        }
        if ($request->has('is_active')) {
            $area->is_active = $request->boolean('is_active');
        }
        if ($request->filled('priority')) {
            $area->priority = (int) $request->input('priority');
        }
        if ($request->has('latitude')) {
            $area->latitude = $request->filled('latitude') ? (float) $request->input('latitude') : null;
        }
        if ($request->has('longitude')) {
            $area->longitude = $request->filled('longitude') ? (float) $request->input('longitude') : null;
        }
        if ($request->has('service_radius_km')) {
            $area->service_radius_km = $request->filled('service_radius_km') ? (float) $request->input('service_radius_km') : 30;
        }
        $area->save();

        if ($request->has('supervisor_ids') || $request->has('supervisor_id')) {
            $supervisorIds = $this->supervisorIdsFromRequest($request);
            if ($supervisorIds !== []) {
                $validCount = User::role('supervisor')->whereIn('id', $supervisorIds)->count();
                if ($validCount !== count($supervisorIds)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Every selected user must have the supervisor role. Use supervisor_ids[] or supervisor_id.',
                    ], 422);
                }
                $this->ensureSupervisorsAttached($area, $supervisorIds);
            }
        }

        $area->load('supervisors');
        return response()->json([
            'success' => true,
            'message' => 'Area updated successfully.',
            'data' => $this->areaToArray($area),
        ]);
    }

    /**
     * DELETE /api/admin/areas/{id}/supervisors/{supervisorId}
     * Remove ONE supervisor from this zone. Others stay. Zone itself is not deleted.
     */
    public function removeSupervisor(int $id, int $supervisorId): JsonResponse
    {
        $area = Area::with('supervisors')->findOrFail($id);

        if (! $area->supervisors()->where('users.id', $supervisorId)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'That supervisor is not assigned to this zone.',
            ], 404);
        }

        $area->supervisors()->detach($supervisorId);
        $area->load('supervisors');

        return response()->json([
            'success' => true,
            'message' => 'Supervisor removed from zone.',
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

    /**
     * Attach supervisors to a zone without removing anyone already mapped.
     * Day-1: assign one supervisor. Later: assign more — previous stay.
     */
    private function ensureSupervisorsAttached(Area $area, array $ids): void
    {
        $valid = User::role('supervisor')->whereIn('id', $ids)->pluck('id')->all();
        if ($valid === []) {
            return;
        }
        $area->supervisors()->syncWithoutDetaching($valid);
    }

    /**
     * Find an area for a location label, including historical duplicates named "X (1)".
     * Does NOT match distinct zones like "Abu Dhabi City" / "Mussafah".
     */
    private function findReusableAreaByLocationLabel(string $location): ?Area
    {
        $norm = strtolower(trim($location));
        if ($norm === '') {
            return null;
        }

        return Area::query()
            ->where(function ($q) use ($norm) {
                $q->whereRaw('LOWER(TRIM(name)) = ?', [$norm])
                    ->orWhereRaw('LOWER(TRIM(name)) LIKE ?', [$norm.' (%)']);
            })
            ->orderBy('id')
            ->first();
    }

    /**
     * @return list<int>
     */
    private function collectNumberedLocationDuplicateIds(Area $canonical, string $location): array
    {
        $norm = strtolower(trim($location));
        if ($norm === '') {
            return [];
        }

        return Area::query()
            ->where('id', '!=', $canonical->id)
            ->where(function ($q) use ($norm) {
                $q->whereRaw('LOWER(TRIM(name)) = ?', [$norm])
                    ->orWhereRaw('LOWER(TRIM(name)) LIKE ?', [$norm.' (%)']);
            })
            ->orderBy('id')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * Merge "Abu Dhabi (1)" style duplicates into the canonical area.
     * Moves supervisors, technicians, and visits; deletes duplicate rows.
     */
    private function mergeNumberedLocationDuplicates(Area $canonical, string $location): Area
    {
        $dupeIds = $this->collectNumberedLocationDuplicateIds($canonical, $location);
        if ($dupeIds === []) {
            // Normalize canonical name/location to the clean label.
            $clean = trim($location);
            if ($clean !== '' && ($canonical->name !== $clean || $canonical->location !== $clean)) {
                $canonical->name = $clean;
                $canonical->location = $clean;
                $canonical->save();
            }

            return $canonical->fresh() ?? $canonical;
        }

        foreach (Area::query()->whereIn('id', $dupeIds)->get() as $dupe) {
            $supIds = $dupe->supervisors()->pluck('users.id')->all();
            $techIds = $dupe->technicians()->pluck('users.id')->all();
            if ($supIds !== []) {
                $canonical->supervisors()->syncWithoutDetaching($supIds);
            }
            if ($techIds !== []) {
                $canonical->technicians()->syncWithoutDetaching($techIds);
            }
            Visit::query()->where('area_id', $dupe->id)->update(['area_id' => $canonical->id]);
            $dupe->supervisors()->detach();
            $dupe->technicians()->detach();
            $dupe->delete();
        }

        $clean = trim($location);
        if ($clean !== '') {
            $canonical->name = $clean;
            $canonical->location = $clean;
            $canonical->save();
        }

        return $canonical->fresh() ?? $canonical;
    }

    private function ensureTechniciansAndSync(Area $area, array $ids): void
    {
        $valid = User::role('technician')->whereIn('id', $ids)->pluck('id')->toArray();
        $area->technicians()->sync($valid);
    }

    private function operationalAreaPayload(Area $area): array
    {
        return [
            'id' => (int) $area->id,
            'name' => (string) ($area->name ?? $area->location ?? ('Area #' . $area->id)),
            'location' => $area->location,
            'country' => $area->country ?? 'UAE',
            'is_active' => (bool) ($area->is_active ?? true),
            'priority' => (int) ($area->priority ?? 100),
            'latitude' => $area->latitude !== null ? (float) $area->latitude : null,
            'longitude' => $area->longitude !== null ? (float) $area->longitude : null,
            'supervisors' => $area->relationLoaded('supervisors')
                ? $area->supervisors->map(fn (User $u) => ['id' => (int) $u->id, 'name' => $u->name])->values()->all()
                : [],
        ];
    }

    private function resolveUaeFallbackCenter(Area $area): ?array
    {
        $cityCenters = [
            'abu dhabi' => ['lat' => 24.4539, 'lng' => 54.3773],
            'al ain' => ['lat' => 24.2075, 'lng' => 55.7447],
            'dubai' => ['lat' => 25.2048, 'lng' => 55.2708],
            'sharjah' => ['lat' => 25.3463, 'lng' => 55.4209],
            'ajman' => ['lat' => 25.4052, 'lng' => 55.5136],
            'umm al quwain' => ['lat' => 25.5647, 'lng' => 55.5552],
            'ras al khaimah' => ['lat' => 25.7895, 'lng' => 55.9432],
            'fujairah' => ['lat' => 25.1288, 'lng' => 56.3265],
            'khor fakkan' => ['lat' => 25.3397, 'lng' => 56.3576],
            'kalba' => ['lat' => 24.9982, 'lng' => 56.2721],
            'dibba' => ['lat' => 25.5925, 'lng' => 56.2618],
            'hatta' => ['lat' => 24.8095, 'lng' => 56.1225],
        ];

        $haystack = strtolower(trim((string) ($area->name ?? '') . ' ' . (string) ($area->location ?? '')));
        if ($haystack === '') {
            return null;
        }
        foreach ($cityCenters as $key => $coords) {
            if (str_contains($haystack, $key)) {
                return $coords;
            }
        }

        return null;
    }
}
