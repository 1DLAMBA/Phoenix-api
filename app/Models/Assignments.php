<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Assignments extends Model
{
    use HasFactory;
    protected $fillable = [
        'assigned_doctor_id', 
        'assigned_nurse_id',       
        'assigned_client_id',
        'assignment_message',
        'status',
    ];
    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class, 'assigned_doctor_id');
    }
    public function nurse()
    {
        return $this->belongsTo(Nurse::class, 'assigned_nurse_id');
    }
    public function client()
    {
        return $this->belongsTo(Client::class, 'assigned_client_id');
    }
}
