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
     * Optional ?lang=en|ar|ur (default en) selects which language's content to read.
     */
    public function adminShow(Request $request): JsonResponse
    {
        $audience = $this->legalPages->resolveAudience($request->route('audience'));
        $slug = $this->legalPages->resolvePageKey($request->route('page'));
        $page = $this->legalPages->findBySlug($slug);
        $locale = $this->legalPages->resolveLocale($request->input('lang', $request->input('locale')));

        return ApiResponse::success('Legal content retrieved.', $this->legalPages->toMobileAdminForm($page, $audience, $locale));
    }

    /**
     * PUT|POST /api/admin/client/{page} or /api/admin/vendor/{page} — form-data or JSON body.
     * Optional lang (query or body, en|ar|ur, default en) selects which language's content this save updates.
     */
    public function adminUpdate(Request $request): JsonResponse
    {
        $this->mergePutMultipartFormFields($request);

        $audience = $this->legalPages->resolveAudience($request->route('audience'));
        $slug = $this->legalPages->resolvePageKey($request->route('page'));
        $page = $this->legalPages->findBySlug($slug);
        $locale = $this->legalPages->resolveLocale($request->input('lang', $request->input('locale')));
        $updated = $this->legalPages->updateFromMobileAdminForm($page, $audience, $request->all(), $locale);

        return ApiResponse::success('Legal content updated.', $this->legalPages->toMobileAdminForm($updated, $audience, $locale));
    }
}
