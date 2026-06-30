<?php

namespace App\Services;

use App\Models\MaintenancePhoto;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class MaintenancePhotoService
{
    public function imageUrl(?string $path): ?string
    {
        if (! $path || ! is_string($path)) {
            return null;
        }

        $path = ltrim(str_replace('\\', '/', $path), '/');
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        $base = rtrim(request()->getSchemeAndHttpHost() ?: config('app.url', ''), '/');

        return $base ? ($base.'/media/'.$path) : asset('media/'.$path);
    }

    /**
     * @return array<string, mixed>
     */
    public function toApiItem(MaintenancePhoto $photo): array
    {
        return [
            'id' => $photo->id,
            'title' => $photo->title,
            'before_image_url' => $this->imageUrl($photo->before_image_path),
            'after_image_url' => $this->imageUrl($photo->after_image_path),
            'priority' => (int) $photo->priority,
            'active' => (bool) $photo->is_active,
            'created_at' => $photo->created_at?->format('c'),
            'updated_at' => $photo->updated_at?->format('c'),
        ];
    }

    /**
     * @param  array{title?: ?string, priority?: int, active?: bool}  $data
     */
    public function store(UploadedFile $beforeImage, UploadedFile $afterImage, array $data = []): MaintenancePhoto
    {
        return MaintenancePhoto::create([
            'title' => $data['title'] ?? null,
            'before_image_path' => $this->storeImage($beforeImage, 'before'),
            'after_image_path' => $this->storeImage($afterImage, 'after'),
            'priority' => (int) ($data['priority'] ?? 0),
            'is_active' => array_key_exists('active', $data) ? (bool) $data['active'] : true,
        ]);
    }

    /**
     * @param  array{title?: ?string, priority?: ?int, active?: ?bool}  $data
     */
    public function update(
        MaintenancePhoto $photo,
        ?UploadedFile $beforeImage = null,
        ?UploadedFile $afterImage = null,
        array $data = []
    ): MaintenancePhoto {
        if ($beforeImage !== null) {
            $this->deleteImage($photo->before_image_path);
            $photo->before_image_path = $this->storeImage($beforeImage, 'before');
        }

        if ($afterImage !== null) {
            $this->deleteImage($photo->after_image_path);
            $photo->after_image_path = $this->storeImage($afterImage, 'after');
        }

        if (array_key_exists('title', $data)) {
            $photo->title = $data['title'];
        }

        if (array_key_exists('priority', $data) && $data['priority'] !== null) {
            $photo->priority = (int) $data['priority'];
        }

        if (array_key_exists('active', $data) && $data['active'] !== null) {
            $photo->is_active = (bool) $data['active'];
        }

        $photo->save();

        return $photo->fresh();
    }

    public function delete(MaintenancePhoto $photo): void
    {
        $this->deleteImage($photo->before_image_path);
        $this->deleteImage($photo->after_image_path);
        $photo->delete();
    }

    private function storeImage(UploadedFile $file, string $prefix): string
    {
        $path = $file->store('maintenance_photos/'.$prefix, 'public');
        ImageCompressionService::compressMaintenancePhotoFromPublicPath($path);

        return $path;
    }

    private function deleteImage(?string $path): void
    {
        if ($path && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }
}
