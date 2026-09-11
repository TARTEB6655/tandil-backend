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
        'hr_technician_monthly',
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

    /**
     * Re-run generation for pending reports that never got a file
     * (queued without a worker — common for HR API before sync fix).
     */
    public static function healStuckPending(int $limit = 25): int
    {
        $stuck = static::query()
            ->where('status', 'pending')
            ->where(function ($q) {
                $q->whereNull('file_path')->orWhere('file_path', '');
            })
            ->orderBy('id')
            ->limit(max(1, $limit))
            ->get();

        $healed = 0;
        foreach ($stuck as $report) {
            try {
                \App\Jobs\GenerateReportJob::dispatchSync($report);
                $healed++;
            } catch (\Throwable $e) {
                // Job marks failed; keep listing usable.
            }
        }

        return $healed;
    }
}
