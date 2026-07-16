<?php

namespace App\Http\Controllers\Api\Admin;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\CmsPage;
use App\Services\Cms\CmsPageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CmsPageApiController extends Controller
{
    public function __construct(
        private readonly CmsPageService $cmsPages
    ) {}

    public function index(): JsonResponse
    {
        $items = $this->cmsPages->allManaged()
            ->map(fn ($page) => $this->cmsPages->toAdminPayload($page))
            ->values()
            ->all();

        return ApiResponse::success('CMS pages retrieved.', [
            'items' => $items,
            'suggested_audiences' => CmsPage::AUDIENCES,
            'suggested_locales' => CmsPageService::SUGGESTED_LOCALES,
        ]);
    }

    public function show(string $slug): JsonResponse
    {
        $page = $this->cmsPages->findBySlug($slug);

        return ApiResponse::success('CMS page retrieved.', $this->cmsPages->toAdminPayload($page));
    }

    public function update(Request $request, string $slug): JsonResponse
    {
        $page = $this->cmsPages->findBySlug($slug);
        $updated = $this->cmsPages->update($page, $request->all());

        return ApiResponse::success('CMS page updated.', $this->cmsPages->toAdminPayload($updated));
    }
}
