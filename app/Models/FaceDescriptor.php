<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FaceDescriptor extends Model
{
    use HasFactory;
    
    protected $fillable = [
        'user_id',
        'face_descriptor',
        'last_used_at',
        'company_id',
    ];
    
    protected $casts = [
        'face_descriptor' => 'array', // Cast JSON to array
        'last_used_at' => 'datetime',
    ];
    
    // Define the relationship with User
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    
    // Relationship with Company
    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
