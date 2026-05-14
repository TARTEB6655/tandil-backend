<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

/**
 * Example translatable content for API consumers (Spatie JSON fields).
 *
 * @property array<string, string> $title
 * @property array<string, string> $description
 */
class LocalizedArticle extends Model
{
    use HasFactory;
    use HasTranslations;

    /** @var list<string> */
    public array $translatable = ['title', 'description'];

    protected $fillable = [
        'slug',
        'title',
        'description',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * Optional: tie Spatie fallback to app config (middleware sets app locale).
     */
    public function getFallbackLocale(): ?string
    {
        return config('locales.fallback', config('app.fallback_locale'));
    }
}
