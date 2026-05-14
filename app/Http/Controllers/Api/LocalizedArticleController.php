<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\LocalizedArticle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Demonstrates Spatie translatable + locale middleware.
 *
 * Resolved strings follow app()->getLocale() (set from lang, locale, Accept-Language, etc.).
 */
class LocalizedArticleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $articles = LocalizedArticle::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return ApiResponse::success('Articles retrieved.', [
            'locale' => app()->getLocale(),
            'fallback_locale' => config('locales.fallback'),
            'available_locales' => config('locales.supported'),
            'rtl' => in_array(app()->getLocale(), config('locales.rtl', []), true),
            'items' => $articles->map(fn (LocalizedArticle $a) => $this->serializeArticle($a, $request))->values()->all(),
        ]);
    }

    public function show(Request $request, string $slug): JsonResponse
    {
        $article = LocalizedArticle::query()
            ->where('slug', $slug)
            ->where('is_active', true)
            ->firstOrFail();

        return ApiResponse::success('Article retrieved.', $this->serializeArticle($article, $request));
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeArticle(LocalizedArticle $article, Request $request): array
    {
        $locale = app()->getLocale();
        $includeTranslations = $request->boolean('include_translations');

        $title = $article->title;
        $description = $article->description;

        $usedFallbackFor = [];
        foreach (['title', 'description'] as $field) {
            $withoutFallback = $article->getTranslation($field, $locale, false);
            $withFallback = $article->getTranslation($field, $locale, true);
            $missing = ($withoutFallback === null || $withoutFallback === '');
            if ($missing && $withFallback !== null && $withFallback !== '') {
                $usedFallbackFor[] = $field;
            }
        }

        $payload = [
            'id' => $article->id,
            'slug' => $article->slug,
            'locale' => $locale,
            'title' => $title,
            'description' => $description,
            'used_fallback_for' => array_values(array_unique($usedFallbackFor)),
        ];

        if ($includeTranslations) {
            $payload['translations'] = [
                'title' => $article->getTranslations('title'),
                'description' => $article->getTranslations('description'),
            ];
        }

        return $payload;
    }
}
