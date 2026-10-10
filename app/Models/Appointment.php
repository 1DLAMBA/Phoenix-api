<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Appointment extends Model
{
    use HasFactory;
    protected $fillable = [
        'client_id', 'doctor_id', 'other_professional_id', 'nurse_id', 'status', 'date_time', 'symptoms',
        'amount', 'platform_fee', 'professional_amount', 'payment_status', 'payment_reference', 'paid_at',
        'is_free_first',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'platform_fee' => 'decimal:2',
        'professional_amount' => 'decimal:2',
        'paid_at' => 'datetime',
        'is_free_first' => 'boolean',
    ];

    /**
     * Resolve the professional (doctor / nurse / other professional) this appointment is with.
     */
    public function professional()
    {
        return $this->doctor ?? $this->otherProfessional ?? $this->nurse;
    }

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
