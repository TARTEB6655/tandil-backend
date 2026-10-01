<?php

namespace App\Models;

use App\Services\MaintenancePhotoService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class MaintenancePhoto extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'before_image_path',
        'after_image_path',
        'priority',
        'is_active',
    ];

    protected $casts = [
        'priority' => 'integer',
        'is_active' => 'boolean',
    ];

    protected $appends = ['before_image_url', 'after_image_url'];

    public function getBeforeImageUrlAttribute(): ?string
    {
        return app(MaintenancePhotoService::class)->imageUrl($this->before_image_path);
    }

    public function getAfterImageUrlAttribute(): ?string
    {
        return app(MaintenancePhotoService::class)->imageUrl($this->after_image_path);
    }
}
