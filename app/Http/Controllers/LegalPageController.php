<?php

namespace App\Http\Controllers;

use App\Models\CmsPage;
use App\Services\Cms\CmsPageService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LegalPageController extends Controller
{
    public function __construct(
        private readonly CmsPageService $cmsPages
    ) {}

    public function privacyPolicy(Request $request): View
    {
        return $this->renderPage(CmsPage::SLUG_PRIVACY, $request);
    }

    public function terms(Request $request): View
    {
        return $this->renderPage(CmsPage::SLUG_TERMS, $request);
    }

    public function contact(Request $request): View
    {
        return $this->renderPage(CmsPage::SLUG_CONTACT, $request);
    }

    private function renderPage(string $slug, Request $request): View
    {
        $locale = $this->cmsPages->resolveLocale($request->query('lang'));
        $audience = $this->cmsPages->resolveAudience($request->query('audience', CmsPage::AUDIENCE_CLIENT));
        $page = $this->cmsPages->findPublicBySlug($slug) ?? $this->cmsPages->findBySlug($slug);
        $appPayload = $this->cmsPages->toAppPayload($page, $audience, $locale);

        return view('legal.cms-page', [
            'page' => $page,
            'audience' => $audience,
            'locale' => $locale,
            'payload' => $appPayload,
        ]);
    }
}
