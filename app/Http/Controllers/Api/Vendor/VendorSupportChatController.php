<?php

namespace App\Http\Controllers\Api\Vendor;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\SupportChatSession;
use App\Services\SupportChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VendorSupportChatController extends Controller
{
    public function __construct(
        private readonly SupportChatService $chat
    ) {}

    /**
     * GET /api/vendor/support/chat — open live chat (session + messages).
     */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        $session = $this->chat->resolveDisplaySession($user) ?? $this->chat->getOrCreateSession($user);
        $messages = $this->chat->messagesForSession($session);

        return ApiResponse::success('Live chat loaded.', [
            'chat' => [
                'session' => $this->chat->sessionToArray($session),
                'messages' => $messages->map(fn ($m) => $this->chat->messageToArray($m))->values()->all(),
                'polling' => [
                    'endpoint' => '/api/vendor/support/chat/messages',
                    'param' => 'after_id',
                    'interval_seconds' => 3,
                ],
            ],
        ]);
    }

    /**
     * GET /api/vendor/support/chat/messages?after_id= — poll new messages.
     */
    public function messages(Request $request): JsonResponse
    {
        $session = $this->chat->resolveDisplaySession($request->user());
        if ($session === null) {
            return ApiResponse::error('Chat session not found.', 404);
        }

        $afterId = $request->filled('after_id') ? (int) $request->query('after_id') : null;
        $messages = $this->chat->messagesForSession($session, $afterId);

        return ApiResponse::success('Messages retrieved.', [
            'session' => $this->chat->sessionToArray($session),
            'session_id' => $session->id,
            'messages' => $messages->map(fn ($m) => $this->chat->messageToArray($m))->values()->all(),
            'can_send' => ! $session->isClosed(),
        ]);
    }

    /**
     * POST /api/vendor/support/chat/messages — send message to admin.
     */
    public function sendMessage(Request $request): JsonResponse
    {
        $request->validate(['message' => 'required|string|max:5000']);

        $session = $this->chat->resolveActiveSession($request->user());
        if ($session === null) {
            $session = $this->chat->getOrCreateSession($request->user());
        }

        try {
            $message = $this->chat->sendMessage(
                $session,
                $request->user(),
                $request->input('message'),
                false
            );
        } catch (\InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }

        return ApiResponse::success('Message sent.', [
            'message' => $this->chat->messageToArray($message),
            'session' => $this->chat->sessionToArray($session->fresh()),
        ], 201);
    }

    private function resolveVendorSession(Request $request): ?SupportChatSession
    {
        $user = $request->user();

        if ($request->filled('session_id')) {
            return SupportChatSession::query()
                ->where('user_id', $user->id)
                ->whereKey((int) $request->query('session_id'))
                ->first();
        }

        return SupportChatSession::query()
            ->where('user_id', $user->id)
            ->whereIn('status', ['open', 'in_progress'])
            ->latest('updated_at')
            ->first();
    }
}
