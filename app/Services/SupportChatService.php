<?php

namespace App\Services;

use App\Models\SupportChatMessage;
use App\Models\SupportChatSession;
use App\Models\User;
use App\Notifications\AdminNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class SupportChatService
{
    /**
     * Get the vendor's active chat session or create one.
     */
    public function getOrCreateSession(User $user): SupportChatSession
    {
        $session = $this->resolveActiveSession($user);

        if ($session !== null) {
            return $session;
        }

        return SupportChatSession::create([
            'user_id' => $user->id,
            'status' => 'open',
            'subject' => 'Live Chat with Admin',
        ]);
    }

    public function resolveActiveSession(User $user): ?SupportChatSession
    {
        return SupportChatSession::query()
            ->where('user_id', $user->id)
            ->whereIn('status', ['open', 'in_progress'])
            ->latest('updated_at')
            ->first();
    }

    /**
     * Active session, or the most recently updated ended session for display/history.
     */
    public function resolveDisplaySession(User $user): ?SupportChatSession
    {
        $active = $this->resolveActiveSession($user);
        if ($active !== null) {
            return $active;
        }

        return SupportChatSession::query()
            ->where('user_id', $user->id)
            ->whereIn('status', ['resolved', 'closed'])
            ->latest('updated_at')
            ->first();
    }

    public function updateSessionStatus(SupportChatSession $session, string $status): SupportChatSession
    {
        $wasOpen = ! $session->isClosed();
        $session->update(['status' => $status]);
        $session = $session->fresh(['user']);

        if ($wasOpen && $session->isClosed()) {
            $this->notifyUserSessionEnded($session, $status);
        }

        return $session;
    }

    /**
     * @return Collection<int, SupportChatMessage>
     */
    public function messagesForSession(SupportChatSession $session, ?int $afterId = null): Collection
    {
        $query = $session->messages()->with('user:id,name,role')->orderBy('id');

        if ($afterId !== null && $afterId > 0) {
            $query->where('id', '>', $afterId);
        }

        return $query->get();
    }

    public function sendMessage(
        SupportChatSession $session,
        User $sender,
        string $message,
        bool $isAdmin
    ): SupportChatMessage {
        if ($session->isClosed()) {
            throw new \InvalidArgumentException('Chat session is closed.');
        }

        $chatMessage = SupportChatMessage::create([
            'support_chat_session_id' => $session->id,
            'user_id' => $sender->id,
            'message' => $message,
            'is_admin' => $isAdmin,
        ]);

        if ($session->status === 'open') {
            $session->update(['status' => 'in_progress']);
        } else {
            $session->touch();
        }

        if ($isAdmin) {
            $this->notifyUser($session, $chatMessage);
        } else {
            $this->notifyAdmins($session, $sender, $chatMessage);
        }

        return $chatMessage->load('user:id,name,role');
    }

    /**
     * @return array<string, mixed>
     */
    public function sessionToArray(SupportChatSession $session): array
    {
        $session->loadMissing('user:id,name,email,role');

        return [
            'id' => $session->id,
            'token' => $session->token,
            'subject' => $session->subject,
            'status' => $session->status,
            'is_closed' => $session->isClosed(),
            'can_send' => ! $session->isClosed(),
            'status_label' => ucfirst(str_replace('_', ' ', (string) $session->status)),
            'user_id' => $session->user_id,
            'user_name' => $session->user?->name,
            'user_role' => $session->user?->role,
            'created_at' => $session->created_at?->toIso8601String(),
            'updated_at' => $session->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function messageToArray(SupportChatMessage $message): array
    {
        return [
            'id' => $message->id,
            'message' => $message->message,
            'is_admin' => $message->is_admin,
            'sender_name' => $message->user?->name,
            'sender_role' => $message->user?->role,
            'created_at' => $message->created_at?->toIso8601String(),
        ];
    }

    private function notifyAdmins(SupportChatSession $session, User $sender, SupportChatMessage $message): void
    {
        try {
            $roleLabel = ucfirst(str_replace('_', ' ', (string) ($sender->role ?? 'user')));
            $admins = User::role('admin')->get();
            foreach ($admins as $admin) {
                $admin->notify(new AdminNotification(
                    'Live Chat Message',
                    ($sender->name ?: $roleLabel).' sent a live chat message.',
                    [
                        'entity' => 'support_chat',
                        'session_id' => $session->id,
                        'token' => $session->token,
                        'message_id' => $message->id,
                        'action' => 'open_support_chat',
                    ]
                ));
            }
        } catch (\Throwable $e) {
            Log::warning('Support chat admin notification failed', [
                'session_id' => $session->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function notifyUserSessionEnded(SupportChatSession $session, string $status): void
    {
        try {
            $user = $session->user;
            if ($user === null) {
                return;
            }

            $endedLabel = $status === 'resolved' ? 'marked as resolved' : 'closed';
            $user->notify(new AdminNotification(
                'Live Chat Ended',
                'Support has '.$endedLabel.' this conversation. Open Live Chat to view history or start a new message.',
                [
                    'entity' => 'support_chat',
                    'session_id' => $session->id,
                    'token' => $session->token,
                    'action' => 'chat_ended',
                    'status' => $status,
                ]
            ));
        } catch (\Throwable $e) {
            Log::warning('Support chat session-ended notification failed', [
                'session_id' => $session->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function notifyUser(SupportChatSession $session, SupportChatMessage $message): void
    {
        try {
            $user = $session->user;
            if ($user === null) {
                return;
            }

            $user->notify(new AdminNotification(
                'Admin replied on Live Chat',
                'You have a new message from support.',
                [
                    'entity' => 'support_chat',
                    'session_id' => $session->id,
                    'token' => $session->token,
                    'message_id' => $message->id,
                    'action' => 'open_support_chat',
                ]
            ));
        } catch (\Throwable $e) {
            Log::warning('Support chat user notification failed', [
                'session_id' => $session->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
