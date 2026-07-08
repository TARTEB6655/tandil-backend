<?php

namespace App\Services\Vendor;

use App\Models\Vendor;
use App\Models\VendorAnalyticsShare;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class VendorAnalyticsShareService
{
    public function __construct(
        private readonly VendorPerformanceAnalyticsService $analytics
    ) {}

    /**
     * Create or refresh a public share link for vendor analytics (CSV + browser view).
     *
     * @return array<string, mixed>
     */
    public function createShare(Vendor $vendor, string $period = 'month', int $expiresInDays = 30): array
    {
        $period = $this->analytics->normalizePeriod($period);
        $vendor->loadMissing('profile');

        $existing = VendorAnalyticsShare::query()
            ->where('vendor_id', $vendor->id)
            ->where('period', $period)
            ->first();

        if ($existing && $existing->file_path && Storage::disk('public')->exists($existing->file_path)) {
            Storage::disk('public')->delete($existing->file_path);
        }

        $token = Str::lower(Str::random(48));
        $relativePath = 'shared/vendor-analytics/'.$token.'.csv';
        Storage::disk('public')->put($relativePath, $this->analytics->buildCsvString($vendor, $period));

        $share = VendorAnalyticsShare::updateOrCreate(
            ['vendor_id' => $vendor->id, 'period' => $period],
            [
                'token' => $token,
                'file_path' => $relativePath,
                'expires_at' => now()->addDays($expiresInDays),
            ]
        );

        return $this->sharePayload($share);
    }

    public function findActiveShare(string $token): ?VendorAnalyticsShare
    {
        $share = VendorAnalyticsShare::query()
            ->where('token', $token)
            ->with(['vendor.profile'])
            ->first();

        if ($share === null || $share->isExpired()) {
            return null;
        }

        if (! Storage::disk('public')->exists($share->file_path)) {
            return null;
        }

        return $share;
    }

    /**
     * @return array<string, mixed>
     */
    public function sharePayload(VendorAnalyticsShare $share): array
    {
        $base = rtrim(request()->getSchemeAndHttpHost() ?: config('app.url', ''), '/');

        return [
            'token' => $share->token,
            'period' => $share->period,
            'share_url' => $base.'/shared/analytics/'.$share->token,
            'view_url' => $base.'/shared/analytics/'.$share->token,
            'file_url' => $base.'/shared/analytics/'.$share->token.'/download',
            'expires_at' => $share->expires_at?->toIso8601String(),
            'expires_in_days' => $share->expires_at
                ? max(0, (int) now()->diffInDays($share->expires_at, false))
                : null,
        ];
    }
}
