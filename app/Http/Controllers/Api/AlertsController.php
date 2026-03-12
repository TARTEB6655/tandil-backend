<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * In-app alerts (database notifications) for technician/supervisor.
 * Includes e.g. leave request approved/rejected. Use with GET /api/technician/alerts or /api/supervisor/alerts.
 */
class AlertsController extends Controller
{
    /**
     * GET /api/technician/alerts or /api/supervisor/alerts
     * List my database notifications (e.g. leave approved/rejected). Paginated.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $perPage = max(1, min(50, (int) $request->get('per_page', 20)));

        $paginator = $user->notifications()->orderByDesc('created_at')->paginate($perPage);

        $list = $paginator->getCollection()->map(function ($notification) {
            $data = $notification->data ?? [];
            return [
                'id' => $notification->id,
                'type' => $data['type'] ?? 'admin_notification',
                'title' => $data['title'] ?? '',
                'message' => $data['message'] ?? '',
                'meta' => $data['meta'] ?? [],
                'read_at' => $notification->read_at?->toIso8601String(),
                'created_at' => $notification->created_at->toIso8601String(),
            ];
        })->values()->all();

        return response()->json([
            'success' => true,
            'data' => $list,
            'unread_count' => $user->unreadNotifications()->count(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }
}
