<?php

namespace App\Services;

use App\Models\AdminNotificationBroadcast;
use App\Models\User;
use App\Notifications\AdminNotification;
use App\Support\UserNotificationAudience;
use Illuminate\Support\Collection;

class NotificationBroadcastService
{
    /**
     * @param  array{
     *   title:string,
     *   message:string,
     *   type:'all'|'role'|'users',
     *   role?:string|null,
     *   user_ids?:array<int,int>,
     *   messages_by_role?:array<string,array{title?:string,message?:string}>
     * }  $data
     */
    public static function send(?User $admin, array $data): AdminNotificationBroadcast
    {
        $users = self::resolveRecipients($data);
        $messagesByRole = self::normalizeMessagesByRole($data['messages_by_role'] ?? []);

        $broadcast = AdminNotificationBroadcast::create([
            'sent_by_user_id' => $admin?->id,
            'title' => $data['title'],
            'message' => $data['message'],
            'scope_type' => $data['type'],
            'scope_role' => $data['type'] === 'role' ? ($data['role'] ?? null) : null,
            'messages_by_role' => $messagesByRole !== [] ? $messagesByRole : null,
        ]);

        $counts = [
            'recipient_client_count' => 0,
            'recipient_technician_count' => 0,
            'recipient_supervisor_count' => 0,
            'recipient_area_manager_count' => 0,
            'recipient_hr_count' => 0,
            'recipient_admin_count' => 0,
            'recipient_other_count' => 0,
        ];

        foreach ($users as $user) {
            $audience = UserNotificationAudience::resolve($user);
            $col = UserNotificationAudience::countColumn($audience);
            if (isset($counts[$col])) {
                $counts[$col]++;
            } else {
                $counts['recipient_other_count']++;
            }

            [$title, $message] = self::resolveTitleMessageForUser(
                $user,
                $data['title'],
                $data['message'],
                $messagesByRole
            );

            $user->notify(new AdminNotification($title, $message, [
                'broadcast_id' => $broadcast->id,
                'audience_role' => $audience,
            ]));
        }

        $broadcast->update(array_merge($counts, [
            'total_recipients' => $users->count(),
        ]));

        return $broadcast->fresh();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function resolveRecipients(array $data): Collection
    {
        if ($data['type'] === 'all') {
            return User::all();
        }
        if ($data['type'] === 'role') {
            return User::role($data['role'])->get();
        }

        return User::whereIn('id', $data['user_ids'] ?? [])->get();
    }

    /**
     * @param  array<string, mixed>  $raw
     * @return array<string, array{title?:string,message?:string}>
     */
    private static function normalizeMessagesByRole(array $raw): array
    {
        $allowed = array_flip(UserNotificationAudience::PRIORITY_ROLES);
        $out = [];
        foreach ($raw as $role => $pair) {
            if (! is_string($role) || ! isset($allowed[$role]) || ! is_array($pair)) {
                continue;
            }
            $t = isset($pair['title']) ? trim((string) $pair['title']) : '';
            $m = isset($pair['message']) ? trim((string) $pair['message']) : '';
            if ($t === '' && $m === '') {
                continue;
            }
            $out[$role] = [
                'title' => $t !== '' ? $t : null,
                'message' => $m !== '' ? $m : null,
            ];
        }

        return $out;
    }

    /**
     * @param  array<string, array{title?:string|null,message?:string|null}>  $messagesByRole
     * @return array{0:string,1:string}
     */
    private static function resolveTitleMessageForUser(
        User $user,
        string $defaultTitle,
        string $defaultMessage,
        array $messagesByRole
    ): array {
        $role = UserNotificationAudience::resolve($user);
        $custom = $messagesByRole[$role] ?? null;
        if (! is_array($custom)) {
            return [$defaultTitle, $defaultMessage];
        }
        $t = $custom['title'] ?? null;
        $m = $custom['message'] ?? null;
        $title = ($t !== null && $t !== '') ? $t : $defaultTitle;
        $message = ($m !== null && $m !== '') ? $m : $defaultMessage;

        return [$title, $message];
    }

    public static function validationRules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'message' => 'required|string|max:1000',
            'type' => 'required|in:all,role,users',
            'role' => 'required_if:type,role|exists:roles,name',
            'user_ids' => 'required_if:type,users|array',
            'user_ids.*' => 'exists:users,id',
            'messages_by_role' => 'nullable|array',
            'messages_by_role.*' => 'nullable|array',
            'messages_by_role.*.title' => 'nullable|string|max:255',
            'messages_by_role.*.message' => 'nullable|string|max:1000',
        ];
    }
}
