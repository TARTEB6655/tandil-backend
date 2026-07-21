<?php

namespace App\Services;

use App\Models\Visit;
use App\Models\VisitPhoto;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class VisitPhotoService
{
    public function photoUrl(?string $path): ?string
    {
        if (! $path || ! is_string($path)) {
            return null;
        }

        $path = ltrim(str_replace('\\', '/', $path), '/');
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        // Serve via clean /media/ path (matches profile pictures, product images).
        // The public /storage symlink is not reliable on all hosts (e.g. Cloudways).
        if (function_exists('request') && request() && request()->getHttpHost()) {
            return rtrim(request()->getSchemeAndHttpHost(), '/').'/media/'.$path;
        }

        return asset('media/'.$path);
    }

    /**
     * @return array<string, mixed>
     */
    public function toApiItem(VisitPhoto $photo, ?array $visitSummary = null): array
    {
        $item = [
            'id' => $photo->id,
            'photo_url' => $this->photoUrl($photo->photo_path),
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
}
