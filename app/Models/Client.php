<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Client extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'assigned_doctor_id',
        'appointment_id',
        'assigned_nurse_id', 
        'date_of_birth'    
    ];

    public function doctors()
    {
        return $this->hasOne(Doctor::class, 'assigned_doctor_id');
    }
    public function nurse()
    {
        return $this->hasOne(Nurse::class, 'assigned_nurse_id');
    }

    public function appointments()
    {
        return $this->hasOne(Appointment::class, 'appointment_id');
    }
    
    public function medicalrecord()
    {
        return $this->hasMany(MedicalRecord::class);
    }
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
