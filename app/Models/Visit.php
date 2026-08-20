<?php

namespace App\Models;

use App\Jobs\SyncVisitOrderTrackingJob;
use App\Notifications\AdminNotification;
use App\Support\RoleUsersNotifier;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Visit extends Model
{
    use HasFactory;

    protected $fillable = [
        'subscription_id',
        'order_id',
        'order_item_id',
        'technician_id',
        'supervisor_id',
        'area_id',
        'scheduled_date',
        'scheduled_time',
        'duration_minutes',
        'completed_date',
        'status',
        'approved_by',
        'approved_at',
        'accepted_at',
        'started_at',
        'completed_at',
        'escalated_at',
        'accept_by',
        'offer_count',
        'notes',
        'internal_notes',
        'price',
    ];

    protected $casts = [
        'scheduled_date' => 'date',
        'completed_date' => 'date',
        'approved_at' => 'datetime',
        'accepted_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'escalated_at' => 'datetime',
        'accept_by' => 'datetime',
        'price' => 'decimal:2',
    ];

    /*
    |--------------------------------------------------------------------------
    | RELATIONSHIPS
    |--------------------------------------------------------------------------
    */

    public function subscription()
    {
        return $this->belongsTo(Subscription::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function orderItem()
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function technician()
    {
        return $this->belongsTo(User::class, 'technician_id');
    }

    public function supervisor()
    {
        return $this->belongsTo(User::class, 'supervisor_id');
    }

    public function area()
    {
        return $this->belongsTo(Area::class, 'area_id');
    }

    public function photos()
    {
        return $this->hasMany(VisitPhoto::class);
    }

    public function report()
    {
        return $this->hasOne(Report::class);
    }

    public function complaints()
    {
        return $this->hasMany(Complaint::class);
    }

    public function visitOffers()
    {
        return $this->hasMany(VisitOffer::class);
    }

    protected static function booted(): void
    {
        static::created(function (Visit $visit): void {
            if ($visit->area_id && $visit->supervisor_id) {
                self::notifyNewJobAssignedToSupervisor($visit);
            } elseif ($visit->area_id && ! $visit->supervisor_id) {
                self::notifyAreaSupervisorsUnclaimedJob($visit);
            }
            self::queueOrderTrackingSync($visit);
        });

        static::updated(function (Visit $visit): void {
            if ($visit->area_id && $visit->supervisor_id) {
                if ($visit->wasChanged('supervisor_id') || ($visit->wasChanged('area_id') && $visit->supervisor_id)) {
                    self::notifyNewJobAssignedToSupervisor($visit);
                }
            }
            if (
                $visit->wasChanged('status')
                || $visit->wasChanged('technician_id')
                || $visit->wasChanged('supervisor_id')
                || $visit->wasChanged('notes')
                || $visit->wasChanged('order_id')
            ) {
                self::queueOrderTrackingSync($visit);
            }
        });
    }

    private static function queueOrderTrackingSync(Visit $visit): void
    {
        if (app()->environment('testing') || app()->runningInConsole()) {
            (new SyncVisitOrderTrackingJob((int) $visit->id))->handle();

            return;
        }

        SyncVisitOrderTrackingJob::dispatch((int) $visit->id)->afterResponse();
    }

    private static function notifyNewJobAssignedToSupervisor(Visit $visit): void
    {
        try {
            $visit->loadMissing('supervisor');
            /** @var User|null $supervisor */
            $supervisor = $visit->supervisor;
            if (! $supervisor) {
                return;
            }

            $jobName = self::jobTitleFromNotes((string) ($visit->notes ?? ''), (int) $visit->id);
            $supervisorName = $supervisor->name ?? ('Supervisor #' . $supervisor->id);
            $message = "New job '{$jobName}' assigned to supervisor {$supervisorName}.";
            $meta = [
                'type' => 'supervisor_new_zone_job_assigned',
                'visit_id' => $visit->id,
                'job_name' => $jobName,
                'supervisor_id' => $supervisor->id,
                'supervisor_name' => $supervisorName,
                'area_id' => $visit->area_id,
            ];

            // Supervisor notification
            $supervisor->notify(new AdminNotification('New job assigned', $message, $meta));

            // Admin notifications
            foreach (RoleUsersNotifier::usersForRole('admin') as $admin) {
                $admin->notify(new AdminNotification('New job assigned', $message, $meta));
            }
        } catch (\Throwable $e) {
            // Keep visit create/update non-blocking if notification channel fails.
        }
    }

    /**
     * Broadcast a new unclaimed zone job to every supervisor mapped to the area.
     */
    private static function notifyAreaSupervisorsUnclaimedJob(Visit $visit): void
    {
        try {
            $visit->loadMissing('area.supervisors');
            $supervisors = $visit->area?->supervisors ?? collect();
            if ($supervisors->isEmpty()) {
                return;
            }

            $jobName = self::jobTitleFromNotes((string) ($visit->notes ?? ''), (int) $visit->id);
            $message = "New job '{$jobName}' is available in your area. Accept it to claim and assign a technician.";
            $meta = [
                'type' => 'supervisor_new_zone_job_unclaimed',
                'visit_id' => $visit->id,
                'job_name' => $jobName,
                'area_id' => $visit->area_id,
                'can_claim' => true,
            ];

            foreach ($supervisors as $supervisor) {
                if ($supervisor instanceof User) {
                    $supervisor->notify(new AdminNotification('New job available', $message, $meta));
                }
            }

            foreach (RoleUsersNotifier::usersForRole('admin') as $admin) {
                $admin->notify(new AdminNotification('New job available', $message, $meta));
            }
        } catch (\Throwable $e) {
            // non-blocking
        }
    }

    private static function jobTitleFromNotes(string $notes, int $visitId): string
    {
        $clean = trim(preg_replace('/^\[DUMMY-SUP-ASSIGN\]\s*/', '', $notes) ?? $notes);
        $parts = array_values(array_filter(array_map('trim', explode('|', $clean)), fn ($p) => $p !== ''));
        if (isset($parts[0]) && $parts[0] !== '') {
            return $parts[0];
        }

        return 'Task #' . $visitId;
    }
}
