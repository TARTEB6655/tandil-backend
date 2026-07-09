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
            return back()->withErrors(['message' => 'This chat is closed.'])->withInput();
        }

        try {
            $this->chat->sendMessage(
                $session,
                $request->user(),
                $request->input('message'),
                true
            );
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['message' => $e->getMessage()])->withInput();
        }

        return back()->with('success', 'Reply sent to vendor.');
    }

    public function updateStatus(Request $request, SupportChatSession $session)
    {
        $request->validate(['status' => 'required|string|in:open,in_progress,resolved,closed']);

        $session->update(['status' => $request->input('status')]);

        return back()->with('success', 'Chat status updated.');
    }
}
