<?php

namespace App\Http\Controllers\Api\Admin;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\AdminNotificationBroadcast;
use App\Services\NotificationBroadcastService;
use App\Support\UserNotificationAudience;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class NotificationBroadcastController extends Controller
{
    /**
     * Send-notification form metadata for admin mobile app (role dropdown, scope types).
     */
    public function options()
    {
        return ApiResponse::success('Broadcast options retrieved successfully.', [
            'scope_types' => [
                ['value' => 'all', 'label' => 'All users'],
                ['value' => 'role', 'label' => 'Specific role'],
                ['value' => 'users', 'label' => 'Selected users'],
            ],
            'roles' => UserNotificationAudience::broadcastRoleOptions(),
        ]);
    }

    /**
     * Send a broadcast (same behaviour as admin web). Optional per-role title/message overrides in messages_by_role.
     */
    public function store(Request $request)
    {
        $validated = $request->validate(NotificationBroadcastService::validationRules());
        $broadcast = NotificationBroadcastService::send($request->user(), $validated);

        return ApiResponse::success('Notification sent successfully.', [
            'broadcast_id' => $broadcast->id,
            'total_recipients' => $broadcast->total_recipients,
            'recipient_counts' => $broadcast->recipientCountsForApi(),
        ], 201);
    }

    public function index(Request $request)
    {
        $perPage = (int) $request->get('per_page', 20);
        $perPage = $perPage >= 1 && $perPage <= 100 ? $perPage : 20;

        $paginator = AdminNotificationBroadcast::query()
            ->with('sentBy:id,name,email')
            ->latest()
            ->paginate($perPage);

        $items = collect($paginator->items())->map(function (AdminNotificationBroadcast $b) {
            return [
                'id' => $b->id,
                'title' => $b->title,
                'message' => $b->message,
                'scope_type' => $b->scope_type,
                'scope_role' => $b->scope_role,
                'recipient_counts' => $b->recipientCountsForApi(),
                'sent_by' => $b->sentBy ? [
                    'id' => $b->sentBy->id,
                    'name' => $b->sentBy->name,
                    'email' => $b->sentBy->email,
                ] : null,
                'created_at' => $b->created_at?->toIso8601String(),
            ];
        })->all();

        return ApiResponse::success('Broadcast history retrieved successfully.', [
            'broadcasts' => $items,
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function show(Request $request, int $id)
    {
        $broadcast = AdminNotificationBroadcast::with('sentBy:id,name,email')->find($id);
        if (! $broadcast) {
            return ApiResponse::error('Broadcast not found.', 404);
        }

        return ApiResponse::success('Broadcast retrieved successfully.', [
            'id' => $broadcast->id,
            'title' => $broadcast->title,
            'message' => $broadcast->message,
            'scope_type' => $broadcast->scope_type,
            'scope_role' => $broadcast->scope_role,
            'messages_by_role' => $broadcast->messages_by_role,
            'recipient_counts' => $broadcast->recipientCountsForApi(),
            'sent_by' => $broadcast->sentBy ? [
                'id' => $broadcast->sentBy->id,
                'name' => $broadcast->sentBy->name,
                'email' => $broadcast->sentBy->email,
            ] : null,
            'created_at' => $broadcast->created_at?->toIso8601String(),
            'updated_at' => $broadcast->updated_at?->toIso8601String(),
        ]);
    }
}
