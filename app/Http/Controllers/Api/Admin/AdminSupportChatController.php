<?php

namespace App\Http\Controllers\Api\Admin;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\SupportChatSession;
use App\Services\SupportChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminSupportChatController extends Controller
{
    public function __construct(
        private readonly SupportChatService $chat
    ) {}

    /**
     * GET /api/admin/support-chat/sessions — list live chat sessions.
     */
    public function index(Request $request): JsonResponse
    {
        $query = SupportChatSession::query()
            ->with('user:id,name,email,role')
            ->withCount('messages')
            ->orderByDesc('updated_at');

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('user_role')) {
            $role = $request->input('user_role');
            $query->whereHas('user', fn ($q) => $q->where('role', $role));
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('token', 'like', "%{$search}%")
                    ->orWhere('subject', 'like', "%{$search}%")
                    ->orWhereHas('user', fn ($u) => $u
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%"));
            });
        }

        $perPage = min(max((int) $request->input('per_page', 20), 1), 50);
        $sessions = $query->paginate($perPage);

        $items = $sessions->getCollection()->map(function (SupportChatSession $session) {
            $data = $this->chat->sessionToArray($session);
            $data['messages_count'] = $session->messages_count;

            return $data;
        });

        return ApiResponse::success('Live chat sessions retrieved.', [
            'data' => $items->values()->all(),
            'pagination' => [
                'current_page' => $sessions->currentPage(),
                'last_page' => $sessions->lastPage(),
                'per_page' => $sessions->perPage(),
                'total' => $sessions->total(),
            ],
        ]);
    }

    /**
     * GET /api/admin/support-chat/sessions/by-token/{token}
     */
    public function showByToken(string $token): JsonResponse
    {
        $session = SupportChatSession::with('user:id,name,email,role')
            ->where('token', $token)
            ->first();

        if ($session === null) {
            return ApiResponse::error('Chat session not found.', 404);
        }

        return $this->sessionWithMessages($session);
    }

    /**
     * GET /api/admin/support-chat/sessions/{id}
     */
    public function show(int $id): JsonResponse
    {
        $session = SupportChatSession::with('user:id,name,email,role')->find($id);
        if ($session === null) {
            return ApiResponse::error('Chat session not found.', 404);
        }

        return $this->sessionWithMessages($session);
    }

    /**
     * GET /api/admin/support-chat/sessions/{id}/messages?after_id=
     */
    public function messages(Request $request, int $id): JsonResponse
    {
        $session = SupportChatSession::find($id);
        if ($session === null) {
            return ApiResponse::error('Chat session not found.', 404);
        }

        $afterId = $request->filled('after_id') ? (int) $request->query('after_id') : null;
        $messages = $this->chat->messagesForSession($session, $afterId);

        return ApiResponse::success('Messages retrieved.', [
            'session_id' => $session->id,
            'messages' => $messages->map(fn ($m) => $this->chat->messageToArray($m))->values()->all(),
        ]);
    }

    /**
     * POST /api/admin/support-chat/sessions/{id}/messages
     */
    public function sendMessage(Request $request, int $id): JsonResponse
    {
        $request->validate(['message' => 'required|string|max:5000']);

        $session = SupportChatSession::find($id);
        if ($session === null) {
            return ApiResponse::error('Chat session not found.', 404);
        }

        if ($session->isClosed()) {
            return ApiResponse::error('Chat is closed.', 422);
        }

        try {
            $message = $this->chat->sendMessage(
                $session,
                $request->user(),
                $request->input('message'),
                true
            );
        } catch (\InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }

        return ApiResponse::success('Message sent.', [
            'message' => $this->chat->messageToArray($message),
            'session' => $this->chat->sessionToArray($session->fresh(['user'])),
        ], 201);
    }

    /**
     * PUT /api/admin/support-chat/sessions/{id}/status
     */
    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $request->validate(['status' => 'required|string|in:open,in_progress,resolved,closed']);

        $session = SupportChatSession::with('user:id,name,email,role')->find($id);
        if ($session === null) {
            return ApiResponse::error('Chat session not found.', 404);
        }

        $session->update(['status' => $request->input('status')]);

        return ApiResponse::success('Chat status updated.', $this->chat->sessionToArray($session->fresh()));
    }

    private function sessionWithMessages(SupportChatSession $session): JsonResponse
    {
        $messages = $this->chat->messagesForSession($session);

        return ApiResponse::success('Chat session retrieved.', [
            'session' => $this->chat->sessionToArray($session),
            'messages' => $messages->map(fn ($m) => $this->chat->messageToArray($m))->values()->all(),
            'polling' => [
                'endpoint' => '/api/admin/support-chat/sessions/'.$session->id.'/messages',
                'param' => 'after_id',
                'interval_seconds' => 3,
            ],
        ]);
    }
}
