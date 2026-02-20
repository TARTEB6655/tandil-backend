<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Employee extends Model
{
    protected $fillable = [
        'user_id', 'name', 'email', 'employee_id', 'phone', 'designation', 'region', 'joining_date', 'specializations'
    ];

    protected $casts = [
        'specializations' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function visits()
    {
        return $this->hasMany(Visit::class, 'technician_id');
    }
}
