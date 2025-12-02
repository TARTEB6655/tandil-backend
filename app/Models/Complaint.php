<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Complaint extends Model
{
    use HasFactory;

    protected $fillable = [
        'visit_id',
        'client_id',
        'status',
        'notes',
    ];

    /**
     * Complaint belongs to a visit.
     */
    public function visit()
    {
        return $this->belongsTo(Visit::class);
    }

    /**
     * Complaint belongs to a client (user).
     */
    public function client()
    {
        return $this->belongsTo(User::class, 'client_id');
    }
}
