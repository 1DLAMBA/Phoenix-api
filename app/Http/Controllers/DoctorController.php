<?php

namespace App\Http\Controllers;

use App\Models\Doctor;
use App\Http\Requests\StoreDoctorRequest;
use App\Http\Requests\UpdateDoctorRequest;

class DoctorController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $doctor = Doctor::with('user')->get();
        return response()->json([
            'doctor'=>$doctor
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(StoreDoctorRequest $request)
    {
        $validatedData = $request->validated();
        if($validatedData){
            $doctor = Doctor::create($validatedData);
            $doctor->save();
            
            return response()->json([
                'Success'=> 'Registered as a doctor',
                'user'=> $doctor
            ]);
        }
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
        $doctor = Doctor::with('user')->where('id', $id)->first();;
        return response()->json([
            'doctor'=>$doctor
        ]);
    }

    public function getDoc(Doctor $doctor, string $id)
    {
        $doctor = Doctor::with('user')->where('user_id', $id)->first();;
        return response()->json([
            'doctor'=>$doctor
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
     * Update the specified resource in storage.
     */
    public function update(UpdateDoctorRequest $request, Doctor $doctor)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Doctor $doctor)
    {
        //
    }
}
