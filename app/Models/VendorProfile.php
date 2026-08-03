<?php

namespace App\Models;

use App\Enums\VendorType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VendorProfile extends Model
{
    protected $fillable = [
        'vendor_id',
        'business_name',
        'owner_name',
        'email',
        'phone',
        'trade_license_number',
        'vendor_type',
        'emirate',
        'city',
        'address',
        'google_maps_location',
        'bank_name',
        'iban',
        'account_holder_name',
        'delivery_radius',
        'operating_hours',
        'minimum_order_amount',
        'tax_vat_number',
        'logo_path',
        'profile_picture_path',
        'banner_path',
        'description',
        'social_links',
        'years_in_business',
        'terms_accepted_at',
        'onboarding_completed_at',
    ];

    protected $casts = [
        'delivery_radius' => 'decimal:2',
        'minimum_order_amount' => 'decimal:2',
        'terms_accepted_at' => 'datetime',
        'onboarding_completed_at' => 'datetime',
        'social_links' => 'array',
    ];

    protected $appends = [
        'vendor_type_label',
        'logo_url',
        'profile_picture_url',
        'banner_url',
    ];

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function getVendorTypeLabelAttribute(): ?string
    {
        if (empty($this->vendor_type)) {
            return null;
        }

        if (\Illuminate\Support\Facades\Schema::hasTable('vendor_types')) {
            $label = \App\Models\VendorType::query()->where('slug', $this->vendor_type)->value('name');
            if (is_string($label) && $label !== '') {
                return $label;
            }
        }

        return VendorType::tryFrom($this->vendor_type)?->label() ?? ucfirst($this->vendor_type);
    }

    public function getLogoUrlAttribute(): ?string
    {
        return $this->mediaUrl($this->logo_path);
    }

    public function getProfilePictureUrlAttribute(): ?string
    {
        return $this->mediaUrl($this->profile_picture_path);
    }

    public function getBannerUrlAttribute(): ?string
    {
        return $this->mediaUrl($this->banner_path);
    }

    /** @return array{facebook: ?string, instagram: ?string, twitter: ?string, website: ?string} */
    public function normalizedSocialLinks(): array
    {
        $links = is_array($this->social_links) ? $this->social_links : [];

        return [
            'facebook' => $links['facebook'] ?? null,
            'instagram' => $links['instagram'] ?? null,
            'twitter' => $links['twitter'] ?? null,
            'website' => $links['website'] ?? null,
        ];
    }

    private function mediaUrl(?string $path): ?string
    {
        if (empty($path)) {
            return null;
        }
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }
        $path = ltrim(str_replace('\\', '/', $path), '/');

        return request()->getSchemeAndHttpHost()
            ? rtrim(request()->getSchemeAndHttpHost(), '/').'/media/'.$path
            : asset('media/'.$path);
    }

    /** @return list<string> Active emirates (admin-managed), with static fallback. */
    public static function emirates(): array
    {
        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('emirates')) {
                $names = Emirate::activeNames();
                if ($names !== []) {
                    return $names;
                }
            }
        } catch (\Throwable) {
            // fall through to static list
        }

        return [
            'Abu Dhabi',
            'Dubai',
            'Sharjah',
            'Ajman',
            'Umm Al Quwain',
            'Ras Al Khaimah',
            'Fujairah',
        ];
    }
}
