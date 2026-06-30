<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Models\Visit;
use App\Models\VisitPhoto;
use App\Services\VisitPhotoService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Maintenance Photos API – client app home screen (read-only for clients).
 * Only photos flagged show_on_client_app by admin are returned.
 */
class MaintenancePhotosController extends Controller
{
    public function __construct(private readonly VisitPhotoService $visitPhotoService)
    {
    }

    /**
     * GET /api/maintenance-photos
     */
    public function index(Request $request)
    {
        $user = $request->user();
        if (! $user) {
            return ApiResponse::error('Unauthorized.', 403);
        }

        $subscriptionIds = Subscription::where('client_id', $user->id)->pluck('id');
        $visitIds = Visit::whereIn('subscription_id', $subscriptionIds)->pluck('id');

        if ($visitIds->isEmpty()) {
            return ApiResponse::success('Maintenance photos retrieved.', [
                'data' => [],
                'pagination' => [
                    'current_page' => 1,
                    'last_page' => 1,
                    'per_page' => (int) $request->query('per_page', 20),
                    'total' => 0,
                ],
            ]);
        }

        $query = VisitPhoto::query()
            ->where('show_on_client_app', true)
            ->whereIn('visit_id', $visitIds)
            ->with('visit:id,subscription_id,scheduled_date,status,completed_at');

        if ($request->filled('visit_id')) {
            $visitId = (int) $request->visit_id;
            if (! $visitIds->contains($visitId)) {
                return ApiResponse::error('Visit not found or access denied.', 404);
            }
            $query->where('visit_id', $visitId);
        }

        $perPage = min(max((int) $request->query('per_page', 20), 1), 100);
        $photos = $query->orderByDesc('created_at')->paginate($perPage);

        $data = $photos->getCollection()->map(function (VisitPhoto $photo) {
            $visit = $photo->visit;
            $visitSummary = $visit ? [
                'id' => $visit->id,
                'scheduled_date' => $visit->scheduled_date?->format('Y-m-d'),
                'status' => $visit->status,
                'status_display' => Str::title(str_replace('_', ' ', $visit->status ?? '')),
                'completed_at' => $visit->completed_at?->format('c'),
            ] : null;

            return $this->visitPhotoService->toApiItem($photo, $visitSummary);
        })->values()->all();

        return ApiResponse::success('Maintenance photos retrieved.', [
            'data' => $data,
            'pagination' => [
                'current_page' => $photos->currentPage(),
                'last_page' => $photos->lastPage(),
                'per_page' => $photos->perPage(),
                'total' => $photos->total(),
            ],
        ]);
    }

    /**
     * GET /api/maintenance-photos/visit/{visit_id}
     */
    public function byVisit(Request $request, $visitId)
    {
        $user = $request->user();
        if (! $user) {
            return ApiResponse::error('Unauthorized.', 403);
        }

        $visit = Visit::with(['photos' => fn ($q) => $q->where('show_on_client_app', true)])->find($visitId);
        if (! $visit) {
            return ApiResponse::error('Visit not found.', 404);
        }

        $subscriptionIds = Subscription::where('client_id', $user->id)->pluck('id');
        if (! $subscriptionIds->contains($visit->subscription_id)) {
            return ApiResponse::error('Visit not found or access denied.', 404);
        }

        $visitSummary = [
            'id' => $visit->id,
            'scheduled_date' => $visit->scheduled_date?->format('Y-m-d'),
            'status' => $visit->status,
            'status_display' => Str::title(str_replace('_', ' ', $visit->status ?? '')),
            'completed_at' => $visit->completed_at?->format('c'),
        ];

        $data = $visit->photos->map(fn (VisitPhoto $p) => $this->visitPhotoService->toApiItem($p, $visitSummary))->values()->all();

        return ApiResponse::success('Maintenance photos retrieved.', [
            'visit' => $visitSummary,
            'photos' => $data,
        ]);
    }
}
