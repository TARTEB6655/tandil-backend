<?php

namespace App\Http\Controllers\Supervisor;

use App\Http\Controllers\Controller;
use App\Models\TechnicianBreak;
use App\Models\User;
use App\Models\Visit;
use App\Services\VisitOfferService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class TeamController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'role:supervisor']);
    }

    private function areaIds(): array
    {
        return request()->user()->supervisedAreaIds();
    }

    private function teamMemberIdsInZones(array $areaIds): \Illuminate\Support\Collection
    {
        if (empty($areaIds)) {
            return collect();
        }
        return collect(DB::table('area_technician')->whereIn('area_id', $areaIds)->distinct()->pluck('user_id'));
    }

    private function assignableVisitsQuery()
    {
        $areaIds = $this->areaIds();
        return Visit::query()
            ->whereIn('area_id', $areaIds)
            ->where(function ($q) {
                $q->whereNull('technician_id')
                    ->orWhereIn('status', ['pending', 'scheduled'])
                    ->orWhereNotNull('escalated_at');
            });
    }

    private function mapTeamMemberToArray(User $u, array $areaIds, Carbon $now, string $today): array
    {
        $visits = $u->visits->whereIn('area_id', $areaIds);
        $totalTasks = $visits->count();
        $completedTasks = $visits->whereIn('status', ['completed', 'approved'])->count();
        $currentVisit = $visits->where('status', 'in_progress')->first();

        $onBreak = TechnicianBreak::where('user_id', $u->id)
            ->whereDate('date', $today)
            ->get()
            ->contains(fn ($b) => $this->isTimeInBreak($now, $b->start_time ?? '', $b->end_time ?? ''));

        $status = $onBreak ? 'Break' : 'Active';
        $currentActivity = $onBreak ? 'On Break' : null;
        if (! $onBreak && $currentVisit) {
            $meta = $currentVisit->notes ? $this->parseVisitMetaFromNotes((string) $currentVisit->notes) : [];
            $loc = $meta['farm_name'] ?? $currentVisit->area?->name ?? $currentVisit->subscription?->client?->name ?? 'Visit';
            $svc = $meta['service_name'] ?? ($currentVisit->subscription?->plan ? str_replace('_', ' ', (string) $currentVisit->subscription->plan) : null) ?? '';
            $currentActivity = $svc ? "{$loc} - {$svc}" : $loc;
        }
        $currentActivity = $currentActivity ?? ($status === 'Break' ? 'On Break' : '—');

        return [
            'id' => $u->id,
            'name' => $u->name,
            'employee_id' => $u->employee?->employee_id ?? ('TECH-' . $u->id),
            'profile_picture_url' => $u->profile_picture_url,
            'status' => $status,
            'current_activity' => $currentActivity,
            'tasks_completed' => $completedTasks,
            'tasks_total' => $totalTasks,
            'tasks_display' => $totalTasks > 0 ? "{$completedTasks}/{$totalTasks}" : '0/0',
            'email' => $u->email,
            'phone' => $u->phone,
        ];
    }

    private function isTimeInBreak(Carbon $now, string $startTime, string $endTime): bool
    {
        if ($startTime === '' || $endTime === '') {
            return false;
        }
        $start = Carbon::parse($now->toDateString() . ' ' . $startTime);
        $end = Carbon::parse($now->toDateString() . ' ' . $endTime);
        return $now->between($start, $end);
    }

    private function parseVisitMetaFromNotes(string $notes): array
    {
        $meta = [];
        foreach (explode("\n", $notes) as $line) {
            if (preg_match('/^(\w+):\s*(.+)$/', trim($line), $m)) {
                $meta[$m[1]] = trim($m[2]);
            }
        }
        return $meta;
    }

    /**
     * My Team – list of technicians in supervisor's zones.
     */
    public function index(): View
    {
        $areaIds = $this->areaIds();
        $technicianIds = $this->teamMemberIdsInZones($areaIds);
        $now = Carbon::now();
        $today = $now->toDateString();

        $teamMembers = [];
        if ($technicianIds->isNotEmpty()) {
            $technicians = User::role('technician')
                ->active()
                ->whereIn('id', $technicianIds)
                ->with(['employee', 'technicianAvailability', 'visits' => fn ($q) => $q->whereIn('area_id', $areaIds)])
                ->orderBy('name')
                ->get();
            foreach ($technicians as $u) {
                $teamMembers[] = $this->mapTeamMemberToArray($u, $areaIds, $now, $today);
            }
        }

        return view('supervisor.team.index', compact('teamMembers'));
    }

    /**
     * Single team member detail.
     */
    public function show(int $id): View|RedirectResponse
    {
        $areaIds = $this->areaIds();
        $technicianIds = $this->teamMemberIdsInZones($areaIds);
        if (! $technicianIds->contains($id)) {
            return redirect()->route('supervisor.team.index')->with('error', 'Team member not found or not in your zones.');
        }

        $now = Carbon::now();
        $today = $now->toDateString();
        $u = User::role('technician')
            ->where('id', $id)
            ->with(['employee', 'technicianAvailability', 'visits' => fn ($q) => $q->whereIn('area_id', $areaIds)])
            ->firstOrFail();

        $member = $this->mapTeamMemberToArray($u, $areaIds, $now, $today);
        return view('supervisor.team.show', compact('member'));
    }

    /**
     * Pending jobs (assignable visits) and assign form.
     */
    public function assignJobs(): View
    {
        $areaIds = $this->areaIds();
        $technicianIds = $this->teamMemberIdsInZones($areaIds);

        $pendingVisits = $this->assignableVisitsQuery()
            ->with(['subscription.client', 'area', 'technician'])
            ->orderByRaw('escalated_at IS NOT NULL DESC')
            ->orderBy('scheduled_date')
            ->paginate(15);

        $teamMembers = [];
        if ($technicianIds->isNotEmpty()) {
            $teamMembers = User::role('technician')
                ->active()
                ->whereIn('id', $technicianIds)
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn ($u) => ['id' => $u->id, 'name' => $u->name])
                ->all();
        }

        return view('supervisor.assign-jobs.index', compact('pendingVisits', 'teamMembers'));
    }

    /**
     * POST assign job to technician (offer with time limit).
     */
    public function assignJobStore(Request $request): RedirectResponse
    {
        $request->validate([
            'visit_id' => 'required|integer|exists:visits,id',
            'technician_id' => 'required|integer|exists:users,id',
            'scheduled_date' => 'nullable|date',
            'note' => 'nullable|string|max:1000',
        ]);

        $visit = $this->assignableVisitsQuery()->findOrFail((int) $request->input('visit_id'));
        $technician = User::role('technician')->active()->find((int) $request->input('technician_id'));
        if (! $technician) {
            return back()->with('error', 'Technician not found or inactive.');
        }

        $visit->supervisor_id = $request->user()->id;
        $visit->escalated_at = null;
        $visit->offer_count = 0;
        if ($request->filled('scheduled_date')) {
            $visit->scheduled_date = $request->input('scheduled_date');
        }
        if ($request->filled('note')) {
            $visit->notes = trim(($visit->notes ? $visit->notes . "\n" : '') . $request->input('note'));
        }
        VisitOfferService::offerToTechnician($visit, $technician->id);

        $minutes = VisitOfferService::ACCEPT_MINUTES;
        return redirect()->route('supervisor.assign-jobs.index')
            ->with('success', "Job offered to {$technician->name}. They have {$minutes} minutes to accept. If they reject or don't respond, it will go to another technician in the same zone or escalate to you.");
    }
}
