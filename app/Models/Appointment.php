<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Appointment extends Model
{
    use HasFactory;
    protected $fillable = ['client_id', 'doctor_id', 'other_professional_id', 'nurse_id', 'status', 'date_time', 'symptoms']; 

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }

    public function otherProfessional(): BelongsTo
    {
        return $this->belongsTo(OtherProfessional::class, 'other_professional_id');
    }

    public function nurse(): BelongsTo
    {
        return $this->belongsTo(Nurse::class, 'nurse_id');
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }
}
