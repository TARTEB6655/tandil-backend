<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Area;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * Admin Supervisors & Teams API.
 * List all supervisors; get a supervisor's team (technicians in their zones); add/remove team members.
 */
class SupervisorController extends Controller
{
    /**
     * GET /api/admin/supervisors – All supervisors for "Supervisors & Teams" screen.
     * Returns: id, name, email, employee_id, assigned_zones (id, name), zone (first zone or null), team_count.
     * Query: per_page, search (name, email, employee_id, zone name).
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = min(max((int) $request->query('per_page', 50), 1), 100);
        $search = is_string($request->query('search')) ? trim($request->query('search')) : '';

        $query = User::role('supervisor')
            ->with(['employee', 'supervisedAreas'])
            ->orderBy('name');

        if ($search !== '') {
            $term = '%' . $search . '%';
            $query->where(function ($q) use ($term) {
                $q->where('users.name', 'like', $term)
                    ->orWhere('users.email', 'like', $term)
                    ->orWhereHas('employee', function ($eq) use ($term) {
                        $eq->where('employee_id', 'like', $term);
                    })
                    ->orWhereHas('supervisedAreas', function ($eq) use ($term) {
                        $eq->where('name', 'like', $term);
                    });
            });
        }

        $supervisors = $query->paginate($perPage);

        $data = $supervisors->getCollection()->map(function (User $u) {
            $zones = $u->supervisedAreas->map(fn ($a) => ['id' => $a->id, 'name' => $a->name])->values()->all();
            $firstZone = $zones[0] ?? null;
            $areaIds = $u->supervisedAreas->pluck('id')->all();
            $teamCount = empty($areaIds)
                ? 0
                : (int) DB::table('area_technician')->whereIn('area_id', $areaIds)->count(DB::raw('DISTINCT user_id'));

            return [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email ?? '',
                'employee_id' => $u->employee?->employee_id ?? ('SUP-' . $u->id),
                'zone' => $firstZone,
                'assigned_zones' => $zones,
                'team_count' => $teamCount,
            ];
        })->all();

        return response()->json([
            'success' => true,
            'message' => $search !== '' ? "Supervisors matching \"{$search}\"." : 'All supervisors with assigned zones and team count.',
            'data' => $data,
            'pagination' => [
                'current_page' => $supervisors->currentPage(),
                'last_page' => $supervisors->lastPage(),
                'per_page' => $supervisors->perPage(),
                'total' => $supervisors->total(),
            ],
            'search' => $search !== '' ? $search : null,
        ]);
    }

    /**
     * GET /api/admin/supervisors/{id}/team – Team list for a supervisor (technicians in their zones).
     */
    public function team(Request $request, int $id): JsonResponse
    {
        $supervisor = User::role('supervisor')->with('supervisedAreas')->find($id);
        if (! $supervisor) {
            return response()->json(['success' => false, 'message' => 'Supervisor not found.'], 404);
        }

        $areaIds = $supervisor->supervisedAreaIds();
        if (empty($areaIds)) {
            return response()->json([
                'success' => true,
                'data' => [
                    'supervisor' => [
                        'id' => $supervisor->id,
                        'name' => $supervisor->name,
                        'employee_id' => $supervisor->employee?->employee_id ?? ('SUP-' . $supervisor->id),
                        'assigned_zones' => [],
                    ],
                    'team' => [],
                ],
            ]);
        }

        $technicianIds = DB::table('area_technician')->whereIn('area_id', $areaIds)->distinct()->pluck('user_id');
        $team = User::role('technician')
            ->whereIn('id', $technicianIds)
            ->with(['employee', 'assignedAreas' => fn ($q) => $q->whereIn('areas.id', $areaIds)])
            ->orderBy('name')
            ->get()
            ->map(function (User $u) use ($areaIds) {
                $zonesInScope = $u->assignedAreas->map(fn ($a) => ['id' => $a->id, 'name' => $a->name])->values()->all();
                return [
                    'id' => $u->id,
                    'name' => $u->name,
                    'email' => $u->email ?? '',
                    'employee_id' => $u->employee?->employee_id ?? ('TECH-' . $u->id),
                    'service_areas' => $u->employee?->service_areas ?? [],
                    'specializations' => $u->employee?->specializations ?? [],
                    'assigned_zones' => $zonesInScope,
                ];
            })
            ->values()
            ->all();

        $zones = $supervisor->supervisedAreas->map(fn ($a) => ['id' => $a->id, 'name' => $a->name])->values()->all();

        return response()->json([
            'success' => true,
            'data' => [
                'supervisor' => [
                    'id' => $supervisor->id,
                    'name' => $supervisor->name,
                    'email' => $supervisor->email,
                    'employee_id' => $supervisor->employee?->employee_id ?? ('SUP-' . $supervisor->id),
                    'assigned_zones' => $zones,
                ],
                'team' => $team,
            ],
        ]);
    }

