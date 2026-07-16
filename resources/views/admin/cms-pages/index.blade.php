@php
    use App\Models\CmsPage;
    use App\Services\Cms\CmsPageService;

    $pageMeta = [
        CmsPage::SLUG_PRIVACY => [
            'badge' => 'Privacy screen',
            'badge_class' => 'text-indigo-600 dark:text-indigo-400',
            'summary' => 'title, subtitle, body',
        ],
        CmsPage::SLUG_TERMS => [
            'badge' => 'Terms screen',
            'badge_class' => 'text-amber-600 dark:text-amber-400',
            'summary' => 'title, effective_date, intro, sections[]',
        ],
        CmsPage::SLUG_CONTACT => [
            'badge' => 'Contact screen',
            'badge_class' => 'text-emerald-600 dark:text-emerald-400',
            'summary' => 'subtitle, hero, company, reach_us[], response_notice',
        ],
    ];
@endphp

<x-admin-layout>
    <div class="space-y-6">
        <div>
            <h1 class="text-xl font-medium text-gray-900 dark:text-gray-100">CMS Pages</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                Manage <strong>Client App</strong> and <strong>Vendor App</strong> policy screens. Each page saves separate content per audience and language — same structure as the mobile API.
            </p>
        </div>

        @if(session('success'))
            <div class="rounded-lg border border-green-200 bg-green-50 p-4 text-sm text-green-800 dark:border-green-800 dark:bg-green-900/20 dark:text-green-200">
                {{ session('success') }}
            </div>
        @endif

        <div class="rounded-xl border border-indigo-200 bg-indigo-50 dark:border-indigo-900 dark:bg-indigo-950/30 p-4 text-sm text-indigo-900 dark:text-indigo-200">
            <p class="font-medium">API mapping</p>
            <p class="mt-1 text-indigo-800 dark:text-indigo-300">
                Public app read: <code class="text-xs bg-white/70 dark:bg-gray-900 px-1.5 py-0.5 rounded">GET /api/public/cms/pages/{slug}?audience=client|vendor&lang=en</code>
            </p>
        </div>

        <div class="grid gap-4 lg:grid-cols-3">
            @foreach($pages as $page)
                @php
                    $meta = $pageMeta[$page->slug] ?? ['summary' => '', 'badge' => 'CMS page', 'badge_class' => 'text-gray-600'];
                    $payload = app(CmsPageService::class)->toAdminPayload($page);
                    $clientLocales = count($payload['translations']['client'] ?? []);
                    $vendorLocales = count($payload['translations']['vendor'] ?? []);
                    $previewRoute = match ($page->slug) {
                        CmsPage::SLUG_PRIVACY => 'legal.privacy-policy',
                        CmsPage::SLUG_TERMS => 'legal.terms',
                        default => 'legal.contact',
                    };
                @endphp
                <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-sm p-5 flex flex-col">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide {{ $meta['badge_class'] }}">{{ $meta['badge'] }}</p>
                            <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100 mt-1">{{ $page->label }}</h2>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1 font-mono">{{ $page->slug }}</p>
                        </div>
                        <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium {{ $page->is_active ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300' : 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300' }}">
                            {{ $page->is_active ? 'Active' : 'Hidden' }}
                        </span>
                    </div>

                    <div class="mt-4 flex flex-wrap gap-2">
                        <span class="inline-flex items-center rounded-full bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300 px-2.5 py-0.5 text-xs font-medium">Client App</span>
                        <span class="inline-flex items-center rounded-full bg-sky-100 text-sky-800 dark:bg-sky-900/30 dark:text-sky-300 px-2.5 py-0.5 text-xs font-medium">Vendor App</span>
                    </div>

                    <dl class="mt-4 space-y-2 text-sm text-gray-600 dark:text-gray-300">
                        <div class="flex justify-between gap-3">
                            <dt>Client languages</dt>
                            <dd class="font-medium">{{ $clientLocales }}</dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt>Vendor languages</dt>
                            <dd class="font-medium">{{ $vendorLocales }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500 dark:text-gray-400">API fields</dt>
                            <dd class="mt-1 text-xs font-mono text-gray-700 dark:text-gray-200">{{ $meta['summary'] }}</dd>
                        </div>
                    </dl>

                    <div class="mt-5 flex flex-col gap-2">
                        <a href="{{ route('admin.cms-pages.edit', $page->slug) }}"
                           class="inline-flex items-center justify-center px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700">
                            Edit Client &amp; Vendor content
                        </a>
                        <div class="grid grid-cols-2 gap-2">
                            <a href="{{ route($previewRoute, ['audience' => 'client', 'lang' => 'en']) }}" target="_blank"
                               class="inline-flex items-center justify-center px-3 py-2 border border-gray-300 dark:border-gray-600 text-sm text-gray-700 dark:text-gray-200 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700">
                                Preview Client
                            </a>
                            <a href="{{ route($previewRoute, ['audience' => 'vendor', 'lang' => 'en']) }}" target="_blank"
                               class="inline-flex items-center justify-center px-3 py-2 border border-gray-300 dark:border-gray-600 text-sm text-gray-700 dark:text-gray-200 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700">
                                Preview Vendor
                            </a>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</x-admin-layout>
