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
     * Ensure a downloadable file exists. PDF files are always rebuilt so
     * branding (logo / forest green shell) stays current.
     */
    public function ensureDownloadableFile(bool $force = false): self
    {
        $disk = \Illuminate\Support\Facades\Storage::disk('local');
        $exists = filled($this->file_path) && $disk->exists($this->file_path);
        $isPdf = strtolower((string) ($this->format ?? 'pdf')) === 'pdf'
            || str_ends_with(strtolower((string) ($this->file_path ?? '')), '.pdf');

        if ($exists && ! $isPdf && ! $force) {
            return $this;
        }

        if ($exists) {
            $disk->delete($this->file_path);
        }

        $this->forceFill([
            'status' => 'pending',
            'failure_reason' => null,
            'file_path' => null,
            'file_size' => null,
            'generated_at' => null,
        ])->save();

        \App\Jobs\GenerateReportJob::dispatchSync($this->fresh() ?? $this);

        return $this->fresh() ?? $this;
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

    /**
     * Generate reports whose scheduled_at (Asia/Dubai wall-clock) has arrived.
     * Used by cron and also when admin opens Reports (cron may be late/missing).
     *
     * @return array{processed: int, generated: int, failed: int}
     */
    public static function processDueScheduled(int $limit = 50): array
    {
        $now = \App\Support\DubaiTime::now();
        $nowStorage = $now->format('Y-m-d H:i:s');

        $due = static::query()
            ->where('status', 'scheduled')
            ->whereNotNull('scheduled_at')
            // Compare as naive Dubai wall-clock strings stored in MySQL datetime.
            ->whereRaw('scheduled_at <= ?', [$nowStorage])
            ->orderBy('scheduled_at')
            ->limit(max(1, $limit))
            ->get();

        $generated = 0;
        $failed = 0;

        foreach ($due as $report) {
            $recurrence = $report->recurrence;
            $scheduledAt = $report->scheduled_at?->copy();
            $params = $report->parameters ?? [];
            $createdBy = $report->created_by;
            $title = $report->title;
            $type = $report->type;
            $format = $report->format ?? 'pdf';

            $report->forceFill([
                'status' => 'pending',
                'failure_reason' => null,
            ])->save();

            try {
                // Re-load by id so SerializesModels / sync job sees pending status.
                \App\Jobs\GenerateReportJob::dispatchSync(static::query()->find($report->id) ?? $report);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Scheduled report generation failed', [
                    'report_id' => $report->id,
                    'error' => $e->getMessage(),
                ]);
            }

            $fresh = $report->fresh();
            if ($fresh && $fresh->status === 'generated') {
                $generated++;
            } else {
                $failed++;
            }

            if ($recurrence && in_array($recurrence, self::RECURRENCE, true) && $scheduledAt) {
                $next = \App\Support\DubaiTime::parse($scheduledAt);
                $guard = 0;
                do {
                    $next = match ($recurrence) {
                        'daily' => $next->copy()->addDay(),
                        'weekly' => $next->copy()->addWeek(),
                        'monthly' => $next->copy()->addMonth(),
                        'yearly' => $next->copy()->addYear(),
                        default => $next->copy()->addDay(),
                    };
                    $guard++;
                } while ($next->lte($now) && $guard < 400);

                static::create([
                    'title' => $title,
                    'type' => $type,
                    'status' => 'scheduled',
                    'scheduled_at' => \App\Support\DubaiTime::toStorage($next),
                    'recurrence' => $recurrence,
                    'format' => $format,
                    'parameters' => $params,
                    'created_by' => $createdBy,
                ]);
            }
        }

        return [
            'processed' => $due->count(),
            'generated' => $generated,
            'failed' => $failed,
            'now' => $nowStorage,
        ];
    }
}
