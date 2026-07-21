<?php

namespace App\Observers;

use App\Support\NotificationSearch;
use Illuminate\Notifications\DatabaseNotification;

class DatabaseNotificationObserver
{
    public function creating(DatabaseNotification $notification): void
    {
        $built = NotificationSearch::buildSearchText($this->resolvePayload($notification));
        $notification->search_text = $built !== '' ? mb_substr($built, 0, 1000) : null;
    }

    public function updating(DatabaseNotification $notification): void
    {
        if ($notification->isDirty('data')) {
            $built = NotificationSearch::buildSearchText($this->resolvePayload($notification));
            $notification->search_text = $built !== '' ? mb_substr($built, 0, 1000) : null;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function resolvePayload(DatabaseNotification $notification): array
    {
        $data = $notification->data;
        if (is_array($data) && $data !== []) {
            return $data;
        }

        $raw = $notification->getAttributes()['data'] ?? null;
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);

            return is_array($decoded) ? $decoded : [];
        }

        return is_array($data) ? $data : [];
    }
}
