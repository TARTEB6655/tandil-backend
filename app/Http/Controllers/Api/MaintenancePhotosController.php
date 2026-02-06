<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Helpers\ApiResponse;
use App\Models\VisitPhoto;
use App\Models\Visit;
use Illuminate\Http\Request;

/**
 * Maintenance Photos API – for the client app "Maintenance Photos" section on home.
 * Photos are visit photos (uploaded by technicians during service visits).
 * Client sees only photos from their own visits (via their subscriptions).
 */
class MaintenancePhotosController extends Controller
{
    /**
     * Build full URL for a stored visit photo path.
     */
    private function photoUrl(?string $path): ?string
    {
        if (! $path || ! is_string($path)) {
            return null;
        }
        $path = ltrim(str_replace('\\', '/', $path), '/');
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }
        if (function_exists('request') && request() && request()->getHttpHost()) {
            return rtrim(request()->getSchemeAndHttpHost(), '/') . '/storage/' . $path;
        }
        return asset('storage/' . $path);
    }

    /**
     * Map a VisitPhoto to API response item (id, photo_url, type, visit_id, visit summary for "View Order").
     */
    private function photoToItem(VisitPhoto $photo, ?array $visitSummary = null): array
    {
        $item = [
            'id' => $photo->id,
            'photo_url' => $this->photoUrl($photo->photo_path),
            'photo_path' => $photo->photo_path,
            'type' => $photo->type ?? 'after',
            'visit_id' => $photo->visit_id,
            'created_at' => $photo->created_at?->format('c'),
        ];
        if ($visitSummary !== null) {
            $item['visit'] = $visitSummary;
        }
        return $item;
    }

    /**
     * GET /api/maintenance-photos
     * List recent maintenance photos for the authenticated client (from their visits).
     * Optional: limit, visit_id (filter by one visit).
     */
    public function index(Request $request)
    {
        $user = $request->user();
        if (! $user || ! $user->hasRole('client')) {
            return ApiResponse::error('Unauthorized. Client role required.', 403);
        }

        $subscriptionIds = \App\Models\Subscription::where('client_id', $user->id)->pluck('id');
        $visitIds = Visit::whereIn('subscription_id', $subscriptionIds)->pluck('id');

        if ($visitIds->isEmpty()) {
            return ApiResponse::success('Maintenance photos retrieved.', [
                'data' => [],
                'pagination' => ['current_page' => 1, 'last_page' => 1, 'per_page' => (int) $request->query('per_page', 20), 'total' => 0],
            ]);
        }

        $query = VisitPhoto::whereIn('visit_id', $visitIds)->with('visit:id,subscription_id,scheduled_date,status,completed_at');

        if ($request->has('visit_id')) {
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
                'completed_at' => $visit->completed_at?->format('c'),
            ] : null;
            return $this->photoToItem($photo, $visitSummary);
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
     * List maintenance photos for a specific visit. Client must own the visit (via subscription).
     */
    public function byVisit(Request $request, $visitId)
    {
        $user = $request->user();
        if (! $user || ! $user->hasRole('client')) {
            return ApiResponse::error('Unauthorized. Client role required.', 403);
        }

        $visit = Visit::with('photos')->find($visitId);
        if (! $visit) {
            return ApiResponse::error('Visit not found.', 404);
        }

        $subscriptionIds = \App\Models\Subscription::where('client_id', $user->id)->pluck('id');
        if (! $subscriptionIds->contains($visit->subscription_id)) {
            return ApiResponse::error('Visit not found or access denied.', 404);
        }

        $visitSummary = [
            'id' => $visit->id,
            'scheduled_date' => $visit->scheduled_date?->format('Y-m-d'),
            'status' => $visit->status,
            'completed_at' => $visit->completed_at?->format('c'),
        ];

        $data = $visit->photos->map(fn (VisitPhoto $p) => $this->photoToItem($p, $visitSummary))->values()->all();

        return ApiResponse::success('Maintenance photos retrieved.', [
            'visit' => $visitSummary,
            'photos' => $data,
        ]);
    }
}
