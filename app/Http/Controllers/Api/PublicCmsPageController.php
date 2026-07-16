<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\CmsPage;
use App\Services\Cms\CmsPageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicCmsPageController extends Controller
{
    public function __construct(
        private readonly CmsPageService $cmsPages
    ) {}

    public function index(): JsonResponse
    {
        $items = $this->cmsPages->allManaged()
            ->filter(fn ($page) => $page->is_active)
            ->map(fn ($page) => [
                'slug' => $page->slug,
                'label' => $page->label,
                'audiences' => CmsPage::AUDIENCES,
            ])
            ->values()
            ->all();

        return ApiResponse::success('CMS pages retrieved.', [
            'items' => $items,
            'suggested_audiences' => CmsPage::AUDIENCES,
            'suggested_locales' => CmsPageService::SUGGESTED_LOCALES,
        ]);
    }

    public function show(Request $request, string $slug): JsonResponse
    {
        $page = $this->cmsPages->findPublicBySlug($slug);
        if (! $page) {
            return ApiResponse::error('Page not found.', 404);
        }

        $audience = $this->cmsPages->resolveAudience($request->query('audience'));
        $locale = $this->cmsPages->resolveLocale($request->query('lang', $request->query('locale')));

        return ApiResponse::success('CMS page retrieved.', $this->cmsPages->toAppPayload($page, $audience, $locale));
    }
}
