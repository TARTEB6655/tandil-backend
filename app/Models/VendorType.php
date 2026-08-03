<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class VendorType extends Model
{
    protected $table = 'vendor_types';

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
            $base = 'type';
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

    /** Active slugs for registration validation. */
    public static function activeSlugs(): array
    {
        return static::query()->active()->orderBy('name')->pluck('slug')->all();
    }

    /** Active names for registration validation / display. */
    public static function activeNames(): array
    {
        return static::query()->active()->orderBy('name')->pluck('name')->all();
    }

    /**
     * Resolve mobile/admin input (slug or name) to the stored slug.
     */
    public static function resolveToSlug(mixed $raw): ?string
    {
        $value = strtolower(trim((string) $raw));
        if ($value === '') {
            return null;
        }

        $compact = preg_replace('/[\s\-_]+/', '', $value) ?? $value;

        $rows = static::query()->get(['id', 'name', 'slug', 'is_active']);
        foreach ($rows as $row) {
            $slugCompact = preg_replace('/[\s\-_]+/', '', strtolower($row->slug)) ?? '';
            $nameCompact = preg_replace('/[\s\-_]+/', '', strtolower($row->name)) ?? '';
            if ($compact === $slugCompact || $compact === $nameCompact) {
                return $row->slug;
            }
        }

        return null;
    }
}
