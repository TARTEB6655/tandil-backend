<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\MaintenancePhoto;
use App\Services\MaintenancePhotoService;
use App\Support\MaintenancePhotoCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Maintenance Photos API – client app home screen (read-only).
 */
class MaintenancePhotosController extends Controller
{
    public function __construct(private readonly MaintenancePhotoService $photos)
    {
    }

    /**
     * GET /api/maintenance-photos
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = min(max((int) $request->query('per_page', 20), 1), 100);
        $page = max((int) $request->query('page', 1), 1);

        $payload = Cache::remember(
            MaintenancePhotoCache::publicListKey($page, $perPage),
            MaintenancePhotoCache::PUBLIC_LIST_TTL_SECONDS,
            function () use ($page, $perPage) {
                $photos = MaintenancePhoto::query()
                    ->where('is_active', true)
                    ->orderBy('priority')
                    ->orderByDesc('id')
                    ->paginate($perPage, ['*'], 'page', $page);

                $data = $photos->getCollection()
                    ->map(fn (MaintenancePhoto $photo) => $this->photos->toApiItem($photo))
                    ->values()
                    ->all();

                return [
                    'data' => $data,
                    'pagination' => [
                        'current_page' => $photos->currentPage(),
                        'last_page' => $photos->lastPage(),
                        'per_page' => $photos->perPage(),
                        'total' => $photos->total(),
                    ],
                ];
            }
        );

        return ApiResponse::success('Maintenance photos retrieved.', $payload)
            ->header('Cache-Control', 'public, max-age=120, stale-while-revalidate=600');
    }
}
