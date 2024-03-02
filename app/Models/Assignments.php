<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Assignments extends Model
{
    use HasFactory;
    protected $fillable = [
        'assigned_doctor_id', 
        'assigned_nurse_id',       
        'assigned_client_id',
        'status',
    ];
    public function doctor()
    {
        return $this->hasOne(Doctor::class, 'assigned_doctor_id');
    }
    public function nurse()
    {
        return $this->hasOne(Nurse::class, 'assigned_nurse_id');
    }
    public function client()
    {
        return $this->hasOne(Client::class, 'assigned_client_id');
    }

}
