<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'phoneno',
        'gender',
        'user_type',
        'passport',
        'password',
        'slug',
        'email_verification_otp',
        'email_verification_token',
        'otp_expires_at',
    ];

    /**
     * Generate a unique vanity slug from a name (e.g. "Alamba Daniel" -> "alambadaniel").
     */
    /** Frontend routes a vanity slug must never shadow (phoenixmed.online/<slug>). */
    private const RESERVED_SLUGS = [
        'login', 'register', 'panel', 'services', 'about', 'contact', 'consult', 'practice',
        'payment', 'admin', 'api', 'assets', 'privacy', 'terms', 'support', 'help', 'phoenix',
    ];

    public static function generateSlug(string $name): string
    {
        $base = preg_replace('/[^a-z0-9]/', '', strtolower($name));
        if ($base === '') {
            $base = 'user';
        }
        $slug = $base;
        $i = 1;
        while (in_array($slug, self::RESERVED_SLUGS, true) || static::withTrashed()->where('slug', $slug)->exists()) {
            $i++;
            $slug = $base . $i;
        }
        return $slug;
    }

    /**
     * The professional record for this user (doctor / nurse / other professional), if any.
     */
    public function professionalRecord()
    {
        if ($this->user_type === 'doctor') return $this->doctors;
        if ($this->user_type === 'nurse') return $this->nurses;
        if ($this->user_type === 'other_professional') return $this->otherProfessionals;
        return null;
    }
    public function doctors()
    {
        return $this->hasOne(Doctor::class);
    }
    public function nurses()
    {
        return $this->hasOne(Nurse::class);
    }
    public function clients()
    {
        return $this->hasOne(Client::class);
    }
    public function otherProfessionals()
    {
        return $this->hasOne(OtherProfessional::class);
    }
    public function sentMessages()
    {
        return $this->hasMany(Message::class, 'sender_id');
    }

    public function receivedMessages()
    {
        return $this->hasMany(Message::class, 'receiver_id');
    }

    public function notifications()
    {
        return $this->hasMany(Notification::class);
    }

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'email_verification_otp',
        'email_verification_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];
}
