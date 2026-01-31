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
        'status',
        'company_id'
    ];

    protected $casts = [
        'status' => 'string'
    ];

    public function users()
    {
        return $this->hasMany(User::class);
    }
    
    // Relationship with Company
    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}