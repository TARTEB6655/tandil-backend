<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Emirate extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function toApiArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'is_active' => (bool) $this->is_active,
        ];
    }

    public static function makeUniqueSlug(string $name, ?string $slug = null, ?int $ignoreId = null): string
    {
        $base = Str::slug(($slug !== null && trim($slug) !== '') ? $slug : $name);
        if ($base === '') {
            $base = 'emirate';
        }

        $candidate = $base;
        $i = 1;
        while (static::query()
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->where('slug', $candidate)
            ->exists()) {
            $candidate = $base.'-'.$i;
            $i++;
        }

        return $candidate;
    }

    /** Active display names (registration still stores the emirate name). */
    public static function activeNames(): array
    {
        return static::query()->active()->orderBy('name')->pluck('name')->all();
    }

    /**
     * Resolve mobile/admin input to the canonical stored name.
     */
    public static function resolveToName(mixed $raw): ?string
    {
        $value = strtolower(trim((string) $raw));
        if ($value === '') {
            return null;
        }

        $compact = preg_replace('/[\s\-_]+/', '', $value) ?? $value;

        foreach (static::query()->get(['name', 'slug']) as $row) {
            $nameCompact = preg_replace('/[\s\-_]+/', '', strtolower($row->name)) ?? '';
            $slugCompact = preg_replace('/[\s\-_]+/', '', strtolower($row->slug)) ?? '';
            if ($compact === $nameCompact || $compact === $slugCompact) {
                return $row->name;
            }
        }

        return null;
    }
}
