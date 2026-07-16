@php
    use App\Models\CmsPage;

    $translations = old('translations', $translations ?? []);
    $contact = old('contact_details', $contactDetails ?? []);
    $localeLabels = ['en' => 'English', 'ar' => 'Arabic', 'ur' => 'Urdu'];
    $audienceLabels = ['client' => 'Client App', 'vendor' => 'Vendor App'];
    $previewRoute = match ($page->slug) {
        CmsPage::SLUG_PRIVACY => 'legal.privacy-policy',
        CmsPage::SLUG_TERMS => 'legal.terms',
        default => 'legal.contact',
    };
    $screenLabel = match (true) {
        $page->isPrivacyPage() => 'Privacy Policy screen',
        $page->isTermsPage() => 'Terms & Conditions screen',
        default => 'Contact Us screen',
    };
@endphp

<x-admin-layout>
    <div class="space-y-6" x-data="{ activeAudience: 'client', activeLocale: 'en' }">
        <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-4">
            <div>
                <a href="{{ route('admin.cms-pages.index') }}" class="text-sm text-indigo-600 hover:text-indigo-800">← Back to CMS Pages</a>
                <h1 class="text-xl font-medium text-gray-900 dark:text-gray-100 mt-2">Edit {{ $page->label }}</h1>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">{{ $screenLabel }} — fields match the mobile API response for each audience.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a :href="'{{ route($previewRoute) }}?audience=' + activeAudience + '&lang=' + activeLocale"
                   target="_blank"
                   class="inline-flex items-center px-3 py-2 border border-gray-300 dark:border-gray-600 text-sm rounded-lg text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700">
                    Preview selected audience
                </a>
            </div>
        </div>

        <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/40 p-4 text-xs font-mono text-gray-700 dark:text-gray-300 break-all">
            API:
            <span x-text="'GET /api/' + activeAudience + '/{{ $page->slug }}?lang=' + activeLocale"></span>
        </div>

        @if(session('success'))
            <div class="rounded-lg border border-green-200 bg-green-50 p-4 text-sm text-green-800 dark:border-green-800 dark:bg-green-900/20 dark:text-green-200">
                {{ session('success') }}
            </div>
        @endif

        @if($errors->any())
            <div class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800 dark:border-red-800 dark:bg-red-900/20 dark:text-red-200">
                <ul class="list-disc pl-5 space-y-1">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('admin.cms-pages.update', $page->slug) }}" class="space-y-6">
            @csrf
            @method('PUT')

            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm p-6 space-y-6">
                <div class="grid gap-6 lg:grid-cols-2">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-3">Audience (app)</p>
                        <div class="flex flex-wrap items-center gap-3">
                            @foreach($audiences as $audience)
                                <button type="button"
                                        @click="activeAudience = '{{ $audience }}'"
                                        :class="activeAudience === '{{ $audience }}' ? 'bg-emerald-600 text-white' : 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-200'"
                                        class="px-4 py-2 rounded-lg text-sm font-medium transition-colors">
                                    {{ $audienceLabels[$audience] ?? ucfirst($audience) }}
                                </button>
                            @endforeach
                        </div>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-3">Language</p>
                        <div class="flex flex-wrap items-center gap-3">
                            @foreach($locales as $locale)
                                <button type="button"
                                        @click="activeLocale = '{{ $locale }}'"
                                        :class="activeLocale === '{{ $locale }}' ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-200'"
                                        class="px-4 py-2 rounded-lg text-sm font-medium transition-colors">
                                    {{ $localeLabels[$locale] ?? strtoupper($locale) }}
                                </button>
                            @endforeach
                        </div>
                    </div>
                </div>

                @foreach($audiences as $audience)
                    @foreach($locales as $locale)
                        @php
                            $content = $translations[$audience][$locale] ?? [];
                            $audienceContact = $contact[$audience] ?? [];
                        @endphp
                        <div x-show="activeAudience === '{{ $audience }}' && activeLocale === '{{ $locale }}'" x-cloak class="space-y-6">
                            <div class="rounded-lg border border-dashed border-gray-300 dark:border-gray-600 p-4">
                                <p class="text-sm font-medium text-gray-900 dark:text-gray-100">Screen header</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                    Maps to API: <code>title</code>
                                    @if($page->isPrivacyPage() || $page->isContactPage()), <code>subtitle</code>@endif
                                    @if($page->isTermsPage()), <code>effective_date</code>@endif
                                </p>
                                <div class="mt-4 space-y-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Title</label>
                                        <input type="text"
                                               name="translations[{{ $audience }}][{{ $locale }}][title]"
                                               value="{{ $content['title'] ?? '' }}"
                                               class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                                    </div>

                                    @if($page->isPrivacyPage())
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Subtitle</label>
                                            <input type="text"
                                                   name="translations[{{ $audience }}][{{ $locale }}][subtitle]"
                                                   value="{{ $content['subtitle'] ?? '' }}"
                                                   class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100"
                                                   placeholder="How Tandil collects, uses, and protects your information">
                                        </div>
                                    @elseif($page->isTermsPage())
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Effective date</label>
                                            <input type="text"
                                                   name="translations[{{ $audience }}][{{ $locale }}][effective_date]"
                                                   value="{{ $content['effective_date'] ?? '' }}"
                                                   class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100"
                                                   placeholder="July 9, 2026">
                                        </div>
                                    @else
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Subtitle</label>
                                            <input type="text"
                                                   name="translations[{{ $audience }}][{{ $locale }}][subtitle]"
                                                   value="{{ $content['subtitle'] ?? '' }}"
                                                   class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100"
                                                   placeholder="{{ $audience === 'vendor' ? 'We are here to help vendors' : 'We are here to help you' }}">
                                        </div>
                                    @endif
                                </div>
                            </div>

                            @if($page->isPrivacyPage())
                                <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-4 space-y-4">
                                    <p class="text-sm font-medium text-gray-900 dark:text-gray-100">Policy content card</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">Maps to API: <code>body</code></p>
                                    <textarea name="translations[{{ $audience }}][{{ $locale }}][body]"
                                              rows="14"
                                              class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 font-mono text-sm">{{ $content['body'] ?? '' }}</textarea>
                                </div>
                            @elseif($page->isTermsPage())
                                <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-4 space-y-4">
                                    <p class="text-sm font-medium text-gray-900 dark:text-gray-100">Introduction card</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">Maps to API: <code>intro</code></p>
                                    <textarea name="translations[{{ $audience }}][{{ $locale }}][intro]"
                                              rows="8"
                                              class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 font-mono text-sm">{{ $content['intro'] ?? '' }}</textarea>
                                </div>
                                <div class="space-y-4">
                                    <p class="text-sm font-medium text-gray-900 dark:text-gray-100">Numbered sections</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">Maps to API: <code>sections[]</code> with <code>number</code>, <code>title</code>, <code>body</code></p>
                                    @for($i = 0; $i < 5; $i++)
                                        @php $section = $content['sections'][$i] ?? []; @endphp
                                        <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-4 space-y-3">
                                            <p class="text-sm font-medium text-gray-700 dark:text-gray-300">Section {{ $i + 1 }}</p>
                                            <input type="text"
                                                   name="translations[{{ $audience }}][{{ $locale }}][sections][{{ $i }}][title]"
                                                   value="{{ $section['title'] ?? '' }}"
                                                   placeholder="Section title"
                                                   class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                                            <textarea name="translations[{{ $audience }}][{{ $locale }}][sections][{{ $i }}][body]"
                                                      rows="5"
                                                      placeholder="Section body"
                                                      class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 font-mono text-sm">{{ $section['body'] ?? '' }}</textarea>
                                        </div>
                                    @endfor
                                </div>
                            @else
                                <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-4 space-y-4">
                                    <p class="text-sm font-medium text-gray-900 dark:text-gray-100">Hero card (green banner)</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">Maps to API: <code>hero.title</code>, <code>hero.description</code></p>
                                    <input type="text"
                                           name="translations[{{ $audience }}][{{ $locale }}][hero_title]"
                                           value="{{ $content['hero_title'] ?? '' }}"
                                           placeholder="Get in touch with Tandil"
                                           class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                                    <textarea name="translations[{{ $audience }}][{{ $locale }}][hero_description]"
                                              rows="4"
                                              placeholder="For any questions regarding these Terms, please contact us..."
                                              class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">{{ $content['hero_description'] ?? '' }}</textarea>
                                </div>

                                <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-4 space-y-4">
                                    <p class="text-sm font-medium text-gray-900 dark:text-gray-100">Company card</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">Maps to API: <code>company.name</code>, <code>company.location</code></p>
                                    <input type="text"
                                           name="contact_details[{{ $audience }}][location][{{ $locale }}]"
                                           value="{{ $audienceContact['location'][$locale] ?? '' }}"
                                           placeholder="United Arab Emirates"
                                           class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                                </div>

                                <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-4 space-y-4">
                                    <p class="text-sm font-medium text-gray-900 dark:text-gray-100">Reach us row subtitles</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">Maps to API: <code>reach_us[].subtitle</code></p>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        @foreach(['website' => 'Website', 'email' => 'Email', 'whatsapp' => 'WhatsApp', 'phone' => 'Phone'] as $channel => $label)
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">{{ $label }} subtitle</label>
                                                <input type="text"
                                                       name="contact_details[{{ $audience }}][channel_subtitles][{{ $channel }}][{{ $locale }}]"
                                                       value="{{ $audienceContact['channel_subtitles'][$channel][$locale] ?? '' }}"
                                                       class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                                            </div>
                                        @endforeach
                                    </div>
                                </div>

                                <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-4 space-y-4">
                                    <p class="text-sm font-medium text-gray-900 dark:text-gray-100">Footer notice</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">Maps to API: <code>response_notice</code></p>
                                    <textarea name="translations[{{ $audience }}][{{ $locale }}][response_notice]"
                                              rows="3"
                                              placeholder="Our support team typically responds within 24–48 hours on business days."
                                              class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">{{ $content['response_notice'] ?? '' }}</textarea>
                                </div>
                            @endif
                        </div>
                    @endforeach
                @endforeach
            </div>

            @if($page->isContactPage())
                @foreach($audiences as $audience)
                    @php $audienceContact = $contact[$audience] ?? []; @endphp
                    <div x-show="activeAudience === '{{ $audience }}'" x-cloak class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm p-6">
                        <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">{{ $audienceLabels[$audience] ?? ucfirst($audience) }} — Reach us channels</h2>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Maps to API: <code>reach_us[]</code> values and <code>company.name</code></p>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Company name</label>
                                <input type="text" name="contact_details[{{ $audience }}][company_name]" value="{{ $audienceContact['company_name'] ?? '' }}"
                                       class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Website</label>
                                <input type="text" name="contact_details[{{ $audience }}][website]" value="{{ $audienceContact['website'] ?? '' }}"
                                       class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100"
                                       placeholder="tandil.ae">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Email</label>
                                <input type="email" name="contact_details[{{ $audience }}][email]" value="{{ $audienceContact['email'] ?? '' }}"
                                       class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">WhatsApp</label>
                                <input type="text" name="contact_details[{{ $audience }}][whatsapp]" value="{{ $audienceContact['whatsapp'] ?? '' }}"
                                       class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Phone</label>
                                <input type="text" name="contact_details[{{ $audience }}][phone]" value="{{ $audienceContact['phone'] ?? '' }}"
                                       class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                            </div>
                        </div>
                    </div>
                @endforeach
            @endif

            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm p-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <label class="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $page->is_active)) class="rounded border-gray-300 text-indigo-600">
                    Page is active (visible in client/vendor apps)
                </label>
                <button type="submit" class="px-6 py-2.5 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700">
                    Save changes
                </button>
            </div>
        </form>
    </div>
</x-admin-layout>
