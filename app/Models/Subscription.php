<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Storage;

class Subscription extends Model
{
    use HasFactory;
    protected $fillable = [
        'client_id','plan','start_date','end_date','amount',
        'payment_status','payment_reference','paid_at','total_visits','completed_visits',
        'plan_name','subtitle','features','apply_to_all','target_type','picture','description'
    ];

    protected $appends = ['picture_url'];

    protected $hidden = [
        'total_visits',
        'visits',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'paid_at' => 'datetime',
        'amount' => 'decimal:2',
        'total_visits' => 'integer',
        'completed_visits' => 'integer',
        'features' => 'array',
        'apply_to_all' => 'boolean',
        'target_type'  => 'string',
    ];

    /**
     * Returns the picture URL using the project-standard /media/ pattern.
     * Stores only the relative path in DB; builds the full URL at response time
     * so it always matches the actual server host (works on Cloudways, localhost, etc.).
     */
    public function getPictureUrlAttribute(): ?string
    {
        $path = $this->attributes['picture'] ?? null;
        if (empty($path) || ! is_string($path)) {
            return null;
        }

        $path = ltrim(str_replace('\\', '/', $path), '/');

        // Already a full URL — return as-is
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        // Use request host (correct on every server) → /media/{path}
        $host = rtrim(request()->getSchemeAndHttpHost() ?: config('app.url', ''), '/');
        return $host ? $host . '/media/' . $path : asset('media/' . $path);
    }

    public function client()
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function visits()
    {
        return $this->hasMany(Visit::class);
    }
}
