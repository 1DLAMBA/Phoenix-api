<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Nurse extends Model
{
    use HasFactory;
    protected $fillable = [
        'user_id',
        'license_number', 
        'med_school', 
        'specialization',
        'grad_year',   
        'degree_file',
        'signature',
        'id_card',
    ];

    public function appointments()
    {
        return $this->hasMany(Appointment::class, 'nurse_id');
    }
    public function clients()
    {
        return $this->hasMany(Client::class, 'assigned_nurse_id');
    }
    public function assignment()
    {
        return $this->hasMany(Assignments::class);
    }

    public function medicalrecord()
    {
        return $this->hasMany(MedicalRecord::class);
    }
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function bankAccount()
    {
        return $this->morphOne(BankAccount::class, 'professionable');
    }
}
