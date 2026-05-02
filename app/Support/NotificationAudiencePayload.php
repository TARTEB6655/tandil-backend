<?php

namespace App\Support;

use App\Models\User;

/**
 * Adds audience_role to every database notification payload for filtering and analytics.
 */
final class NotificationAudiencePayload
{
    public static function merge($notifiable, array $payload): array
    {
        if (! $notifiable instanceof User) {
            return $payload;
        }

        return array_merge($payload, [
            'audience_role' => UserNotificationAudience::resolve($notifiable),
        ]);
    }
}
