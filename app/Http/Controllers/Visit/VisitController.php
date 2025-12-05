<?php

namespace App\Http\Controllers\Visit;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Visit;
use App\Models\VisitPhoto;
use Illuminate\Support\Facades\Validator;

class VisitController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('role:client|technician|supervisor|area_manager|admin');
    }

    /**
     * List visits based on role:
     * - Admin: All visits
     * - Area Manager: Visits in managed areas
     * - Supervisor: Visits in supervised areas
     * - Technician: Assigned visits
     * - Client: Visits of their subscriptions
     */
    public function index(Request $request)
    {
        try {
            $user = $request->user();
            if (!$user) {
                return response()->json(['status' => false, 'message' => 'Unauthenticated'], 401);
            }

            if ($user->hasRole('admin')) {
                // Admin sees everything
                $visits = Visit::with(['subscription.client', 'technician', 'supervisor', 'area', 'photos'])
                    ->latest()
                    ->get();
            } elseif ($user->hasRole('area_manager')) {
                // Area Manager → visits in their managed areas
                $areaIds = $user->supervisedAreas()->pluck('areas.id')->toArray();

                $visits = Visit::whereIn('area_id', $areaIds)
                    ->with(['subscription.client', 'technician', 'supervisor', 'area', 'photos'])
                    ->latest()
                    ->get();
            } elseif ($user->hasRole('supervisor')) {
                // Supervisor → visits in areas they supervise
                $areaIds = $user->supervisedAreas()->pluck('areas.id')->toArray();

                $visits = Visit::whereIn('area_id', $areaIds)
                    ->with(['subscription.client', 'technician', 'supervisor', 'area', 'photos'])
                    ->latest()
                    ->get();
            } elseif ($user->hasRole('technician')) {
                // Technician → assigned visits
                $visits = Visit::where('technician_id', $user->id)
                    ->with(['subscription.client', 'technician', 'supervisor', 'area', 'photos'])
                    ->latest()
                    ->get();
            } else {
                // Client → their subscription visits
                $visits = Visit::whereHas('subscription', function ($q) use ($user) {
                    $q->where('client_id', $user->id);
                })
                ->with(['subscription.client', 'technician', 'supervisor', 'area', 'photos'])
                ->latest()
                ->get();
            }

            return response()->json(['status' => true, 'data' => $visits], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to fetch visits: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Show details of a visit
     */
    public function show(Request $request, $id)
    {
        $visit = Visit::with(['subscription.client', 'technician', 'supervisor', 'area', 'photos', 'report'])
            ->find($id);

        if (!$visit) {
            return response()->json(['status' => false, 'message' => 'Visit not found'], 404);
        }

        $user = $request->user();

        // Authorization rules
        if (
            $user->hasRole('admin') ||
            ($user->hasRole('technician') && $visit->technician_id === $user->id) ||
            ($user->hasRole('client') && $visit->subscription->client_id === $user->id) ||
            ($user->hasRole('supervisor') && in_array($visit->area_id, $user->supervisedAreas()->pluck('areas.id')->toArray())) ||
            ($user->hasRole('area_manager') && in_array($visit->area_id, $user->supervisedAreas()->pluck('areas.id')->toArray()))
        ) {
            return response()->json(['status' => true, 'data' => $visit], 200);
        }

        return response()->json(['status' => false, 'message' => 'Forbidden'], 403);
    }

    /**
     * Upload a photo (technician/admin only)
     */
    public function uploadPhoto(Request $request, $id)
    {
        $visit = Visit::find($id);
        if (!$visit) {
            return response()->json(['status' => false, 'message' => 'Visit not found'], 404);
        }

        $user = $request->user();

        // Authorization: technician assigned or admin
        if (!(
            $user->hasRole('admin') ||
            ($user->hasRole('technician') && $visit->technician_id == $user->id)
        )) {
            return response()->json(['status' => false, 'message' => 'Forbidden'], 403);
        }

        // Validation
        $validator = Validator::make($request->all(), [
            'photo' => 'required|image|max:5120',
            'type'  => 'nullable|string|in:before,after',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'errors' => $validator->errors()], 422);
        }

        // Upload
        $path = $request->file('photo')->store('visit_photos', 'public');

        $photo = VisitPhoto::create([
            'visit_id'   => $visit->id,
            'type'       => $request->input('type', 'before'),
            'photo_path' => $path,
        ]);

        return response()->json(['status' => true, 'data' => $photo], 201);
    }

    /**
     * Update visit status
     * - Technician marks as in_progress/completed
     * - Supervisor approves
     * - Admin can change anything
     */
    public function updateStatus(Request $request, $id)
    {
        $visit = Visit::find($id);
        if (!$visit) {
            return response()->json(['status' => false, 'message' => 'Visit not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'status' => 'required|string|in:pending,in_progress,completed,approved,rejected',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'errors' => $validator->errors()], 422);
        }

        $user = $request->user();
        $status = $request->status;

        // Permissions
        if ($user->hasRole('technician') && $visit->technician_id !== $user->id) {
            return response()->json(['status' => false, 'message' => 'Forbidden'], 403);
        }

        if ($user->hasRole('supervisor')) {
            $areaIds = $user->supervisedAreas()->pluck('areas.id')->toArray();
            if (!in_array($visit->area_id, $areaIds)) {
                return response()->json(['status' => false, 'message' => 'Forbidden'], 403);
            }
        }

        if ($user->hasRole('client')) {
            return response()->json(['status' => false, 'message' => 'Forbidden'], 403);
        }

        // Technician can only mark in_progress or completed
        if ($user->hasRole('technician') && !in_array($status, ['in_progress', 'completed'])) {
            return response()->json(['status' => false, 'message' => 'Technician cannot set this status'], 403);
        }

        // Supervisor can approve/reject
        if ($user->hasRole('supervisor') && !in_array($status, ['approved', 'rejected'])) {
            return response()->json(['status' => false, 'message' => 'Supervisor can only approve/reject'], 403);
        }

        // Save status
        $visit->status = $status;

        if ($status === 'completed') {
            $visit->completed_date = now();
        }

        $visit->save();

        return response()->json(['status' => true, 'message' => 'Status updated', 'data' => $visit], 200);
    }
}
