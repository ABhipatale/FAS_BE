<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Company extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'email',
        'address',
        'phone',
        'logo',
        'status',
    ];

    protected $casts = [
        'status' => 'string',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function faceDescriptors(): HasMany
    {
        return $this->hasMany(FaceDescriptor::class);
    }

    public function shifts(): HasMany
    {
        return $this->hasMany(Shift::class);
    }
}