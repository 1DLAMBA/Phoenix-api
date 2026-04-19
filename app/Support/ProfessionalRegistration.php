<?php

namespace App\Support;

use App\Models\BankAccount;
use App\Models\Doctor;
use App\Models\Nurse;
use App\Models\OtherProfessional;
use App\Models\User;
use Carbon\Carbon;

class ProfessionalRegistration
{
    public static function registrationComplete(?User $user): bool
    {
        if (!$user) {
            return false;
        }

        $user->loadMissing(['doctors', 'nurses', 'otherProfessionals']);

        return match ($user->user_type) {
            'doctor' => self::doctorProfileComplete($user),
            'nurse' => self::nurseProfileComplete($user),
            'other_professional' => self::otherProfessionalProfileComplete($user),
            default => true,
        };
    }

    /**
     * User has no professional row yet (should not happen after OTP stub flow).
     */
    public static function requiresProfessionalProfile(User $user): bool
    {
        return match ($user->user_type) {
            'doctor' => $user->doctors === null,
            'nurse' => $user->nurses === null,
            'other_professional' => $user->otherProfessionals === null,
            default => false,
        };
    }

    public static function ensureStubForVerifiedProfessional(User $user): void
    {
        $types = ['doctor', 'nurse', 'other_professional'];
        if (!in_array($user->user_type, $types, true) || !$user->email_verified_at) {
            return;
        }

        $user->loadMissing(['doctors', 'nurses', 'otherProfessionals']);

        if ($user->user_type === 'doctor' && !$user->doctors) {
            Doctor::create([
                'user_id' => $user->id,
                'license_number' => null,
                'med_school' => null,
                'specialization' => null,
                'grad_year' => null,
                'degree_file' => null,
                'signature' => null,
                'id_card' => null,
                'availability' => null,
            ]);
        }

        if ($user->user_type === 'nurse' && !$user->nurses) {
            Nurse::create([
                'user_id' => $user->id,
                'license_number' => null,
                'med_school' => null,
                'specialization' => null,
                'grad_year' => null,
                'degree_file' => null,
                'signature' => null,
                'id_card' => null,
            ]);
        }

        if ($user->user_type === 'other_professional' && !$user->otherProfessionals) {
            OtherProfessional::create([
                'user_id' => $user->id,
                'professional_type' => null,
                'license_number' => null,
                'med_school' => null,
                'specialization' => null,
                'grad_year' => null,
                'degree_file' => null,
                'signature' => null,
                'id_card' => null,
            ]);
        }
    }

    public static function appendFlagsToUser(User $user): array
    {
        $user->loadMissing(['doctors', 'nurses', 'otherProfessionals']);

        $requires = self::requiresProfessionalProfile($user);
        $complete = self::registrationComplete($user);

        return [
            'requires_professional_profile' => $requires,
            'registration_complete' => $complete,
            'can_respond_to_consultations' => $complete,
        ];
    }

    public static function appendFlagToDoctor(Doctor $doctor): Doctor
    {
        $doctor->loadMissing('user');
        if ($doctor->user) {
            $doctor->setAttribute('registration_complete', self::registrationComplete($doctor->user));
        } else {
            $doctor->setAttribute('registration_complete', false);
        }

        return $doctor;
    }

    public static function appendFlagToNurse(Nurse $nurse): Nurse
    {
        $nurse->loadMissing('user');
        if ($nurse->user) {
            $nurse->setAttribute('registration_complete', self::registrationComplete($nurse->user));
        } else {
            $nurse->setAttribute('registration_complete', false);
        }

        return $nurse;
    }

    public static function appendFlagToOtherProfessional(OtherProfessional $op): OtherProfessional
    {
        $op->loadMissing('user');
        if ($op->user) {
            $op->setAttribute('registration_complete', self::registrationComplete($op->user));
        } else {
            $op->setAttribute('registration_complete', false);
        }

        return $op;
    }

    /**
     * Fresh row from DB so completeness matches persisted files/credentials (not stale relation cache).
     */
    private static function doctorProfileComplete(User $user): bool
    {
        $d = Doctor::where('user_id', $user->id)->first();
        if (!$d) {
            return false;
        }

        foreach (['license_number', 'med_school', 'specialization'] as $field) {
            if (!self::filledAttribute($d->{$field})) {
                return false;
            }
        }

        foreach (['degree_file', 'signature', 'id_card'] as $field) {
            if (!self::filledAttribute($d->{$field})) {
                return false;
            }
        }

        if (!self::gradYearFilled($d->grad_year)) {
            return false;
        }

        return self::hasBankAccountOrLegacy($d);
    }

    private static function nurseProfileComplete(User $user): bool
    {
        $n = Nurse::where('user_id', $user->id)->first();
        if (!$n) {
            return false;
        }

        foreach (['license_number', 'med_school', 'specialization'] as $field) {
            if (!self::filledAttribute($n->{$field})) {
                return false;
            }
        }

        foreach (['degree_file', 'signature', 'id_card'] as $field) {
            if (!self::filledAttribute($n->{$field})) {
                return false;
            }
        }

        if (!self::gradYearFilled($n->grad_year)) {
            return false;
        }

        return self::hasBankAccountOrLegacy($n);
    }

    private static function otherProfessionalProfileComplete(User $user): bool
    {
        $o = OtherProfessional::where('user_id', $user->id)->first();
        if (!$o) {
            return false;
        }

        if (!self::filledAttribute($o->professional_type) || !self::filledAttribute($o->med_school) || !self::filledAttribute($o->specialization)) {
            return false;
        }

        foreach (['degree_file', 'signature', 'id_card'] as $field) {
            if (!self::filledAttribute($o->{$field})) {
                return false;
            }
        }

        if (!self::gradYearFilled($o->grad_year)) {
            return false;
        }

        return self::hasBankAccountOrLegacy($o);
    }

    private static function gradYearFilled(mixed $value): bool
    {
        if ($value === null || $value === '') {
            return false;
        }

        return (int) $value > 0;
    }

    /**
     * Non-empty stored value (string, int, etc.). DB/API may not always return strings.
     */
    private static function filledAttribute(mixed $value): bool
    {
        if ($value === null) {
            return false;
        }
        if (is_string($value)) {
            return trim($value) !== '';
        }
        if (is_int($value) || is_float($value)) {
            return true;
        }
        if (is_bool($value)) {
            return $value;
        }

        return !empty($value);
    }

    /**
     * Bank account required for profiles created on or after deferred-registration rollout.
     * Older rows stay eligible without Paystack/bank setup.
     */
    private static function bankRequiredFor(Doctor|Nurse|OtherProfessional $professionable): bool
    {
        $cutoff = Carbon::parse('2026-04-16 00:00:00', config('app.timezone'));

        return $professionable->created_at && $professionable->created_at->gte($cutoff);
    }

    private static function hasBankAccount(Doctor|Nurse|OtherProfessional $professionable): bool
    {
        return BankAccount::where('professionable_id', $professionable->id)
            ->where('professionable_type', $professionable::class)
            ->exists();
    }

    private static function hasBankAccountOrLegacy(Doctor|Nurse|OtherProfessional $professionable): bool
    {
        if (self::hasBankAccount($professionable)) {
            return true;
        }

        return !self::bankRequiredFor($professionable);
    }

    /**
     * Status updates that only a complete professional should perform (accept / decline / complete).
     */
    public static function statusChangeRequiresProfessionalResponse(string $newStatus): bool
    {
        $s = strtolower(trim($newStatus));

        return in_array($s, ['accepted', 'approved', 'declined', 'done'], true);
    }
}
