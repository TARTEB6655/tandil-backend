<?php

namespace App\Jobs;

use App\Services\ImageCompressionService;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Log;

/**
 * Runs image optimization after the HTTP response is sent (fast API for large uploads).
 * Not queued — uses dispatch()->afterResponse() so no queue worker is required.
 */
class OptimizePublicDiskImageJob
{
    use Dispatchable;

    public function __construct(
        public string $relativePath,
        public string $profile = 'gallery',
    ) {}

    public function handle(): void
    {
        if ($this->relativePath === '') {
            return;
        }

        try {
            $ok = match ($this->profile) {
                'option' => ImageCompressionService::optimizeProductOptionFromPublicPath($this->relativePath),
                default => ImageCompressionService::optimizeProductGalleryFromPublicPath($this->relativePath),
            };

            if (! $ok) {
                Log::warning('OptimizePublicDiskImageJob: optimization returned false', [
                    'path' => $this->relativePath,
                    'profile' => $this->profile,
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('OptimizePublicDiskImageJob failed', [
                'path' => $this->relativePath,
                'profile' => $this->profile,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
