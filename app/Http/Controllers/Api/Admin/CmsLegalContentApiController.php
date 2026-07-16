<?php

namespace App\Http\Controllers\Api\Admin;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\Cms\CmsPageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Mobile admin "Legal & Contact Content" screen — flat form fields, multipart/form-data or x-www-form-urlencoded.
 */
class CmsLegalContentApiController extends Controller
{
    public function __construct(
        private readonly CmsPageService $cmsPages
    ) {}

    /**
     * GET /api/admin/cms/legal-content/pages?audience=client|vendor
     */
    public function pages(Request $request): JsonResponse
    {
        $audience = $this->cmsPages->resolveAudience($request->query('audience'));

        $items = $this->cmsPages->allManaged()
            ->map(fn ($page) => $this->cmsPages->toMobileAdminTab($page, $audience))
            ->values()
            ->all();

        return ApiResponse::success('Legal content pages retrieved.', [
            'audience' => $audience,
            'items' => $items,
        ]);
    }

    /**
     * GET /api/admin/cms/legal-content?audience=client|vendor&page=contact-us|terms|privacy
     */
    public function show(Request $request): JsonResponse
    {
        $audience = $this->cmsPages->resolveAudience($request->query('audience'));
        $slug = $this->cmsPages->resolvePageKey($request->query('page'));
        $page = $this->cmsPages->findBySlug($slug);

        return ApiResponse::success('Legal content retrieved.', $this->cmsPages->toMobileAdminForm($page, $audience));
    }

    /**
     * PUT /api/admin/cms/legal-content — send fields as form-data (not JSON body).
     */
    public function update(Request $request): JsonResponse
    {
        $audience = $this->cmsPages->resolveAudience($request->input('audience'));
        $slug = $this->cmsPages->resolvePageKey($request->input('page'));
        $page = $this->cmsPages->findBySlug($slug);
        $updated = $this->cmsPages->updateFromMobileAdminForm($page, $audience, $request->all());

        return ApiResponse::success('Legal content updated.', $this->cmsPages->toMobileAdminForm($updated, $audience));
    }
}
