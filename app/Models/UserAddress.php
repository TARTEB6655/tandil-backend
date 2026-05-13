<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserAddress extends Model
{
    protected $table = 'user_addresses';

    protected $fillable = [
        'user_id',
        'type',
        'full_name',
        'phone_number',
        'street_address',
        'city',
        'state',
        'zip_code',
        'country',
        'is_default',
    ];

    /** Allowed address types (local address label: Home, Office, Other). */
    public const TYPES = ['home', 'office', 'other'];

    protected $casts = [
        'is_default' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Format for API (checkout Address step / Review screen).
     */
    public function toApiArray(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type ?? 'home',
            'full_name' => $this->full_name,
            'phone_number' => $this->phone_number,
            'street_address' => $this->street_address,
            'city' => $this->city,
            'state' => $this->state,
            'zip_code' => $this->zip_code,
            'country' => $this->country,
            'country_code' => self::countryIso2((string) $this->country),
            'is_default' => $this->is_default,
        ];
    }

    public static function countryIso2(string $country): string
    {
        $t = trim($country);
        if ($t === '') {
            return 'AE';
        }
        if (strlen($t) === 2 && ctype_alpha($t)) {
            return strtoupper($t);
        }

        $tl = function_exists('mb_strtolower') ? mb_strtolower($t, 'UTF-8') : strtolower($t);
        $tl = preg_replace('/[()\[\]{}]/u', ' ', $tl) ?? $tl;
        $tl = trim(preg_replace('/\s+/u', ' ', $tl) ?? $tl);

        return match (true) {
            $tl === 'uae',
            $tl === 'united arab emirates',
            $tl === 'متحدہ عرب امارات',
            $tl === 'الامارات العربية المتحدة',
            $tl === 'الإمارات العربية المتحدة',
            str_contains($tl, 'uae'),
            str_contains($tl, 'emirates'),
            str_contains($tl, 'امارات'),
            str_contains($tl, 'الإمارات') => 'AE',
            $tl === 'united states', $tl === 'usa' => 'US',
            $tl === 'india' => 'IN',
            $tl === 'pakistan' => 'PK',
            $tl === 'saudi arabia', $tl === 'ksa' => 'SA',
            $tl === 'united kingdom', $tl === 'uk' => 'GB',
            default => 'AE',
        };
    }
}
