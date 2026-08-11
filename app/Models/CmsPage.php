<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CmsPage extends Model
{
    public const SLUG_PRIVACY = 'privacy-policy';

    public const SLUG_TERMS = 'terms-and-conditions';

    public const SLUG_CONTACT = 'contact-us';

    public const SLUG_SUPPORT = 'support';

    public const AUDIENCE_CLIENT = 'client';

    public const AUDIENCE_VENDOR = 'vendor';

    /** @var list<string> */
    public const MANAGED_SLUGS = [
        self::SLUG_PRIVACY,
        self::SLUG_TERMS,
        self::SLUG_CONTACT,
        self::SLUG_SUPPORT,
    ];

    /** @var list<string> */
    public const AUDIENCES = [
        self::AUDIENCE_CLIENT,
        self::AUDIENCE_VENDOR,
    ];

    protected $fillable = [
        'slug',
        'label',
        'translations',
        'contact_details',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'translations' => 'array',
            'contact_details' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function isContactPage(): bool
    {
        return $this->slug === self::SLUG_CONTACT;
    }

    public function isTermsPage(): bool
    {
        return $this->slug === self::SLUG_TERMS;
    }

    public function isPrivacyPage(): bool
    {
        return $this->slug === self::SLUG_PRIVACY;
    }

    public function isSupportPage(): bool
    {
        return $this->slug === self::SLUG_SUPPORT;
    }
}
