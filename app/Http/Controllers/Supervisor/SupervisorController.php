<?php

namespace App\Http\Controllers\Supervisor;

use App\Http\Controllers\Controller;
use App\Models\Visit;
use App\Models\Report;
use App\Models\Product;
use App\Models\Area;
use App\Models\Complaint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SupervisorController extends Controller
{
    /**
     * Accept product_ids from form-data as JSON string, CSV, or array.
     */
    private function normalizeProductIds(mixed $value): ?array
    {
        if (is_array($value)) {
            return $value;
        }

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        $trimmed = trim($value);
        $decoded = json_decode($trimmed, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        $parts = array_filter(array_map('trim', explode(',', $trimmed)), fn ($item) => $item !== '');
        return count($parts) ? $parts : null;
    }

    public function __construct()
    {
        // Middleware is handled in routes, but we can add auth here for safety
        $this->middleware('auth:sanctum');
    }

    /**
     * List visits under supervisor's supervised areas.
     * Optional ?status= for tabs: active (pending + in_progress), completed (completed + approved), or single status (pending, in_progress, completed, approved).
     */
    public function listVisits(Request $request)
    {
        $user = $request->user();

        $areaIds = $user->supervisedAreaIds();

        $query = Visit::whereIn('area_id', $areaIds)
            ->with(['subscription.client', 'technician', 'report', 'photos']);

        $status = $request->query('status');
        if ($status === 'active') {
            $query->whereIn('status', ['pending', 'in_progress']);
        } elseif ($status === 'completed') {
            $query->whereIn('status', ['completed', 'approved']);
        } elseif ($status !== null && $status !== '') {
            $query->where('status', $status);
        }

        $visits = $query->latest()->get();

        return response()->json(['status' => true, 'data' => $visits], 200);
    }

    /**
     * List areas supervised by this user.
     */
    public function listAreas(Request $request)
    {
        $user = $request->user();

        $areas = $user->supervisedAreas()->with(['technicians', 'visits'])->get();

        return response()->json(['status' => true, 'data' => $areas], 200);
    }

    /**
     * View details of a visit for review.
     */
    public function reviewVisit(Request $request, $id)
    {
        $user = $request->user();

        $visit = Visit::with(['photos', 'subscription.client', 'report', 'technician'])->find($id);

        if (!$visit) {
            return response()->json(['status' => false, 'message' => 'Visit not found'], 404);
        }

        // Check if visit is in supervised areas
        $areaIds = $user->supervisedAreaIds();
        if (!in_array($visit->area_id, $areaIds)) {
            return response()->json(['status' => false, 'message' => 'Forbidden'], 403);
        }

        return response()->json(['status' => true, 'data' => $visit], 200);
    }

    /**
     * Recommend products related to a visit.
     */
    public function recommendProducts(Request $request, $id)
    {
        $user = $request->user();
        $visit = Visit::find($id);

        if (!$visit) {
            return response()->json(['status' => false, 'message' => 'Visit not found'], 404);
        }

        $areaIds = $user->supervisedAreaIds();
        if (!in_array($visit->area_id, $areaIds)) {
            return response()->json(['status' => false, 'message' => 'Forbidden'], 403);
        }

        $payload = $request->all();
        if (array_key_exists('product_ids', $payload)) {
            $normalized = $this->normalizeProductIds($payload['product_ids']);
            if ($normalized !== null) {
                $payload['product_ids'] = $normalized;
            }
        }

        $validator = Validator::make($payload, [
            'product_ids' => 'required|array',
            'product_ids.*' => 'integer|exists:products,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'errors' => $validator->errors()], 422);
        }

        $productIds = $payload['product_ids'];

        $products = Product::whereIn('id', $productIds)->get();

        $report = Report::firstOrCreate(
            ['visit_id' => $visit->id],
            ['notes' => '', 'status' => 'pending']
        );

        $report->recommended_products = $products->pluck('id')->toArray();
        $report->save();

        return response()->json(['status' => true, 'data' => $report->load('visit')], 200);
    }

    /**
     * Finalize report and optionally submit to client (Select Recommendations → Submit Report to Client).
     * Body: notes, supervisor_notes, recommendations[] (e.g. "Needs Fertilizer", "Needs Vitamins"),
     * recommended_products[] (product IDs), status (pending|finalized|sent_to_client|rejected).
     */
    public function finalizeReport(Request $request, $id)
    {
        $user = $request->user();
        $visit = Visit::with('subscription.client')->find($id);

        if (!$visit) {
            return response()->json(['success' => false, 'status' => false, 'message' => 'Visit not found'], 404);
        }

        $areaIds = $user->supervisedAreaIds();
        $inMyArea = !empty($areaIds) && in_array($visit->area_id, $areaIds);
        $assignedByMe = (int) $visit->supervisor_id === (int) $user->id;
        if (!$inMyArea && !$assignedByMe) {
            return response()->json(['success' => false, 'status' => false, 'message' => 'Forbidden'], 403);
        }

        // Only supervisors can check/complete the job, and only after the technician has submitted a field report.
        $report = Report::where('visit_id', $visit->id)->first();
        if (!$report) {
            return response()->json([
                'success' => false,
                'status' => false,
                'message' => 'Technician must submit a field report (POST /api/technician/reports) before the supervisor can check and complete this job.',
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'notes' => 'nullable|string|max:5000',
            'supervisor_notes' => 'nullable|string|max:5000',
            'recommendations' => 'nullable|array',
            'recommendations.*' => 'nullable|string|max:255',
            'recommended_products' => 'nullable|array',
            'recommended_products.*' => 'nullable',
            'status' => 'nullable|string|in:pending,finalized,sent_to_client,rejected',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'status' => false, 'errors' => $validator->errors()], 422);
        }

        $report->supervisor_id = $user->id;
        if ($request->has('notes')) {
            $report->notes = $request->input('notes');
        }
        if ($request->has('supervisor_notes')) {
            $report->supervisor_notes = $request->input('supervisor_notes');
        }
        if ($request->has('recommendations')) {
            $report->recommendations = array_values(array_filter($request->input('recommendations', [])));
        }
        if ($request->has('recommended_products')) {
            $report->recommended_products = array_values($request->input('recommended_products', []));
        }

        $status = $request->input('status');
        if ($status) {
            $report->status = $status === 'finalized' ? 'approved' : $status;
            if (in_array($status, ['finalized', 'sent_to_client'], true)) {
                $report->approved_by = $user->id;
                $report->approved_at = now();
                if ($status === 'sent_to_client') {
                    $report->status = 'sent_to_client';
                }
                // ReportFinalized notification is disabled system-wide (see App\Notifications\ReportFinalized::via).
            }
        }

        $report->save();

        return response()->json([
            'success' => true,
            'status' => true,
            'message' => in_array($status, ['finalized', 'sent_to_client'], true)
                ? 'Report submitted to client successfully.'
                : 'Report updated.',
            'data' => $report->load(['visit.subscription.client', 'supervisor']),
        ], 200);
    }

    /**
     * Approve or reject visit status.
     */
    public function updateVisitStatus(Request $request, $id)
    {
        $user = $request->user();
        $visit = Visit::find($id);

        if (!$visit) {
            return response()->json(['status' => false, 'message' => 'Visit not found'], 404);
        }

        $areaIds = $user->supervisedAreaIds();
        if (!in_array($visit->area_id, $areaIds)) {
            return response()->json(['status' => false, 'message' => 'Forbidden'], 403);
        }

        $validator = Validator::make($request->all(), [
            'status' => 'required|string|in:approved,rejected',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'errors' => $validator->errors()], 422);
        }

        $oldStatus = $visit->status;
        $status = $request->input('status');
        $visit->status = $status;

        // Optionally, you can record who approved/rejected and when
        $visit->approved_by = $user->id;
        $visit->approved_at = now();

        $visit->save();

        // 🔔 Send notifications
        try {
            // Notify client
            if ($visit->subscription && $visit->subscription->client) {
                $visit->subscription->client->notify(new \App\Notifications\AdminNotification(
                    $status === 'approved' ? 'Visit Approved' : 'Visit Rejected',
                    $status === 'approved' 
                        ? "Your visit #{$visit->id} has been approved by the supervisor."
                        : "Your visit #{$visit->id} has been rejected. Please contact support."
                ));
            }

            // Notify technician
            if ($visit->technician_id) {
                $technician = \App\Models\User::find($visit->technician_id);
                if ($technician) {
                    $technician->notify(new \App\Notifications\AdminNotification(
                        $status === 'approved' ? 'Visit Approved' : 'Visit Rejected',
                        $status === 'approved'
                            ? "Visit #{$visit->id} has been approved by the supervisor."
                            : "Visit #{$visit->id} has been rejected. Please review and resubmit."
                    ));
                }
            }
        } catch (\Exception $e) {
            \Log::error('Failed to send visit status notification: ' . $e->getMessage());
        }

        return response()->json(['status' => true, 'message' => "Visit status updated to {$status}", 'data' => $visit], 200);
    }

    /**
     * View complaints related to supervisor's areas.
     */
    public function listComplaints(Request $request)
    {
        $user = $request->user();

        $areaIds = $user->supervisedAreaIds();

        $complaints = Complaint::whereHas('visit', function ($query) use ($areaIds) {
            $query->whereIn('visits.area_id', $areaIds);
        })->with(['visit', 'client'])->get();

        return response()->json(['status' => true, 'data' => $complaints], 200);
    }

    /**
     * Escalate or update a complaint.
     */
    public function escalateComplaint(Request $request, $complaintId)
    {
        $user = $request->user();
        $complaint = Complaint::find($complaintId);

        if (!$complaint) {
            return response()->json(['status' => false, 'message' => 'Complaint not found'], 404);
        }

        // Check if complaint belongs to supervised area
        $areaIds = $user->supervisedAreaIds();
        if (!in_array($complaint->visit->area_id, $areaIds)) {
            return response()->json(['status' => false, 'message' => 'Forbidden'], 403);
        }

        $validator = Validator::make($request->all(), [
            'status' => 'nullable|in:open,in_progress,resolved,escalated',
            'note' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'errors' => $validator->errors()], 422);
        }

        if ($request->has('status')) {
            $complaint->status = $request->input('status');
        }

        if ($request->has('note')) {
            $complaint->notes = $request->input('note');
        }

        $complaint->save();

        return response()->json(['status' => true, 'data' => $complaint], 200);
    }
}
