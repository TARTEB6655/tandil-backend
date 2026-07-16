<?php

namespace App\Services\Cms;

use App\Models\CmsPage;
use App\Models\Setting;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class CmsPageService
{
    /** @var list<string> */
    public const SUGGESTED_LOCALES = ['en', 'ar', 'ur'];

    public function allManaged(): Collection
    {
        $this->ensureDefaults();

        $pages = CmsPage::query()
            ->whereIn('slug', CmsPage::MANAGED_SLUGS)
            ->get();

        return collect(CmsPage::MANAGED_SLUGS)
            ->map(fn (string $slug) => $pages->firstWhere('slug', $slug))
            ->filter()
            ->values();
    }

    public function findBySlug(string $slug): CmsPage
    {
        $this->ensureDefaults();

        return CmsPage::query()
            ->whereIn('slug', CmsPage::MANAGED_SLUGS)
            ->where('slug', $slug)
            ->firstOrFail();
    }

    public function findPublicBySlug(string $slug): ?CmsPage
    {
        $this->ensureDefaults();

        return CmsPage::query()
            ->whereIn('slug', CmsPage::MANAGED_SLUGS)
            ->where('slug', $slug)
            ->where('is_active', true)
            ->first();
    }

    public function resolveAudience(?string $audience): string
    {
        $audience = strtolower(trim((string) $audience));

        if ($audience === '') {
            return CmsPage::AUDIENCE_CLIENT;
        }

        if (! in_array($audience, CmsPage::AUDIENCES, true)) {
            throw ValidationException::withMessages([
                'audience' => 'Audience must be client or vendor.',
            ]);
        }

        return $audience;
    }

    public function resolveLocale(?string $locale): string
    {
        $locale = strtolower(trim((string) $locale));

        return $locale !== '' ? $locale : 'en';
    }

    /**
     * @return array<string, mixed>
     */
    public function toAdminPayload(CmsPage $page): array
    {
        return [
            'slug' => $page->slug,
            'label' => $page->label,
            'is_active' => $page->is_active,
            'translations' => $this->normalizeAudienceTranslations($page->translations ?? []),
            'contact_details' => $this->normalizeAudienceContactDetails($page->contact_details ?? []),
            'suggested_audiences' => CmsPage::AUDIENCES,
            'suggested_locales' => self::SUGGESTED_LOCALES,
            'updated_at' => $page->updated_at?->toIso8601String(),
        ];
    }

    /**
     * App-ready payload shaped for the mobile policy/contact screens.
     *
     * @return array<string, mixed>
     */
    public function toAppPayload(CmsPage $page, string $audience, ?string $locale = null): array
    {
        $audience = $this->resolveAudience($audience);
        $locale = $this->resolveLocale($locale);
        $translations = $this->normalizeAudienceTranslations($page->translations ?? []);
        $content = $this->pickLocaleContent($translations[$audience] ?? [], $locale);

        if ($page->isContactPage()) {
            return $this->buildContactAppPayload($page, $audience, $locale, $content);
        }

        if ($page->isTermsPage()) {
            return $this->buildTermsAppPayload($page, $audience, $locale, $content);
        }

        return $this->buildPrivacyAppPayload($page, $audience, $locale, $content);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(CmsPage $page, array $data): CmsPage
    {
        $validated = validator($data, [
            'is_active' => 'sometimes|boolean',
            'translations' => 'required|array',
            'translations.*' => 'array',
            'translations.*.*' => 'array',
            'translations.*.*.title' => 'nullable|string|max:500',
            'translations.*.*.subtitle' => 'nullable|string|max:1000',
            'translations.*.*.body' => 'nullable|string|max:50000',
            'translations.*.*.intro' => 'nullable|string|max:50000',
            'translations.*.*.effective_date' => 'nullable|string|max:120',
            'translations.*.*.hero_title' => 'nullable|string|max:500',
            'translations.*.*.hero_description' => 'nullable|string|max:5000',
            'translations.*.*.response_notice' => 'nullable|string|max:2000',
            'translations.*.*.sections' => 'nullable|array',
            'translations.*.*.sections.*.title' => 'nullable|string|max:500',
            'translations.*.*.sections.*.body' => 'nullable|string|max:20000',
            'contact_details' => 'nullable|array',
            'contact_details.*' => 'array',
            'contact_details.*.website' => 'nullable|string|max:255',
            'contact_details.*.email' => 'nullable|email|max:255',
            'contact_details.*.whatsapp' => 'nullable|string|max:50',
            'contact_details.*.phone' => 'nullable|string|max:50',
            'contact_details.*.company_name' => 'nullable|string|max:255',
            'contact_details.*.location' => 'nullable|array',
            'contact_details.*.location.*' => 'nullable|string|max:500',
            'contact_details.*.channel_subtitles' => 'nullable|array',
            'contact_details.*.channel_subtitles.*' => 'nullable|array',
            'contact_details.*.channel_subtitles.*.*' => 'nullable|string|max:500',
            'contact_details.*.working_hours' => 'nullable|array',
            'contact_details.*.working_hours.*' => 'nullable|string|max:1000',
            'contact_details.*.service_areas' => 'nullable|array',
            'contact_details.*.service_areas.*' => 'nullable|string|max:2000',
        ])->validate();

        $translations = $this->cleanAudienceTranslations($validated['translations']);

        if ($translations === []) {
            throw ValidationException::withMessages([
                'translations' => 'At least one audience and language must be provided.',
            ]);
        }

        $contactDetails = null;
        if ($page->isContactPage()) {
            $contactDetails = $this->cleanAudienceContactDetails($validated['contact_details'] ?? []);
            if ($contactDetails === []) {
                throw ValidationException::withMessages([
                    'contact_details' => 'Contact details are required for the Contact Us page.',
                ]);
            }
        }

        $page->fill([
            'translations' => $translations,
            'contact_details' => $contactDetails,
            'is_active' => (bool) ($validated['is_active'] ?? $page->is_active),
        ]);
        $page->save();

        $this->syncLegacySettings($page);

        return $page->fresh();
    }

    public function ensureDefaults(): void
    {
        foreach ($this->defaultPages() as $defaults) {
            CmsPage::query()->firstOrCreate(
                ['slug' => $defaults['slug']],
                [
                    'label' => $defaults['label'],
                    'translations' => $defaults['translations'],
                    'contact_details' => $defaults['contact_details'],
                    'is_active' => true,
                ]
            );
        }
    }

    /**
     * @return array{phone: string, email: string, address: ?string, support_hours: string, whatsapp: ?string, service_areas: ?string}
     */
    public function contactForHelpCenter(?string $locale = null, string $audience = CmsPage::AUDIENCE_CLIENT): array
    {
        $page = $this->findPublicBySlug(CmsPage::SLUG_CONTACT);
        if (! $page) {
            return $this->legacyContactDefaults();
        }

        $audience = $this->resolveAudience($audience);
        $locale = $this->resolveLocale($locale);
        $translations = $this->normalizeAudienceTranslations($page->translations ?? []);
        $content = $this->pickLocaleContent($translations[$audience] ?? [], $locale);
        $contact = $this->audienceContactBlock($page, $audience);

        return [
            'phone' => $contact['phone'] ?? '',
            'whatsapp' => $contact['whatsapp'] ?? null,
            'email' => $contact['email'] ?? '',
            'address' => $content['hero_description'] ?? $content['body'] ?? null,
            'support_hours' => $contact['working_hours'][$locale]
                ?? $contact['working_hours']['en']
                ?? '',
            'service_areas' => $contact['service_areas'][$locale]
                ?? $contact['service_areas']['en']
                ?? ($contact['location'][$locale] ?? $contact['location']['en'] ?? null),
        ];
    }

    /**
     * @param  array<string, array<string, mixed>>  $audienceLocales
     * @return array<string, mixed>
     */
    private function pickLocaleContent(array $audienceLocales, string $locale): array
    {
        if (isset($audienceLocales[$locale]) && is_array($audienceLocales[$locale])) {
            return $audienceLocales[$locale];
        }

        if (isset($audienceLocales['en']) && is_array($audienceLocales['en'])) {
            return $audienceLocales['en'];
        }

        $first = reset($audienceLocales);

        return is_array($first) ? $first : [];
    }

    /**
     * @param  array<string, mixed>  $content
     * @return array<string, mixed>
     */
    private function buildPrivacyAppPayload(CmsPage $page, string $audience, string $locale, array $content): array
    {
        return [
            'slug' => $page->slug,
            'audience' => $audience,
            'locale' => $locale,
            'title' => $content['title'] ?? $page->label,
            'subtitle' => $content['subtitle'] ?? '',
            'body' => $content['body'] ?? '',
        ];
    }

    /**
     * @param  array<string, mixed>  $content
     * @return array<string, mixed>
     */
    private function buildTermsAppPayload(CmsPage $page, string $audience, string $locale, array $content): array
    {
        $sections = [];
        foreach ((array) ($content['sections'] ?? []) as $index => $section) {
            if (! is_array($section)) {
                continue;
            }
            $title = trim((string) ($section['title'] ?? ''));
            $body = trim((string) ($section['body'] ?? ''));
            if ($title === '' && $body === '') {
                continue;
            }
            $sections[] = [
                'number' => $index + 1,
                'title' => $title,
                'body' => $body,
            ];
        }

        return [
            'slug' => $page->slug,
            'audience' => $audience,
            'locale' => $locale,
            'title' => $content['title'] ?? $page->label,
            'effective_date' => $content['effective_date'] ?? '',
            'intro' => $content['intro'] ?? $content['body'] ?? '',
            'sections' => $sections,
        ];
    }

    /**
     * @param  array<string, mixed>  $content
     * @return array<string, mixed>
     */
    private function buildContactAppPayload(CmsPage $page, string $audience, string $locale, array $content): array
    {
        $contact = $this->audienceContactBlock($page, $audience);

        return [
            'slug' => $page->slug,
            'audience' => $audience,
            'locale' => $locale,
            'title' => $content['title'] ?? $page->label,
            'subtitle' => $content['subtitle'] ?? '',
            'hero' => [
                'title' => $content['hero_title'] ?? 'Get in touch with Tandil',
                'description' => $content['hero_description'] ?? '',
            ],
            'company' => [
                'name' => $contact['company_name'] ?? 'Tandil',
                'location' => $contact['location'][$locale] ?? $contact['location']['en'] ?? 'United Arab Emirates',
            ],
            'reach_us' => $this->buildReachUs($contact, $locale),
            'response_notice' => $content['response_notice'] ?? '',
        ];
    }

    /**
     * @param  array<string, mixed>  $contact
     * @return list<array{type: string, label: string, value: string, subtitle: string}>
     */
    private function buildReachUs(array $contact, string $locale): array
    {
        $items = [];
        $subtitles = (array) ($contact['channel_subtitles'] ?? []);

        $channels = [
            'website' => 'WEBSITE',
            'email' => 'EMAIL',
            'whatsapp' => 'WHATSAPP',
            'phone' => 'PHONE',
        ];

        foreach ($channels as $type => $label) {
            $value = trim((string) ($contact[$type] ?? ''));
            if ($value === '') {
                continue;
            }
            $items[] = [
                'type' => $type,
                'label' => $label,
                'value' => $value,
                'subtitle' => $subtitles[$type][$locale]
                    ?? $subtitles[$type]['en']
                    ?? '',
            ];
        }

        return $items;
    }

    /**
     * @return array<string, mixed>
     */
    private function audienceContactBlock(CmsPage $page, string $audience): array
    {
        $details = $this->normalizeAudienceContactDetails($page->contact_details ?? []);

        return $details[$audience] ?? $details[CmsPage::AUDIENCE_CLIENT] ?? [];
    }

    /**
     * @param  array<string, mixed>  $translations
     * @return array<string, array<string, array<string, mixed>>>
     */
    private function normalizeAudienceTranslations(array $translations): array
    {
        if ($this->isLegacyLocaleTranslations($translations)) {
            return [
                CmsPage::AUDIENCE_CLIENT => $this->cleanLocaleTranslations($translations),
                CmsPage::AUDIENCE_VENDOR => $this->cleanLocaleTranslations($translations),
            ];
        }

        $normalized = [];
        foreach (CmsPage::AUDIENCES as $audience) {
            if (! isset($translations[$audience]) || ! is_array($translations[$audience])) {
                continue;
            }
            $clean = $this->cleanLocaleTranslations($translations[$audience]);
            if ($clean !== []) {
                $normalized[$audience] = $clean;
            }
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $contact
     * @return array<string, array<string, mixed>>
     */
    private function normalizeAudienceContactDetails(array $contact): array
    {
        if ($this->isLegacyFlatContactDetails($contact)) {
            $clean = $this->cleanContactDetails($contact);

            return [
                CmsPage::AUDIENCE_CLIENT => $clean,
                CmsPage::AUDIENCE_VENDOR => $clean,
            ];
        }

        $normalized = [];
        foreach (CmsPage::AUDIENCES as $audience) {
            if (! isset($contact[$audience]) || ! is_array($contact[$audience])) {
                continue;
            }
            $clean = $this->cleanContactDetails($contact[$audience]);
            if ($clean !== []) {
                $normalized[$audience] = $clean;
            }
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $translations
     */
    private function isLegacyLocaleTranslations(array $translations): bool
    {
        foreach (array_keys($translations) as $key) {
            if (in_array($key, self::SUGGESTED_LOCALES, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $contact
     */
    private function isLegacyFlatContactDetails(array $contact): bool
    {
        return isset($contact['phone'])
            || isset($contact['email'])
            || isset($contact['whatsapp'])
            || isset($contact['website']);
    }

    /**
     * @param  array<string, mixed>  $translations
     * @return array<string, array<string, mixed>>
     */
    private function cleanAudienceTranslations(array $translations): array
    {
        $clean = [];
        foreach ($translations as $audience => $locales) {
            if (! in_array($audience, CmsPage::AUDIENCES, true) || ! is_array($locales)) {
                continue;
            }
            $localeClean = $this->cleanLocaleTranslations($locales);
            if ($localeClean !== []) {
                $clean[$audience] = $localeClean;
            }
        }

        return $clean;
    }

    /**
     * @param  array<string, mixed>  $locales
     * @return array<string, array<string, mixed>>
     */
    private function cleanLocaleTranslations(array $locales): array
    {
        $clean = [];
        foreach ($locales as $locale => $fields) {
            if (! is_string($locale) || ! is_array($fields)) {
                continue;
            }
            $locale = trim($locale);
            if ($locale === '') {
                continue;
            }

            $entry = [];
            foreach (['title', 'subtitle', 'body', 'intro', 'effective_date', 'hero_title', 'hero_description', 'response_notice'] as $field) {
                $value = trim((string) ($fields[$field] ?? ''));
                if ($value !== '') {
                    $entry[$field] = $value;
                }
            }

            $sections = [];
            foreach ((array) ($fields['sections'] ?? []) as $section) {
                if (! is_array($section)) {
                    continue;
                }
                $title = trim((string) ($section['title'] ?? ''));
                $body = trim((string) ($section['body'] ?? ''));
                if ($title === '' && $body === '') {
                    continue;
                }
                $sections[] = ['title' => $title, 'body' => $body];
            }
            if ($sections !== []) {
                $entry['sections'] = $sections;
            }

            if ($entry !== []) {
                $clean[$locale] = $entry;
            }
        }

        return $clean;
    }

    /**
     * @param  array<string, mixed>  $contacts
     * @return array<string, array<string, mixed>>
     */
    private function cleanAudienceContactDetails(array $contacts): array
    {
        $clean = [];
        foreach ($contacts as $audience => $contact) {
            if (! in_array($audience, CmsPage::AUDIENCES, true) || ! is_array($contact)) {
                continue;
            }
            $normalized = $this->cleanContactDetails($contact);
            if ($normalized !== []) {
                $clean[$audience] = $normalized;
            }
        }

        return $clean;
    }

    /**
     * @param  array<string, mixed>  $contact
     * @return array<string, mixed>
     */
    private function cleanContactDetails(array $contact): array
    {
        $location = [];
        foreach ((array) ($contact['location'] ?? []) as $locale => $value) {
            if (is_string($locale) && is_string($value) && trim($value) !== '') {
                $location[$locale] = trim($value);
            }
        }

        $workingHours = [];
        foreach ((array) ($contact['working_hours'] ?? []) as $locale => $value) {
            if (is_string($locale) && is_string($value) && trim($value) !== '') {
                $workingHours[$locale] = trim($value);
            }
        }

        $serviceAreas = [];
        foreach ((array) ($contact['service_areas'] ?? []) as $locale => $value) {
            if (is_string($locale) && is_string($value) && trim($value) !== '') {
                $serviceAreas[$locale] = trim($value);
            }
        }

        $channelSubtitles = [];
        foreach ((array) ($contact['channel_subtitles'] ?? []) as $channel => $locales) {
            if (! is_string($channel) || ! is_array($locales)) {
                continue;
            }
            foreach ($locales as $locale => $value) {
                if (is_string($locale) && is_string($value) && trim($value) !== '') {
                    $channelSubtitles[$channel][$locale] = trim($value);
                }
            }
        }

        $clean = array_filter([
            'website' => trim((string) ($contact['website'] ?? '')),
            'email' => trim((string) ($contact['email'] ?? '')),
            'whatsapp' => trim((string) ($contact['whatsapp'] ?? '')),
            'phone' => trim((string) ($contact['phone'] ?? '')),
            'company_name' => trim((string) ($contact['company_name'] ?? '')),
            'location' => $location,
            'working_hours' => $workingHours,
            'service_areas' => $serviceAreas,
            'channel_subtitles' => $channelSubtitles,
        ], fn ($value) => $value !== [] && $value !== '');

        return $clean;
    }

    private function syncLegacySettings(CmsPage $page): void
    {
        if (! $page->isContactPage()) {
            return;
        }

        $contact = $this->audienceContactBlock($page, CmsPage::AUDIENCE_CLIENT);
        if (($contact['phone'] ?? '') !== '') {
            Setting::set('contact_phone', $contact['phone'], 'text', 'general');
        }
        if (($contact['email'] ?? '') !== '') {
            Setting::set('contact_email', $contact['email'], 'text', 'general');
        }
        $workingHours = $contact['working_hours'] ?? [];
        $hours = $workingHours['en'] ?? (is_array($workingHours) && $workingHours !== [] ? reset($workingHours) : '');
        if (is_string($hours) && $hours !== '') {
            Setting::set('support_hours', $hours, 'text', 'general');
        }
    }

    /**
     * @return array{phone: string, email: string, address: null, support_hours: string, whatsapp: null, service_areas: null}
     */
    private function legacyContactDefaults(): array
    {
        return [
            'phone' => Setting::get('contact_phone', '+971 50 000 0000'),
            'whatsapp' => null,
            'email' => Setting::get('contact_email', 'support@tandil.com'),
            'address' => Setting::get('contact_address') ?: null,
            'support_hours' => Setting::get('support_hours', '24/7 Customer Support'),
            'service_areas' => null,
        ];
    }

    /**
     * @return list<array{slug: string, label: string, translations: array<string, array<string, array<string, mixed>>>, contact_details: ?array<string, array<string, mixed>>}>
     */
    private function defaultPages(): array
    {
        $privacyBody = <<<'HTML'
<p>We respect your privacy. Tandil collects only the information needed to provide services, process orders, and improve your experience (such as name, contact details, delivery address, and payment status).</p>
<p>We do not sell your personal data. Data may be shared with payment providers and delivery partners only to fulfil your orders. You can delete your account at any time from Profile → Delete Account in the app.</p>
<p>For the full policy, refer to updates published by Tandil or contact support@tandil.com.</p>
HTML;

        $termsIntro = <<<'HTML'
<p>Welcome to Tandil. These Terms and Conditions ("Terms") govern your access to and use of the Tandil website, mobile application, and all related services (collectively, the "Platform"). By accessing or using Tandil, you confirm that you have read, understood, and agree to be legally bound by these Terms. If you do not agree with any part of these Terms, you must not use the Platform.</p>
HTML;

        $aboutTandil = 'Tandil is a technology platform that connects customers with qualified agricultural professionals and service providers for residential, commercial, and agricultural properties across the United Arab Emirates. Services may include, but are not limited to, landscaping, irrigation, maintenance, and related property services. Availability of services depends on location, technician availability, weather conditions, and operational capacity.';

        $contactHero = 'For any questions regarding these Terms, please contact us using any of the options below.';

        $sharedContact = [
            'website' => 'tandil.ae',
            'email' => 'info@tandil.ae',
            'whatsapp' => '+971 569206959',
            'phone' => Setting::get('contact_phone', '+971 569206959'),
            'company_name' => 'Tandil',
            'location' => ['en' => 'United Arab Emirates'],
            'channel_subtitles' => [
                'website' => ['en' => 'Visit our official site'],
                'email' => ['en' => 'Send us your questions'],
                'whatsapp' => ['en' => 'Chat with our team'],
            ],
            'working_hours' => ['en' => Setting::get('support_hours', 'Mon–Sat, 9:00 AM – 6:00 PM')],
            'service_areas' => ['en' => 'United Arab Emirates'],
        ];

        $privacyLocale = fn (string $subtitle): array => [
            'title' => 'Privacy Policy',
            'subtitle' => $subtitle,
            'body' => $privacyBody,
        ];

        $termsLocale = fn (): array => [
            'title' => 'Terms & Conditions',
            'effective_date' => 'July 9, 2026',
            'intro' => $termsIntro,
            'sections' => [
                ['title' => 'About Tandil', 'body' => $aboutTandil],
            ],
        ];

        $contactLocale = fn (string $subtitle, string $responseNotice): array => [
            'title' => 'Contact Us',
            'subtitle' => $subtitle,
            'hero_title' => 'Get in touch with Tandil',
            'hero_description' => $contactHero,
            'response_notice' => $responseNotice,
        ];

        return [
            [
                'slug' => CmsPage::SLUG_PRIVACY,
                'label' => 'Privacy Policy',
                'translations' => [
                    CmsPage::AUDIENCE_CLIENT => [
                        'en' => $privacyLocale('How Tandil collects, uses, and protects your information'),
                    ],
                    CmsPage::AUDIENCE_VENDOR => [
                        'en' => $privacyLocale('How Tandil collects, uses, and protects vendor information'),
                    ],
                ],
                'contact_details' => null,
            ],
            [
                'slug' => CmsPage::SLUG_TERMS,
                'label' => 'Terms & Conditions',
                'translations' => [
                    CmsPage::AUDIENCE_CLIENT => ['en' => $termsLocale()],
                    CmsPage::AUDIENCE_VENDOR => ['en' => $termsLocale()],
                ],
                'contact_details' => null,
            ],
            [
                'slug' => CmsPage::SLUG_CONTACT,
                'label' => 'Contact Us',
                'translations' => [
                    CmsPage::AUDIENCE_CLIENT => [
                        'en' => $contactLocale(
                            'We are here to help you',
                            'Our support team typically responds within 24–48 hours on business days.'
                        ),
                    ],
                    CmsPage::AUDIENCE_VENDOR => [
                        'en' => $contactLocale(
                            'We are here to help vendors',
                            'Our support team typically responds within 24–48 hours on business days.'
                        ),
                    ],
                ],
                'contact_details' => [
                    CmsPage::AUDIENCE_CLIENT => $sharedContact,
                    CmsPage::AUDIENCE_VENDOR => $sharedContact,
                ],
            ],
        ];
    }
}
