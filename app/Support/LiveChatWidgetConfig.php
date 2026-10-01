<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Route;

final class LiveChatWidgetConfig
{
    /** @var array<string, string> */
    private const ROLE_ROUTE_PREFIX = [
        'admin' => 'admin',
        'vendor' => 'vendor',
        'client' => 'client',
        'technician' => 'technician',
        'supervisor' => 'supervisor',
        'hr' => 'hr',
        'area_manager' => 'areamanager',
    ];

    /**
     * @return array<string, mixed>|null
     */
    public static function forUser(?User $user): ?array
    {
        if ($user === null) {
            return null;
        }

        $role = self::resolveRole($user);
        if ($role === null) {
            return null;
        }

        $prefix = self::ROLE_ROUTE_PREFIX[$role] ?? null;
        if ($prefix === null) {
            return null;
        }

        if ($role === 'admin') {
            return [
                'mode' => 'admin',
                'title' => 'Live Support Inbox',
                'subtitle' => 'Respond to users in real time',
                'widgetDataUrl' => route('admin.support-chat.widget-data'),
                'fullPageUrl' => route('admin.support-chat.index'),
                'csrf' => csrf_token(),
            ];
        }

        $widgetRoute = "{$prefix}.support-chat.widget-data";
        if (! Route::has($widgetRoute)) {
            return null;
        }

        return [
            'mode' => 'portal',
            'title' => 'Chat with Support',
            'subtitle' => 'We typically reply within minutes',
            'widgetDataUrl' => route("{$prefix}.support-chat.widget-data"),
            'messagesUrl' => route("{$prefix}.support-chat.messages"),
            'sendUrl' => route("{$prefix}.support-chat.send"),
            'fullPageUrl' => Route::has("{$prefix}.support-chat.index")
                ? route("{$prefix}.support-chat.index")
                : route("{$prefix}.help-support.index"),
            'csrf' => csrf_token(),
        ];
    }

    public static function shouldRender(?User $user): bool
    {
        return self::forUser($user) !== null;
    }

    private static function resolveRole(User $user): ?string
    {
        if (! empty($user->role) && isset(self::ROLE_ROUTE_PREFIX[$user->role])) {
            return $user->role;
        }

        foreach (array_keys(self::ROLE_ROUTE_PREFIX) as $role) {
            if ($user->hasRole($role)) {
                return $role;
            }
        }

        return null;
    }
}
