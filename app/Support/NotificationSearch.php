<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

final class NotificationSearch
{
    public static function apply(Builder $query, string $search): Builder
    {
        $term = trim($search);
        if ($term === '') {
            return $query;
        }

        $like = '%'.$term.'%';

        if (Schema::hasColumn('notifications', 'search_text')) {
            return $query->where(function (Builder $group) use ($like) {
                $group->where(function (Builder $indexed) use ($like) {
                    $indexed->whereNotNull('search_text')
                        ->where('search_text', '!=', '')
                        ->where('search_text', 'like', $like);
                })->orWhere('data', 'like', $like);
            });
        }

        return $query->where('data', 'like', $like);
    }

    public static function buildSearchText(mixed $data): string
    {
        if (is_string($data)) {
            $decoded = json_decode($data, true);
            $data = is_array($decoded) ? $decoded : [];
        }
        if (! is_array($data)) {
            return '';
        }

        $parts = array_filter([
            $data['title'] ?? null,
            $data['message'] ?? null,
            $data['body'] ?? null,
        ], fn ($value) => is_string($value) && trim($value) !== '');

        return mb_substr(trim(implode(' ', $parts)), 0, 1000);
    }
}
