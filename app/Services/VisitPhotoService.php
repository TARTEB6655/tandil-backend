<?php

namespace App\Services;

use App\Models\Visit;
use App\Models\VisitPhoto;
use App\Support\MediaThumbCache;
use App\Support\MediaUrl;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class VisitPhotoService
{
    public const CLIENT_THUMB_WIDTH = 384;

    public function photoUrl(?string $path): ?string
    {
        return MediaUrl::full($path);
    }

    public function thumbUrl(?string $path, int $width = self::CLIENT_THUMB_WIDTH): ?string
    {
        return MediaUrl::thumb($path, $width) ?? MediaUrl::full($path);
    }

    /**
     * @return array<string, mixed>
     */
    public function toApiItem(VisitPhoto $photo, ?array $visitSummary = null): array
    {
        $item = [
            'id' => $photo->id,
            'photo_url' => $this->thumbUrl($photo->photo_path),
            'photo_full_url' => $this->photoUrl($photo->photo_path),
            'photo_path' => $photo->photo_path,
            'type' => $photo->type ?? 'after',
            'visit_id' => $photo->visit_id,
            'show_on_client_app' => (bool) $photo->show_on_client_app,
            'created_at' => $photo->created_at?->format('c'),
        ];

        if ($visitSummary !== null) {
            $item['visit'] = $visitSummary;
        }

        return $item;
    }

    public function storeForVisit(Visit $visit, UploadedFile $file, string $type = 'after', bool $showOnClientApp = true): VisitPhoto
    {
        $path = $file->store('visit_photos', 'public');
        ImageCompressionService::compressVisitPhotoFromPublicPath($path);
        $this->warmThumbs($path);

        return VisitPhoto::create([
            'visit_id' => $visit->id,
            'type' => $type,
            'photo_path' => $path,
            'show_on_client_app' => $showOnClientApp,
        ]);
    }

    public function updatePhoto(VisitPhoto $photo, ?UploadedFile $file = null, ?string $type = null, ?bool $showOnClientApp = null): VisitPhoto
    {
        if ($file !== null) {
            if ($photo->photo_path && Storage::disk('public')->exists($photo->photo_path)) {
                Storage::disk('public')->delete($photo->photo_path);
            }

            $path = $file->store('visit_photos', 'public');
            ImageCompressionService::compressVisitPhotoFromPublicPath($path);
            $this->warmThumbs($path);
            $photo->photo_path = $path;
        }

        if ($type !== null) {
            $photo->type = $type;
        }

        if ($showOnClientApp !== null) {
            $photo->show_on_client_app = $showOnClientApp;
        }

        $photo->save();

        return $photo->fresh();
    }

    public function deletePhoto(VisitPhoto $photo): void
    {
        if ($photo->photo_path && Storage::disk('public')->exists($photo->photo_path)) {
            Storage::disk('public')->delete($photo->photo_path);
        }

        $photo->delete();
    }

    private function warmThumbs(string $path): void
    {
        foreach ([192, 256, self::CLIENT_THUMB_WIDTH] as $width) {
            try {
                MediaThumbCache::resolve($path, $width);
            } catch (\Throwable) {
                // non-fatal
            }
        }
    }
}
