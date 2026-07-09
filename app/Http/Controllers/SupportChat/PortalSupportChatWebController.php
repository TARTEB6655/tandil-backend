<?php

namespace App\Http\Controllers\SupportChat;

use App\Http\Controllers\Controller;
use App\Models\SupportChatSession;
use App\Services\SupportChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Live chat widget + polling for portal users (vendor, client, technician, etc.).
 */
class PortalSupportChatWebController extends Controller
{
    public function __construct(
        private readonly SupportChatService $chat
    ) {
        $this->middleware('auth');
    }

    public function widgetData(Request $request): JsonResponse
    {
        return $this->sessionPayload($request);
    }

    public function messages(Request $request): JsonResponse
    {
        $session = $this->chat->resolveDisplaySession($request->user());
        if ($session === null) {
            return response()->json(['success' => false, 'message' => 'Chat not found.'], 404);
        }

        return $this->sessionPayload($request, $session);
    }

    public function send(Request $request): JsonResponse
    {
        $request->validate([
            'message' => 'nullable|string|max:5000',
            'image' => 'nullable|image|max:5120',
        ]);

        if (! $request->filled('message') && ! $request->hasFile('image')) {
            return response()->json(['success' => false, 'message' => 'Message or image is required.'], 422);
        }

        $session = $this->chat->resolveActiveSession($request->user());
        if ($session === null) {
            $session = $this->chat->getOrCreateSession($request->user());
        }

        try {
            $message = $this->chat->sendMessage(
                $session,
                $request->user(),
                (string) $request->input('message', ''),
                false,
                $request->file('image')
            );
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Message sent.',
            'chat_message' => $this->chat->messageToArray($message),
            'session' => $this->chat->sessionToArray($session->fresh(['user'])),
        ]);
    }

    private function sessionPayload(Request $request, ?SupportChatSession $session = null): JsonResponse
    {
        $session ??= $this->chat->resolveDisplaySession($request->user());

        if ($session === null) {
            return response()->json([
                'success' => true,
                'session' => null,
                'messages' => [],
                'unread_count' => 0,
                'can_send' => true,
            ]);
        }

        $afterId = $request->filled('after_id') ? (int) $request->query('after_id') : null;
        $messages = $this->chat->messagesForSession($session, $afterId);
        $sessionData = $this->chat->sessionToArray($session);

        return response()->json([
            'success' => true,
            'session' => $sessionData,
            'messages' => $messages->map(fn ($m) => $this->chat->messageToArray($m))->values()->all(),
            'unread_count' => $this->unreadAdminCount($session, $afterId),
            'can_send' => ! $session->isClosed(),
            'closed_notice' => $session->isClosed()
                ? $this->closedNoticeForStatus((string) $session->status)
                : null,
        ]);
    }

    private function closedNoticeForStatus(string $status): string
    {
        if ($status === 'resolved') {
            return 'This chat was marked as resolved by support. You can start a new conversation below.';
        }

        return 'This chat was closed by support. You can start a new conversation below.';
    }

    private function unreadAdminCount(SupportChatSession $session, ?int $afterId): int
    {
        if ($session->isClosed()) {
            return 0;
        }

        $query = $session->messages()->where('is_admin', true);
        if ($afterId !== null && $afterId > 0) {
            $query->where('id', '>', $afterId);
        }

        return (int) $query->count();
    }
}
