<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TechnicianBankAccount extends Model
{
    protected $table = 'technician_bank_accounts';

    protected $fillable = [
        'user_id',
        'bank_name',
        'account_holder_name',
        'last_four',
        'currency',
        'is_default',
    ];

    protected $casts = [
        'is_default' => 'boolean',
    ];

    protected $hidden = [];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Masked display e.g. ****1234 */
    public function getMaskedNumberAttribute(): string
    {
        return '****' . $this->last_four;
    }
}