    /**
     * POST /api/admin/supervisors/{id}/team – Add a technician to the supervisor's team (assign to one of their zones).
     * Body: technician_id (required), area_id (required; must be one of the supervisor's zones).
     */
    public function addTeamMember(Request $request, int $id): JsonResponse
    {
        $supervisor = User::role('supervisor')->with('supervisedAreas')->find($id);
        if (! $supervisor) {
            return response()->json(['success' => false, 'message' => 'Supervisor not found.'], 404);
        }

        $validator = Validator::make($request->all(), [
            'technician_id' => 'required|integer|exists:users,id',
            'area_id' => 'required|integer|exists:areas,id',
        ]);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $areaId = (int) $request->input('area_id');
        $technicianId = (int) $request->input('technician_id');

        $supervisorAreaIds = $supervisor->supervisedAreaIds();
        if (! in_array($areaId, $supervisorAreaIds, true)) {
            return response()->json(['success' => false, 'message' => 'Area must be one of the supervisor\'s assigned zones.'], 422);
        }

        $technician = User::role('technician')->find($technicianId);
        if (! $technician) {
            return response()->json(['success' => false, 'message' => 'Technician not found.'], 404);
        }

        $area = Area::find($areaId);
        $area->technicians()->syncWithoutDetaching([$technicianId]);

        return response()->json([
            'success' => true,
            'message' => "{$technician->name} has been added to the team (assigned to {$area->name}).",
            'data' => [
                'technician_id' => $technicianId,
                'area_id' => $areaId,
            ],
        ], 201);
    }

    /**
     * DELETE /api/admin/supervisors/{id}/team – Remove a technician from the supervisor's team (unassign from a zone).
     * Body or query: technician_id (required), area_id (required; must be one of the supervisor's zones).
     */
    public function removeTeamMember(Request $request, int $id): JsonResponse
    {
        $supervisor = User::role('supervisor')->find($id);
        if (! $supervisor) {
            return response()->json(['success' => false, 'message' => 'Supervisor not found.'], 404);
        }

        $technicianId = (int) ($request->input('technician_id') ?? $request->query('technician_id'));
        $areaId = (int) ($request->input('area_id') ?? $request->query('area_id'));

        if (! $technicianId || ! $areaId) {
            return response()->json(['success' => false, 'message' => 'technician_id and area_id are required.'], 422);
        }

        $supervisorAreaIds = $supervisor->supervisedAreaIds();
        if (! in_array($areaId, $supervisorAreaIds, true)) {
            return response()->json(['success' => false, 'message' => 'Area must be one of the supervisor\'s assigned zones.'], 422);
        }

        $area = Area::find($areaId);
        if (! $area) {
            return response()->json(['success' => false, 'message' => 'Area not found.'], 404);
        }

        $area->technicians()->detach($technicianId);

        return response()->json([
            'success' => true,
            'message' => 'Technician has been removed from the team for this zone.',
            'data' => [
                'technician_id' => $technicianId,
                'area_id' => $areaId,
            ],
        ]);
    }
}
