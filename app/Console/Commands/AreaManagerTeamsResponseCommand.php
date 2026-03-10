<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\Visit;
use App\Services\ProfilePictureUploadService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AreaManagerTeamsResponseCommand extends Command
{
    protected $signature = 'area-manager:teams-response';

    protected $description = 'Output the full JSON response for GET /api/area-manager/teams (for debugging).';

    public function handle(): int
    {
        $supervisorIds = DB::table('area_supervisor')->distinct()->pluck('user_id');
        $supervisors = User::role('supervisor')
            ->whereIn('id', $supervisorIds)
            ->with('employee')
            ->get();

        $list = $supervisors->map(function (User $u) {
            $areaIds = $u->supervisedAreaIds();
            $firstArea = $u->supervisedAreas()->first();
            $location = $firstArea?->name ?? $firstArea?->location ?? $u->employee?->region ?? null;
            $teamCount = empty($areaIds) ? 0 : DB::table('area_technician')->whereIn('area_id', $areaIds)->distinct()->count('user_id');
            $visitQuery = Visit::where(function ($q) use ($u, $areaIds) {
                $q->where('supervisor_id', $u->id);
                if (! empty($areaIds)) {
                    $q->orWhereIn('area_id', $areaIds);
                }
            });
            $activeCount = (clone $visitQuery)->whereIn('status', ['pending', 'scheduled', 'in_progress', 'started'])->count();
            $doneCount = (clone $visitQuery)->whereIn('status', ['completed', 'approved'])->count();
            $total = $activeCount + $doneCount;
            $performance = $total > 0 ? round(($doneCount / $total) * 100, 0) : 0;
            $initial = mb_substr(trim($u->name), 0, 1) ?: '?';

            return [
                'id' => $u->id,
                'name' => $u->name,
                'employee_id' => $u->employee?->employee_id ?? ('SUP-' . $u->id),
                'initial' => mb_strtoupper($initial),
                'location' => $location,
                'profile_picture' => $u->profile_picture,
                'profile_picture_url' => ProfilePictureUploadService::fullUrlOrDefault($u->profile_picture, $initial),
                'performance_percent' => $performance,
                'team' => $teamCount,
                'active' => $activeCount,
                'done' => $doneCount,
            ];
        })->values()->all();

        $response = [
            'success' => true,
            'data' => $list,
            'meta' => ['total' => count($list)],
        ];

        $this->line(json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return 0;
    }
}
