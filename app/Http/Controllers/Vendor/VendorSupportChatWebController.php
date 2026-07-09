<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\SupportChatSession;
use App\Services\SupportChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VendorSupportChatWebController extends Controller
{
    public function __construct(
        private readonly SupportChatService $chat
    ) {
        $this->middleware(['auth', 'role:vendor', 'vendor.account']);
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $session = $this->chat->getOrCreateSession($user);
        $messages = $this->chat->messagesForSession($session);

        return view('vendor.support-chat.index', [
            'session' => $session,
            'messages' => $messages,
            'sessionData' => $this->chat->sessionToArray($session),
        ]);
    }

    public function messages(Request $request): JsonResponse
    {
        $session = $this->resolveSession($request);
        if ($session === null) {
            return response()->json(['success' => false, 'message' => 'Chat not found.'], 404);
        }

        $afterId = $request->filled('after_id') ? (int) $request->query('after_id') : null;
        $messages = $this->chat->messagesForSession($session, $afterId);

        return response()->json([
            'success' => true,
            'session' => $this->chat->sessionToArray($session),
            'messages' => $messages->map(fn ($m) => $this->chat->messageToArray($m))->values()->all(),
        ]);
    }

    public function send(Request $request)
    {
        $request->validate(['message' => 'required|string|max:5000']);

        $session = $this->resolveSession($request);
        if ($session === null) {
            $session = $this->chat->getOrCreateSession($request->user());
        }

        if ($session->isClosed()) {
            return back()->withErrors(['message' => 'Chat is closed. Start a new conversation from Help & Support.']);
        }

        try {
            $this->chat->sendMessage($session, $request->user(), $request->input('message'), false);
        } catch (\InvalidArgumentException $e) {
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
            }

            return back()->withErrors(['message' => $e->getMessage()]);
        }

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'message' => 'Message sent.']);
        }

        return back()->with('success', 'Message sent to support.');
    }

    public function widgetData(Request $request): JsonResponse
    {
        $session = $this->resolveSession($request);
        if ($session === null) {
            $session = SupportChatSession::query()
                ->where('user_id', $request->user()->id)
                ->whereIn('status', ['open', 'in_progress'])
                ->latest('updated_at')
                ->first();
        }

        if ($session === null) {
            return response()->json([
                'success' => true,
                'session' => null,
                'messages' => [],
            ]);
        }

        $afterId = $request->filled('after_id') ? (int) $request->query('after_id') : null;
        $messages = $this->chat->messagesForSession($session, $afterId);

        return response()->json([
            'success' => true,
            'session' => $this->chat->sessionToArray($session),
            'messages' => $messages->map(fn ($m) => $this->chat->messageToArray($m))->values()->all(),
            'full_page_url' => route('vendor.support-chat.index'),
        ]);
    }

    private function resolveSession(Request $request): ?SupportChatSession
    {
        return SupportChatSession::query()
            ->where('user_id', $request->user()->id)
            ->whereIn('status', ['open', 'in_progress'])
            ->latest('updated_at')
            ->first();
    }
}
