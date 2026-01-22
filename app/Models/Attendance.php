<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Attendance extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'date',
        'punch_in_time',
        'punch_out_time',
        'status'
    ];

    protected $casts = [
        'date' => 'date',
        'punch_in_time' => 'datetime',
        'punch_out_time' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}