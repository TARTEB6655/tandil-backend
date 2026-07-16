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

    /**
     * @return array<string, mixed>
     */
    public function toAdminPayload(CmsPage $page): array
    {
        return [
            'slug' => $page->slug,
            'label' => $page->label,
            'is_active' => $page->is_active,
            'translations' => $page->translations ?? [],
            'contact_details' => $page->contact_details ?? [],
            'suggested_locales' => self::SUGGESTED_LOCALES,
            'updated_at' => $page->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toPublicPayload(CmsPage $page): array
    {
        $payload = [
            'slug' => $page->slug,
            'label' => $page->label,
            'translations' => $page->translations ?? [],
        ];

        if ($page->isContactPage()) {
            $payload['contact_details'] = $this->normalizedContactDetails($page->contact_details ?? []);
        }

        return $payload;
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
            'translations.*.title' => 'nullable|string|max:500',
            'translations.*.body' => 'nullable|string|max:50000',
            'contact_details' => 'nullable|array',
            'contact_details.phone' => 'nullable|string|max:50',
            'contact_details.whatsapp' => 'nullable|string|max:50',
            'contact_details.email' => 'nullable|email|max:255',
            'contact_details.working_hours' => 'nullable|array',
            'contact_details.working_hours.*' => 'nullable|string|max:1000',
            'contact_details.service_areas' => 'nullable|array',
            'contact_details.service_areas.*' => 'nullable|string|max:2000',
        ])->validate();

        if ($page->isContactPage() && empty($validated['contact_details'])) {
            throw ValidationException::withMessages([
                'contact_details' => 'Contact details are required for the Contact Us page.',
            ]);
        }

        if (! $page->isContactPage()) {
            $validated['contact_details'] = null;
        }

        $page->fill([
            'translations' => $this->cleanTranslations($validated['translations']),
            'contact_details' => $page->isContactPage()
                ? $this->cleanContactDetails($validated['contact_details'] ?? [])
                : null,
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
    public function contactForHelpCenter(?string $locale = null): array
    {
        $page = $this->findPublicBySlug(CmsPage::SLUG_CONTACT);
        if (! $page) {
            return $this->legacyContactDefaults();
        }

        $contact = $this->normalizedContactDetails($page->contact_details ?? []);
        $locale = $locale ?: app()->getLocale();
        $translation = $page->translations[$locale] ?? $page->translations['en'] ?? [];

        return [
            'phone' => $contact['phone'] ?? '',
            'whatsapp' => $contact['whatsapp'] ?? null,
            'email' => $contact['email'] ?? '',
            'address' => $translation['body'] ?? null,
            'support_hours' => $contact['working_hours'][$locale]
                ?? $contact['working_hours']['en']
                ?? '',
            'service_areas' => $contact['service_areas'][$locale]
                ?? $contact['service_areas']['en']
                ?? null,
        ];
    }

    /**
     * @param  array<string, array<string, mixed>>  $translations
     * @return array<string, array<string, string>>
     */
    private function cleanTranslations(array $translations): array
    {
        $clean = [];
        foreach ($translations as $locale => $fields) {
            if (! is_string($locale) || ! is_array($fields)) {
                continue;
            }
            $locale = trim($locale);
            if ($locale === '') {
                continue;
            }
            $title = trim((string) ($fields['title'] ?? ''));
            $body = trim((string) ($fields['body'] ?? ''));
            if ($title === '' && $body === '') {
                continue;
            }
            $clean[$locale] = [
                'title' => $title,
                'body' => $body,
            ];
        }

        return $clean;
    }

    /**
     * @param  array<string, mixed>  $contact
     * @return array<string, mixed>
     */
    private function cleanContactDetails(array $contact): array
    {
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

        return [
            'phone' => trim((string) ($contact['phone'] ?? '')),
            'whatsapp' => trim((string) ($contact['whatsapp'] ?? '')),
            'email' => trim((string) ($contact['email'] ?? '')),
            'working_hours' => $workingHours,
            'service_areas' => $serviceAreas,
        ];
    }

    /**
     * @param  array<string, mixed>  $contact
     * @return array<string, mixed>
     */
    private function normalizedContactDetails(array $contact): array
    {
        return $this->cleanContactDetails($contact);
    }

    private function syncLegacySettings(CmsPage $page): void
    {
        if (! $page->isContactPage()) {
            return;
        }

        $contact = $this->normalizedContactDetails($page->contact_details ?? []);
        if ($contact['phone'] !== '') {
            Setting::set('contact_phone', $contact['phone'], 'text', 'general');
        }
        if ($contact['email'] !== '') {
            Setting::set('contact_email', $contact['email'], 'text', 'general');
        }
        $hours = $contact['working_hours']['en'] ?? reset($contact['working_hours']) ?: '';
        if ($hours !== '') {
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
     * @return list<array{slug: string, label: string, translations: array<string, array<string, string>>, contact_details: ?array<string, mixed>}>
     */
    private function defaultPages(): array
    {
        $privacyBody = <<<'HTML'
<p><strong>Effective Date:</strong> 2026</p>
<p>Tandil respects your privacy and is committed to protecting your personal information. By using the Tandil application, you agree to the collection and use of information in accordance with this Privacy Policy.</p>
<p>We may collect your name, email, phone, delivery address, location, order history, payment-related information, and device usage data. We use this information to process orders, provide support, improve the app, send notifications, and maintain security.</p>
<p>For questions, contact us at <a href="mailto:info@tandil.ae">info@tandil.ae</a> or using the details on our Contact Us page.</p>
HTML;

        $termsBody = <<<'HTML'
<p><strong>Effective Date:</strong> 2026</p>
<p>By accessing or using Tandil, you agree to these Terms &amp; Conditions. You must provide accurate account information, use the platform lawfully, and comply with applicable regulations.</p>
<p>Orders, payments, deliveries, refunds, and vendor marketplace rules are governed by the policies shown in the app at checkout and in your account.</p>
<p>We may update these terms from time to time. Continued use of the service means you accept the updated terms.</p>
HTML;

        $contactBody = <<<'HTML'
<p>Reach our support team using the phone, WhatsApp, or email shown on this page. We are happy to help with orders, subscriptions, vendor onboarding, and general questions.</p>
HTML;

        return [
            [
                'slug' => CmsPage::SLUG_PRIVACY,
                'label' => 'Privacy Policy',
                'translations' => [
                    'en' => ['title' => 'Privacy Policy', 'body' => $privacyBody],
                ],
                'contact_details' => null,
            ],
            [
                'slug' => CmsPage::SLUG_TERMS,
                'label' => 'Terms & Conditions',
                'translations' => [
                    'en' => ['title' => 'Terms & Conditions', 'body' => $termsBody],
                ],
                'contact_details' => null,
            ],
            [
                'slug' => CmsPage::SLUG_CONTACT,
                'label' => 'Contact Us',
                'translations' => [
                    'en' => ['title' => 'Contact Us', 'body' => $contactBody],
                ],
                'contact_details' => [
                    'phone' => Setting::get('contact_phone', '+971 50 000 0000'),
                    'whatsapp' => Setting::get('contact_whatsapp', '+971 50 000 0000'),
                    'email' => Setting::get('contact_email', 'support@tandil.com'),
                    'working_hours' => [
                        'en' => Setting::get('support_hours', 'Mon–Sat, 9:00 AM – 6:00 PM'),
                    ],
                    'service_areas' => [
                        'en' => 'United Arab Emirates',
                    ],
                ],
            ],
        ];
    }
}
