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
        $locale = $request->query('lang', app()->getLocale());
        $audience = $this->cmsPages->resolveAudience($request->query('audience', CmsPage::AUDIENCE_CLIENT));
        $page = $this->cmsPages->findPublicBySlug($slug) ?? $this->cmsPages->findBySlug($slug);
        $translations = $this->cmsPages->toAdminPayload($page)['translations'];
        $content = $translations[$audience][$locale]
            ?? $translations[$audience]['en']
            ?? reset($translations[$audience] ?? [])
            ?: ['title' => $page->label, 'body' => ''];

        $contactDetails = [];
        if ($page->isContactPage()) {
            $allContact = $this->cmsPages->toAdminPayload($page)['contact_details'];
            $contactDetails = $allContact[$audience] ?? $allContact[CmsPage::AUDIENCE_CLIENT] ?? [];
        }

        return view('legal.cms-page', [
            'title' => $content['title'] ?? $page->label,
            'body' => $content['body'] ?? $content['intro'] ?? '',
            'locale' => $locale,
            'audience' => $audience,
            'contactDetails' => $contactDetails,
        ]);
    }
}
