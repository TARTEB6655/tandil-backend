<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\Cms\CmsPageService;
use Illuminate\Http\JsonResponse;

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
            ])
            ->values()
            ->all();

        return ApiResponse::success('CMS pages retrieved.', [
            'items' => $items,
        ]);
    }

    public function show(string $slug): JsonResponse
    {
        $page = $this->cmsPages->findPublicBySlug($slug);
        if (! $page) {
            return ApiResponse::error('Page not found.', 404);
        }

        return ApiResponse::success('CMS page retrieved.', $this->cmsPages->toPublicPayload($page));
    }
}
