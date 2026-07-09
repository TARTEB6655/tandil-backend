<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SupportChatSession;
use App\Services\SupportChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupportChatWebController extends Controller
{
    public function __construct(
        private readonly SupportChatService $chat
    ) {
        $this->middleware('role:admin');
    }

    public function index(Request $request)
    {
        $query = SupportChatSession::query()
            ->with('user:id,name,email,role')
            ->withCount('messages')
            ->orderByDesc('updated_at');

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('user_role')) {
            $query->whereHas('user', fn ($q) => $q->where('role', $request->input('user_role')));
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

        $stats = [
            'open' => SupportChatSession::where('status', 'open')->count(),
            'in_progress' => SupportChatSession::where('status', 'in_progress')->count(),
            'resolved' => SupportChatSession::where('status', 'resolved')->count(),
            'vendor_active' => SupportChatSession::whereIn('status', ['open', 'in_progress'])
                ->whereHas('user', fn ($q) => $q->where('role', 'vendor'))
                ->count(),
        ];

        $sessions = $query->paginate(20)->withQueryString();

        return view('admin.support-chat.index', compact('sessions', 'stats'));
    }

    public function show(SupportChatSession $session)
    {
        $session->load(['user.vendor', 'user:id,name,email,role,phone']);
        $messages = $this->chat->messagesForSession($session);

        return view('admin.support-chat.show', [
            'session' => $session,
            'messages' => $messages,
            'sessionData' => $this->chat->sessionToArray($session),
        ]);
    }

    public function messages(Request $request, SupportChatSession $session): JsonResponse
    {
        $afterId = $request->filled('after_id') ? (int) $request->query('after_id') : null;
        $messages = $this->chat->messagesForSession($session, $afterId);

        return response()->json([
            'success' => true,
            'messages' => $messages->map(fn ($m) => $this->chat->messageToArray($m))->values()->all(),
            'session' => $this->chat->sessionToArray($session->fresh(['user'])),
        ]);
    }

    public function reply(Request $request, SupportChatSession $session)
    {
        $request->validate(['message' => 'required|string|max:5000']);

        if ($session->isClosed()) {
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => 'This chat is closed.'], 422);
            }

            return back()->withErrors(['message' => 'This chat is closed.'])->withInput();
        }

        try {
            $chatMessage = $this->chat->sendMessage(
                $session,
                $request->user(),
                $request->input('message'),
                true
            );
        } catch (\InvalidArgumentException $e) {
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
            }

            return back()->withErrors(['message' => $e->getMessage()])->withInput();
        }

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Reply sent.',
                'chat_message' => $this->chat->messageToArray($chatMessage),
                'session' => $this->chat->sessionToArray($session->fresh(['user'])),
            ]);
        }

        return back()->with('success', 'Reply sent.');
    }

    public function updateStatus(Request $request, SupportChatSession $session)
    {
        $request->validate(['status' => 'required|string|in:open,in_progress,resolved,closed']);

        $session = $this->chat->updateSessionStatus($session, $request->input('status'));

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'session' => $this->chat->sessionToArray($session),
            ]);
        }

        return back()->with('success', 'Chat status updated.');
    }

    public function accept(Request $request, SupportChatSession $session)
    {
        if ($session->isClosed()) {
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => 'Chat is already closed.'], 422);
            }

            return back()->withErrors(['message' => 'Chat is already closed.']);
        }

        $session->update(['status' => 'in_progress']);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'session' => $this->chat->sessionToArray($session->fresh(['user'])),
            ]);
        }

        return redirect()
            ->route('admin.support-chat.show', $session)
            ->with('success', 'Chat accepted. You can reply now.');
    }

    public function widgetData(Request $request): JsonResponse
    {
        $sessions = SupportChatSession::query()
            ->with('user:id,name,email,role')
            ->withCount('messages')
            ->whereIn('status', ['open', 'in_progress'])
            ->orderByDesc('updated_at')
            ->limit(20)
            ->get()
            ->map(function (SupportChatSession $session) {
                $data = $this->chat->sessionToArray($session);
                $data['messages_count'] = $session->messages_count;
                $data['show_url'] = route('admin.support-chat.show', $session);
                $data['accept_url'] = route('admin.support-chat.accept', $session);
                $data['messages_url'] = route('admin.support-chat.messages', $session);
                $data['reply_url'] = route('admin.support-chat.reply', $session);
                $data['needs_accept'] = $session->status === 'open';
                $data['role_label'] = ucfirst(str_replace('_', ' ', (string) ($session->user?->role ?? 'user')));

                return $data;
            });

        return response()->json([
            'success' => true,
            'open_count' => $sessions->where('status', 'open')->count(),
            'active_count' => $sessions->count(),
            'sessions' => $sessions->values()->all(),
            'full_page_url' => route('admin.support-chat.index'),
        ]);
    }
}
