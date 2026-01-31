<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasApiTokens;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'face_descriptor',
        'address',
        'phone',
        'sex',
        'age',
        'dob',
        'position',
        'shift_id',
        'company_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'face_descriptor'   => 'array', // JSON → PHP array
    ];

    /**
     * Automatically hash password
     */
    public function setPasswordAttribute($value)
    {
        if ($value) {
            $this->attributes['password'] = bcrypt($value);
        }
    }

    /**
     * Role helpers (optional but very useful)
     */
    public function isAdmin(): bool
    {
        return $this->role === 'admin' || $this->role === '0';
    }

    public function isUser(): bool
    {
        return $this->role === 'user' || $this->role === '1';
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === 'superadmin' || $this->role === '2';
    }
    
    public function isEmployee(): bool
    {
        return $this->role === 'employee' || $this->role === '3';
    }
    
    // Relationship with Face Descriptors
    public function faceDescriptors()
    {
        return $this->hasMany(FaceDescriptor::class);
    }
    
    // Relationship with Shift
    public function shift()
    {
        return $this->belongsTo(Shift::class);
    }
    
    // Relationship with Company
    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}