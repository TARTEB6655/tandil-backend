<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class VisitPhoto extends Model
{
    use HasFactory;
    protected $fillable = ['visit_id','type','photo_path'];

    public function visit()
    {
        return $this->belongsTo(Visit::class);
    }
}
