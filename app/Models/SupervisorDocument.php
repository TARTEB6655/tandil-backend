<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupervisorDocument extends Model
{
    protected $fillable = [
        'supervisor_registration_id',
        'type',
        'file_path',
        'original_name',
        'verification_status',
    ];

    public function registration(): BelongsTo
    {
        return $this->belongsTo(SupervisorRegistration::class, 'supervisor_registration_id');
    }

    public function getFileUrlAttribute(): ?string
    {
        if (! $this->file_path) {
            return null;
        }
        $path = ltrim(str_replace('\\', '/', $this->file_path), '/');
        if (function_exists('request') && request()?->getHttpHost()) {
            return rtrim(request()->getSchemeAndHttpHost(), '/').'/media/'.$path;
        }

        return asset('media/'.$path);
    }

    public function toApiArray(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'file_path' => $this->file_path,
            'file_url' => $this->file_url,
            'original_name' => $this->original_name,
            'verification_status' => $this->verification_status,
        ];
    }
}
