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
    public function __construct()
    {
        // Middleware is handled in routes, but we can add auth here for safety
        $this->middleware('auth:sanctum');
    }

    /**
     * List visits under supervisor's supervised areas.
     */
    public function listVisits(Request $request)
    {
        $user = $request->user();

        // Get IDs of supervised areas
        $areaIds = $user->supervisedAreas()->pluck('areas.id')->toArray();

        $visits = Visit::whereIn('area_id', $areaIds)
            ->with(['subscription.client', 'technician', 'report', 'photos'])
            ->latest()
            ->get();

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

        $visit = Visit::with(['photos', 'subscription.client', 'report'])->find($id);

        if (!$visit) {
            return response()->json(['status' => false, 'message' => 'Visit not found'], 404);
        }

        // Check if visit is in supervised areas
        $areaIds = $user->supervisedAreas()->pluck('areas.id')->toArray();
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

        $areaIds = $user->supervisedAreas()->pluck('areas.id')->toArray();
        if (!in_array($visit->area_id, $areaIds)) {
            return response()->json(['status' => false, 'message' => 'Forbidden'], 403);
        }

        $validator = Validator::make($request->all(), [
            'product_ids' => 'required|array',
            'product_ids.*' => 'integer|exists:products,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'errors' => $validator->errors()], 422);
        }

        $productIds = $request->input('product_ids');

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
     * Finalize or update a report for a visit.
     */
    public function finalizeReport(Request $request, $id)
    {
        $user = $request->user();
        $visit = Visit::with('subscription.client')->find($id);

        if (!$visit) {
            return response()->json(['status' => false, 'message' => 'Visit not found'], 404);
        }

        $areaIds = $user->supervisedAreas()->pluck('areas.id')->toArray();
        if (!in_array($visit->area_id, $areaIds)) {
            return response()->json(['status' => false, 'message' => 'Forbidden'], 403);
        }

        $validator = Validator::make($request->all(), [
            'notes' => 'nullable|string',
            'status' => 'nullable|string|in:pending,finalized,rejected',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'errors' => $validator->errors()], 422);
        }

        $report = Report::firstOrCreate(['visit_id' => $visit->id]);

        if ($request->has('notes')) {
            $report->notes = $request->input('notes');
        }

        if ($request->has('status')) {
            $report->status = $request->input('status');

            // If finalized, set approved_by and approved_at
            if ($report->status === 'finalized') {
                $report->approved_by = $user->id;
                $report->approved_at = now();

                // Notify client
                try {
                    $client = $visit->subscription->client;
                    if ($client) {
                        $client->notify(new \App\Notifications\ReportFinalized($report));
                    }
                } catch (\Throwable $e) {
                    // Log error if needed
                }
            }
        }

        $report->save();

        return response()->json(['status' => true, 'data' => $report], 200);
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

        $areaIds = $user->supervisedAreas()->pluck('areas.id')->toArray();
        if (!in_array($visit->area_id, $areaIds)) {
            return response()->json(['status' => false, 'message' => 'Forbidden'], 403);
        }

        $validator = Validator::make($request->all(), [
            'status' => 'required|string|in:approved,rejected',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'errors' => $validator->errors()], 422);
        }

        $status = $request->input('status');
        $visit->status = $status;

        // Optionally, you can record who approved/rejected and when
        $visit->approved_by = $user->id;
        $visit->approved_at = now();

        $visit->save();

        return response()->json(['status' => true, 'message' => "Visit status updated to {$status}", 'data' => $visit], 200);
    }

    /**
     * View complaints related to supervisor's areas.
     */
    public function listComplaints(Request $request)
    {
        $user = $request->user();

        $areaIds = $user->supervisedAreas()->pluck('areas.id')->toArray();

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
        $areaIds = $user->supervisedAreas()->pluck('areas.id')->toArray();
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
