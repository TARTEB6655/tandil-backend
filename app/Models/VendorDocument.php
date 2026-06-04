<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VendorDocument extends Model
{
    protected $fillable = [
        'vendor_id',
        'type',
        'file_path',
        'original_name',
        'verification_status',
        'verified_by',
        'verified_at',
        'admin_notes',
    ];

    protected $casts = [
        'verified_at' => 'datetime',
    ];

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function getFileUrlAttribute(): ?string
    {
        $path = $this->file_path;
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
}
