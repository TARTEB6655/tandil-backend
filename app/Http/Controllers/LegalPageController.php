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
        $page = $this->cmsPages->findPublicBySlug($slug) ?? $this->cmsPages->findBySlug($slug);

        $translation = $page->translations[$locale]
            ?? $page->translations['en']
            ?? reset($page->translations)
            ?: ['title' => $page->label, 'body' => ''];

        return view('legal.cms-page', [
            'title' => $translation['title'] ?? $page->label,
            'body' => $translation['body'] ?? '',
            'locale' => $locale,
            'contactDetails' => $page->isContactPage() ? ($page->contact_details ?? []) : [],
        ]);
    }
}
