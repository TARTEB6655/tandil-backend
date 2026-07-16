<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CmsPage extends Model
{
    public const SLUG_PRIVACY = 'privacy-policy';

    public const SLUG_TERMS = 'terms-and-conditions';

    public const SLUG_CONTACT = 'contact-us';

    /** @var list<string> */
    public const MANAGED_SLUGS = [
        self::SLUG_PRIVACY,
        self::SLUG_TERMS,
        self::SLUG_CONTACT,
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
}
