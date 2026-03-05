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
     * GET /api/admin/supervisors – Supervisor list only (no team count). Click on a supervisor then call GET /api/admin/supervisors/{id}/team for team members.
     * Returns: id, name, email, employee_id, zone (first zone or null), assigned_zones.
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
            $query->where(function ($q) use ($term, $search) {
                $q->where('users.name', 'like', $term)
                    ->orWhere('users.email', 'like', $term)
                    ->orWhereHas('employee', function ($eq) use ($term) {
                        $eq->where('employee_id', 'like', $term);
                    })
                    ->orWhereHas('supervisedAreas', function ($eq) use ($term) {
                        $eq->where('name', 'like', $term);
                    });
                // Match displayed "SUP-{id}" when user has no Employee record (fallback is 'SUP-' . user id)
                if (preg_match('/^SUP-(\d+)$/i', trim($search), $m)) {
                    $q->orWhere('users.id', (int) $m[1]);
                }
            });
        }

        $supervisors = $query->paginate($perPage);

        $data = $supervisors->getCollection()->map(function (User $u) {
            $zones = $u->supervisedAreas->map(fn ($a) => ['id' => $a->id, 'name' => $a->name])->values()->all();
            $firstZone = $zones[0] ?? null;

            return [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email ?? '',
                'employee_id' => $u->employee?->employee_id ?? ('SUP-' . $u->id),
                'zone' => $firstZone,
                'assigned_zones' => $zones,
            ];
        })->all();

        return response()->json([
            'success' => true,
            'message' => $search !== '' ? "Supervisors matching \"{$search}\"." : 'All supervisors with assigned zones. Use GET /api/admin/supervisors/{id}/team for team members.',
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
     * POST /api/admin/supervisors/{id}/team – Add a technician to the supervisor's team (assign to first assigned zone).
     * Body: technician_id (required).
     */
    public function addTeamMember(Request $request, int $id): JsonResponse
    {
        $supervisor = User::role('supervisor')->with('supervisedAreas')->find($id);
        if (! $supervisor) {
            return response()->json(['success' => false, 'message' => 'Supervisor not found.'], 404);
        }

        $validator = Validator::make($request->all(), [
            'technician_id' => 'required|integer|exists:users,id',
        ]);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $technicianId = (int) $request->input('technician_id');
        $supervisorAreaIds = $supervisor->supervisedAreaIds();

        if (empty($supervisorAreaIds)) {
            return response()->json(['success' => false, 'message' => 'Supervisor has no assigned zones.'], 422);
        }

        $technician = User::role('technician')->find($technicianId);
        if (! $technician) {
            return response()->json(['success' => false, 'message' => 'Technician not found.'], 404);
        }

        $areaId = $supervisorAreaIds[0];
        $area = Area::find($areaId);
        $area->technicians()->syncWithoutDetaching([$technicianId]);

        return response()->json([
            'success' => true,
            'message' => "{$technician->name} has been added to the team.",
            'data' => [
                'technician_id' => $technicianId,
            ],
        ], 201);
    }

    /**
     * DELETE /api/admin/supervisors/{id}/team – Remove a technician from the supervisor's team (unassign from all of the supervisor's zones).
     * Body or query: technician_id (required). For DELETE, prefer query string ?technician_id=3 if the client does not send a body.
     */
    public function removeTeamMember(Request $request, int $id): JsonResponse
    {
        $supervisor = User::role('supervisor')->with('supervisedAreas')->find($id);
        if (! $supervisor) {
            return response()->json(['success' => false, 'message' => 'Supervisor not found.'], 404);
        }

        $technicianId = (int) ($request->input('technician_id') ?? $request->query('technician_id'));
        if (! $technicianId && $request->getContent()) {
            $content = $request->getContent();
            $body = json_decode($content, true);
            if (is_array($body) && isset($body['technician_id'])) {
                $technicianId = (int) $body['technician_id'];
            }
            if (! $technicianId && $request->header('Content-Type')) {
                if (str_contains($request->header('Content-Type'), 'application/x-www-form-urlencoded')) {
                    parse_str($content, $params);
                    $technicianId = (int) ($params['technician_id'] ?? 0);
                }
                if (! $technicianId && str_contains($request->header('Content-Type'), 'multipart/form-data')) {
                    $technicianId = (int) $this->parseFormDataValue($content, $request->header('Content-Type'), 'technician_id');
                }
            }
        }

        if (! $technicianId) {
            return response()->json(['success' => false, 'message' => 'technician_id is required (query param or body).'], 422);
        }

        $supervisorAreaIds = $supervisor->supervisedAreaIds();
        if (empty($supervisorAreaIds)) {
            return response()->json(['success' => true, 'message' => 'Technician has been removed from the team.', 'data' => ['technician_id' => $technicianId]]);
        }

        DB::table('area_technician')
            ->whereIn('area_id', $supervisorAreaIds)
            ->where('user_id', $technicianId)
            ->delete();

        return response()->json([
            'success' => true,
            'message' => 'Technician has been removed from the team.',
            'data' => [
                'technician_id' => $technicianId,
            ],
        ]);
    }

    /** Parse a field value from multipart/form-data raw body (used for DELETE where PHP does not populate $_POST). */
    private function parseFormDataValue(string $content, string $contentType, string $name): ?string
    {
        if (! preg_match('/boundary=(?:"([^"]+)"|([^\s;]+))/', $contentType, $m)) {
            return null;
        }
        $boundaryValue = trim($m[1] ?? $m[2], '"');
        $delimiter = '\r?\n--' . preg_quote($boundaryValue, '/');
        $parts = preg_split('/' . $delimiter . '(?=\r\n|\r|\n|--)/s', $content);
        $parts = array_slice($parts, 1, -1);
        foreach ($parts as $part) {
            if (preg_match('/Content-Disposition:\s*form-data[^;]*;\s*name="' . preg_quote($name, '/') . '"(\r?\n\r?\n|\r\n\r\n)(.*)/s', $part, $match)) {
                return trim(preg_replace('/\r?\n.*/s', '', $match[2]));
            }
        }
        return null;
    }
}
