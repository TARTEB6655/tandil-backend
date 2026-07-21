<?php

namespace App\Http\Controllers\Report;

use App\Http\Controllers\Controller;
use App\Models\Report;
use App\Models\Subscription;
use App\Models\Visit;
use App\Support\CapsPagination;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth:sanctum', 'role:client|technician|supervisor|area_manager|admin']);
    }

    public function index(Request $request)
    {
        try {
            $user = $request->user();
            if (!$user) {
                return response()->json(['status' => false, 'message' => 'Unauthenticated'], 401);
            }

            $role = strtolower(trim((string) ($user->role ?? '')));

            $query = Report::query()
                ->with([
                    'visit.subscription.client:id,name,email,phone',
                    'visit.technician:id,name,email,phone',
                    'visit.area:id,name,location',
                    'supervisor:id,name,email,phone',
                ])
                ->latest();

            if (in_array($role, ['admin', 'supervisor', 'area_manager'], true)
                || $user->hasRole('admin')
                || $user->hasRole('supervisor')
                || $user->hasRole('area_manager')) {
                // Admin-like roles see all reports (optionally paginated).
            } elseif ($role === 'client' || $user->hasRole('client')) {
                $subscriptionIds = Subscription::query()
                    ->where('client_id', $user->id)
                    ->pluck('id');
                $visitIds = Visit::query()
                    ->whereIn('subscription_id', $subscriptionIds)
                    ->pluck('id');
                $query->whereIn('visit_id', $visitIds);
            } elseif ($role === 'technician' || $user->hasRole('technician')) {
                $visitIds = Visit::query()
                    ->where('technician_id', $user->id)
                    ->pluck('id');
                $query->whereIn('visit_id', $visitIds);
            } else {
                $query->whereRaw('1 = 0');
            }

            if ($request->filled('status')) {
                $query->where('status', $request->input('status'));
            }

            if ($request->has('page') || $request->has('per_page')) {
                $perPage = CapsPagination::perPage($request, 20, 100);
                $paginator = $query->paginate($perPage);

                return response()->json([
                    'status' => true,
                    'data' => $paginator->items(),
                    'pagination' => [
                        'current_page' => $paginator->currentPage(),
                        'last_page' => $paginator->lastPage(),
                        'per_page' => $paginator->perPage(),
                        'total' => $paginator->total(),
                    ],
                ], 200);
            }

            $reports = $query->limit(200)->get();

            return response()->json([
                'status' => true,
                'data' => $reports,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to fetch reports: ' . $e->getMessage()
            ], 500);
        }
    }

    public function show($id)
    {
        try {
            $report = Report::with(['visit', 'supervisor'])->find($id);

            if (! $report) {
                return response()->json(['status' => false, 'message' => 'Report not found'], 404);
            }

            return response()->json(['status' => true, 'data' => $report], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to fetch report: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Single API for technician to submit field report to supervisor.
     * Body: visit_id, technician_notes (field notes), optional recommended_products, optional status.
     */
    public function store(Request $request)
    {
        try {
            $data = $request->validate([
                'visit_id' => 'required|integer|exists:visits,id',
                'technician_notes' => 'nullable|string',
                'notes' => 'nullable|string',
                'recommended_products' => 'nullable|array',
                'recommended_products.*' => 'nullable|string',
                'status' => 'nullable|string|in:draft,pending,approved,sent_to_client',
            ]);

            $user = $request->user();
            $visit = Visit::find($data['visit_id']);

            if ($user && $user->hasRole('technician')) {
                if ($visit->technician_id !== $user->id) {
                    return response()->json([
                        'status' => false,
                        'message' => 'You can only submit reports for visits assigned to you.',
                    ], 403);
                }
                $data['status'] = $data['status'] ?? 'pending';
            }

            $report = Report::create($data);

            return response()->json(['status' => true, 'data' => $report], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed.',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to create report: ' . $e->getMessage()
            ], 500);
        }
    }
}
