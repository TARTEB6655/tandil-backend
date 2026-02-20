<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TechnicianAvailability extends Model
{
    protected $table = 'technician_availability';

    protected $fillable = [
        'user_id',
        'is_online',
        'auto_accept_jobs',
        'working_days',
        'working_hours_slots',
        'default_bank_account_id',
        'payout_frequency',
    ];

    protected $casts = [
        'is_online' => 'boolean',
        'auto_accept_jobs' => 'boolean',
        'working_days' => 'array',
        'working_hours_slots' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function defaultBankAccount(): BelongsTo
    {
        return $this->belongsTo(TechnicianBankAccount::class, 'default_bank_account_id');
    }
}
