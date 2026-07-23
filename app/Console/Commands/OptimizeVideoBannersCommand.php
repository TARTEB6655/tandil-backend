<?php

namespace App\Console\Commands;

use App\Models\VideoBanner;
use App\Services\VideoCompressionService;
use App\Support\VideoBannerCache;
use Illuminate\Console\Command;

/**
 * Re-compress / faststart existing video banners so home screen starts playing quickly.
 */
class OptimizeVideoBannersCommand extends Command
{
    protected $signature = 'video-banners:optimize {--force : Re-encode even small files}';

    protected $description = 'Compress and faststart existing video banners for faster client home playback';

    public function handle(): int
    {
        $banners = VideoBanner::query()->whereNotNull('video_path')->get();
        if ($banners->isEmpty()) {
            $this->info('No video banners found.');

            return self::SUCCESS;
        }

        $this->info('Optimizing '.$banners->count().' video banner(s)…');
        $updated = 0;

        foreach ($banners as $banner) {
            $before = (string) $banner->video_path;
            try {
                $after = VideoCompressionService::compressIfNeededFromPublicPath($before);
                if ($after !== $before) {
                    $banner->video_path = $after;
                    $banner->save();
                    $updated++;
                    $this->line("  #{$banner->id}: {$before} → {$after}");
                } else {
                    // Still attempt faststart remux in place
                    $fast = VideoCompressionService::ensureFastStartFromPublicPath($before);
                    if ($fast !== $before) {
                        $banner->video_path = $fast;
                        $banner->save();
                        $updated++;
                        $this->line("  #{$banner->id}: faststart {$before} → {$fast}");
                    } else {
                        $this->line("  #{$banner->id}: ok ({$before})");
                    }
                }
            } catch (\Throwable $e) {
                $this->error("  #{$banner->id}: ".$e->getMessage());
            }
        }

        VideoBannerCache::forgetPublicList();
        $this->info("Done. Paths updated: {$updated}");
        $this->newLine();
        $this->comment('For fastest app playback, ensure the media symlink exists so /media/* is static:');
        $this->comment('  php artisan storage:link');

        return self::SUCCESS;
    }
}
