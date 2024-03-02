<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MedicalRecord extends Model
{
    use HasFactory;
    protected $fillable = [
        'client_id',
        'assigned_doctor_id',
        'record_number',
        'diagnosis',
        'past_diagnosis',
        'allergies',
        'treatment',

    ];
    public function client()
    {
        return $this->belongsTo(Client::class, 'client_id');
    }
    public function doctor()
    {
        return $this->belongsTo(Doctor::class, 'assigned_doctor_id');
    }
}
