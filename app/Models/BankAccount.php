<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Crypt;

class BankAccount extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'account_name',
        'account_number',
        'bank_code',
        'bank_name',
        'paystack_subaccount_code',
        'consultation_fee',
        'paystack_response',
        'professionable_id',
        'professionable_type',
    ];

    protected $casts = [
        'paystack_response' => 'array',
        'consultation_fee' => 'decimal:2',
    ];

    public function professionable()
    {
        return $this->morphTo();
    }

    public function setAccountNumberAttribute($value)
    {
        $this->attributes['account_number'] = Crypt::encryptString($value);
    }

    public function getAccountNumberAttribute($value)
    {
        if (!$value) return null;
        try {
            return Crypt::decryptString($value);
        } catch (\Exception $e) {
            return $value;
        }
    }

    public function getMaskedAccountNumberAttribute()
    {
        $accountNumber = $this->account_number;
        if (!$accountNumber) return null;
        $lastFour = substr($accountNumber, -4);
        return '****' . $lastFour;
    }
}
