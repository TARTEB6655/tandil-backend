<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class AdminReport extends Model
{
    use HasFactory;

    protected $table = 'admin_reports';

    protected $fillable = [
        'title',
        'type',
        'status',
        'scheduled_at',
        'recurrence',
        'generated_at',
        'file_path',
        'file_size',
        'format',
        'parameters',
        'created_by',
        'failure_reason',
    ];

    protected $casts = [
        'parameters' => 'array',
        'scheduled_at' => 'datetime',
        'generated_at' => 'datetime',
    ];

    public const TYPES = [
        'financial',
        'performance',
        'customer',
        'operational',
        'user',
        'subscription',
    ];

    public const STATUSES = [
        'pending',
        'generated',
        'scheduled',
        'failed',
    ];

    public const RECURRENCE = [
        'daily',
        'weekly',
        'monthly',
        'yearly',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
