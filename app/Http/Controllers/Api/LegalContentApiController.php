<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Traits\ParsesPutMultipartFormFields;
use App\Models\CmsPage;
use App\Services\Cms\CmsPageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LegalContentApiController extends Controller
{
    use ParsesPutMultipartFormFields;

    public function __construct(
        private readonly CmsPageService $legalPages
    ) {}

    /**
     * GET /api/client/{page} or /api/vendor/{page} — public app screen payload.
     */
    public function showPublic(Request $request): JsonResponse
    {
        $audience = $this->legalPages->resolveAudience($request->route('audience'));
        $slug = $this->legalPages->resolvePageKey($request->route('page'));
        $page = $this->legalPages->findPublicBySlug($slug);

        if (! $page) {
            return ApiResponse::error('Page not found.', 404);
        }

        $locale = $this->legalPages->resolveLocale($request->query('lang', $request->query('locale')));

        return ApiResponse::success('Legal content retrieved.', $this->legalPages->toAppPayload($page, $audience, $locale));
    }

    /**
     * GET /api/admin/client/pages or /api/admin/vendor/pages
     */
    public function adminPages(Request $request): JsonResponse
    {
        $audience = $this->legalPages->resolveAudience($request->route('audience'));

        $items = $this->legalPages->allManaged()
            ->map(fn ($page) => $this->legalPages->toMobileAdminTab($page, $audience))
            ->values()
            ->all();

        return ApiResponse::success('Legal content pages retrieved.', [
            'audience' => $audience,
            'items' => $items,
        ]);
    }

    /**
     * GET /api/admin/client/{page} or /api/admin/vendor/{page}
     */
    public function adminShow(Request $request): JsonResponse
    {
        $audience = $this->legalPages->resolveAudience($request->route('audience'));
        $slug = $this->legalPages->resolvePageKey($request->route('page'));
        $page = $this->legalPages->findBySlug($slug);

        return ApiResponse::success('Legal content retrieved.', $this->legalPages->toMobileAdminForm($page, $audience));
    }

    /**
     * PUT|POST /api/admin/client/{page} or /api/admin/vendor/{page} — form-data or JSON body.
     */
    public function adminUpdate(Request $request): JsonResponse
    {
        $this->mergePutMultipartFormFields($request);

        $audience = $this->legalPages->resolveAudience($request->route('audience'));
        $slug = $this->legalPages->resolvePageKey($request->route('page'));
        $page = $this->legalPages->findBySlug($slug);
        $updated = $this->legalPages->updateFromMobileAdminForm($page, $audience, $request->all());

        return ApiResponse::success('Legal content updated.', $this->legalPages->toMobileAdminForm($updated, $audience));
    }
}
