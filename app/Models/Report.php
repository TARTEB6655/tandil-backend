<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Report extends Model
{
    use HasFactory;
    protected $fillable = [
        'visit_id',
        'supervisor_id',
        'user_id', // client_id for backward compatibility
        'technician_notes',
        'supervisor_notes',
        'notes',
        'recommendations',
        'recommended_products',
        'status',
        'approved_by',
        'approved_at'
    ];

    protected $casts = [
        'recommendations' => 'array',
        'recommended_products' => 'array',
        'approved_at' => 'datetime'
    ];

    public function visit()
    {
        return $this->belongsTo(Visit::class);
    }

    public function supervisor()
    {
        return $this->belongsTo(User::class, 'supervisor_id');
    }
}
