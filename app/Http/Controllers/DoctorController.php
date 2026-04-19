<?php

namespace App\Http\Controllers;

use App\Models\Doctor;
use App\Http\Requests\StoreDoctorRequest;
use App\Http\Requests\UpdateDoctorRequest;
use App\Support\ProfessionalRegistration;

class DoctorController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $doctors = Doctor::with('user')->get()->map(function (Doctor $doctor) {
            return ProfessionalRegistration::appendFlagToDoctor($doctor);
        });

        return response()->json([
            'doctor' => $doctors->values()->all(),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(StoreDoctorRequest $request)
    {
        $validatedData = $request->validated();

        if (Doctor::where('user_id', $validatedData['user_id'])->exists()) {
            return response()->json([
                'error' => 'A professional profile already exists for this account. Use update to complete your registration.',
                'error_code' => 'DUPLICATE_PROFESSIONAL_PROFILE',
            ], 409);
        }

        $doctor = Doctor::create($validatedData);
        $doctor->save();
        $doctor->load('user');
        ProfessionalRegistration::appendFlagToDoctor($doctor);

        return response()->json([
            'Success' => 'Registered as a doctor',
            'user' => $doctor,
            'doctor' => $doctor,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreDoctorRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(Doctor $doctor, string $id)
    {
        $doctor = Doctor::with('user')->where('id', $id)->first();
        if ($doctor) {
            ProfessionalRegistration::appendFlagToDoctor($doctor);
        }

        return response()->json([
            'doctor' => $doctor,
        ]);
    }

    public function getDoc(Doctor $doctor, string $id)
    {
        $doctor = Doctor::with('user')->where('user_id', $id)->first();
        if ($doctor) {
            ProfessionalRegistration::appendFlagToDoctor($doctor);
        }

        return response()->json([
            'doctor' => $doctor,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Doctor $doctor)
    {
        //
    }

    /**
     * Update doctor profile by primary key (used after stub creation).
     */
    public function updateProfile(UpdateDoctorRequest $request, string $id)
    {
        $doctor = Doctor::with('user')->findOrFail($id);
        $doctor->fill($request->validated());
        $doctor->save();
        $doctor->load('user');
        ProfessionalRegistration::appendFlagToDoctor($doctor);

        return response()->json([
            'success' => true,
            'doctor' => $doctor,
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Doctor $doctor)
    {
        //
    }

    public function toggleAvailability(StoreDoctorRequest $request, string $id)
    {
        $doctor = Doctor::with('user')->findOrFail($id);

        if ($doctor->user && !ProfessionalRegistration::registrationComplete($doctor->user)) {
            return response()->json([
                'error' => 'Complete your registration before changing availability.',
                'error_code' => 'REGISTRATION_INCOMPLETE',
            ], 403);
        }

        $validatedData = $request->validated();
        $doctor->availability = $validatedData['availability'];
        $doctor->save();

        return response()->json([
            'message' => 'Availability toggled successfully',
            'doctor' => $doctor,
        ]);
    }
}
