<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Cms\CmsPageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CmsPageController extends Controller
{
    public function __construct(
        private readonly CmsPageService $cmsPages
    ) {
        $this->middleware('role:admin');
    }

    public function index(): View
    {
        return view('admin.cms-pages.index', [
            'pages' => $this->cmsPages->allManaged(),
        ]);
    }

    public function edit(string $slug): View
    {
        $page = $this->cmsPages->findBySlug($slug);

        return view('admin.cms-pages.edit', [
            'page' => $page,
            'locales' => CmsPageService::SUGGESTED_LOCALES,
        ]);
    }

    public function update(Request $request, string $slug): RedirectResponse
    {
        $page = $this->cmsPages->findBySlug($slug);
        $this->cmsPages->update($page, $request->all());

        return redirect()
            ->route('admin.cms-pages.edit', $page->slug)
            ->with('success', $page->label.' updated successfully.');
    }
}
