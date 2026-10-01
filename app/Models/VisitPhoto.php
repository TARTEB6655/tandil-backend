<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class VisitPhoto extends Model
{
    use HasFactory;
    protected $fillable = ['visit_id', 'type', 'photo_path', 'show_on_client_app'];

    protected $casts = [
        'show_on_client_app' => 'boolean',
    ];

    public function visit()
    {
        return $this->belongsTo(Visit::class);
    }
}