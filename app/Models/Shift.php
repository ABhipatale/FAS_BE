<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Shift extends Model
{
    use HasFactory;

    protected $fillable = [
        'shift_name',
        'punch_in_time',
        'punch_out_time',
        'status'
    ];

    protected $casts = [
        'status' => 'string'
    ];

    public function users()
    {
        return $this->hasMany(User::class);
    }
}