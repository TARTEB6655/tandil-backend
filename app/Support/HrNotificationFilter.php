<?php

namespace App\Support;

use App\Models\User;
use App\Notifications\AdminNotification;
use App\Notifications\TipPublishedNotification;
use App\Notifications\TipsNotification;
class HrNotificationFilter
{
    public static function apply($query)
    {
        return $query->where(function ($outer) {
            $outer
                ->whereIn('type', [
                    TipPublishedNotification::class,
                    TipsNotification::class,
                ])
                ->orWhere(function ($admin) {
                    $admin->where('type', AdminNotification::class)
                        ->where(function ($meta) {
                            $meta
                                ->whereIn('data->meta->type', [
                                    'hr_leave_request',
                                    'hr_visit_completed',
                                ])
                                ->orWhere(function ($supportReply) {
                                    $supportReply
                                        ->where('data->meta->entity', 'support_ticket')
                                        ->where('data->meta->action', 'open_ticket_reply');
                                });
                        });
                });
        });
    }

    public static function forUser(User $user)
    {
        return self::apply($user->notifications());
    }

    public static function unreadForUser(User $user)
    {
        return self::apply($user->unreadNotifications());
    }
}

