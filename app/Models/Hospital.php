<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Hospital extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'address',
        'phoneno',
        'email',
        'registration_number',
        'password',
        'status',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'password' => 'hashed',
    ];

    public function doctors()
    {
        return $this->hasMany(Doctor::class);
    }

    public function nurses()
    {
        return $this->hasMany(Nurse::class);
    }

    public function otherProfessionals()
    {
        return $this->hasMany(OtherProfessional::class);
    }
}
