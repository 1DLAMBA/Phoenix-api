<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Appointment extends Model
{
    use HasFactory;
    protected $fillable = ['client_id', 'doctor_id','status' ,'date_time']; 

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }
}
