<?php

namespace App\Policies;

use App\Models\Doctor;
use App\Models\MedicalRecord;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class MedicalRecordPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        //
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, MedicalRecord $medicalRecord): bool
    {
        //
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(Doctor $doctor): bool
    {
        return $doctor->id;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(Doctor $doctor, MedicalRecord $medicalRecord): bool
    {
       return $doctor->id === $medicalRecord->id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, MedicalRecord $medicalRecord): bool
    {
        //
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, MedicalRecord $medicalRecord): bool
    {
        //
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, MedicalRecord $medicalRecord): bool
    {
        //
    }
}
