<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Http\Requests\StoreAppointmentRequest;
use App\Http\Requests\UpdateAppointmentRequest;
use Illuminate\Http\Request as HttpRequest;
use Illuminate\Request;

class AppointmentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $appointment = Appointment::with('doctor', 'client');
        return response()->json([
            'appointments'=>$appointment
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function delete($id)
    {
        $delete = Appointment::where('id', $id)->delete();
        return response()->json([
            'message' => 'Appointments deleted successfully.',
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreAppointmentRequest $request)
    {
        $validatedData = $request->validated();
        if($validatedData){
            $appointment = Appointment::create($validatedData);
            $appointment->save();
        }
        response()->json([
            'success' => 'assignment created'
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $appointment = Appointment::with('doctor.user', 'client.user')->findorfail($id);
        return response()->json([
            'appointments'=>$appointment
        ]);
    }
    public function showDoc($id)
    {
        $appointment = Appointment::with(['client.user'])->where('doctor_id', $id)->orderBy('created_at', 'desc')->get();
        return response()->json([
            'appointments'=>$appointment
        ]);
    }
    public function showCli($id)
    {
        $appointment = Appointment::with('doctor.user')->where('client_id', $id)->get();
        return response()->json([
            'appointments'=>$appointment
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(HttpRequest $request,string $id)
    {
        $requestStatus = $request->status;
        $appointment = Appointment::findorfail($id);
        $appointment->status = $requestStatus;
        $appointment->save();
        return response()->json([
            'Approved'=>'Appointment Edited'
        ]);


    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateAppointmentRequest $request, Appointment $appointment)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Appointment $appointment)
    {
        //
    }
}
