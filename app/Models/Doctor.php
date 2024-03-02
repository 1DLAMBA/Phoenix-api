<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Doctor extends Model
{
    use HasFactory;
    protected $fillable = [
        'user_id',
        'license_number', 
        'med_school', 
        'specialization',
        'grad_year',   
        'degree_file',
        'availability'
    ];
    
    public function appointments()
    {
        return $this->hasMany(Appointment::class);
    }
    public function clients()
    {
        return $this->hasMany(Client::class);
    }
    public function assignment()
    {
        return $this->hasMany(Assignments::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
