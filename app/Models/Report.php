<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Report extends Model
{
    use HasFactory;
    protected $fillable = [
        'visit_id','supervisor_id','technician_notes','supervisor_notes','recommendations'
    ];

    protected $casts = [
        'recommendations' => 'array'
    ];

    public function visit()
    {
        return $this->belongsTo(Visit::class);
    }

    public function supervisor()
    {
        return $this->belongsTo(Employee::class, 'supervisor_id');
    }
}
