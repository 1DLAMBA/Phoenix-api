<?php

namespace App\Http\Controllers;

use App\Models\Assignments;
use App\Http\Requests\StoreAssignmentsRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Request as HttpRequest;
use App\Http\Requests\UpdateAssignmentsRequest;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class AssignmentsController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function show($id)
    {
        $assignment= Assignments::with('doctor.user', 'nurse.user', 'client.user')->where('id', $id)->get();

        return response()->json([
            'assignment'=>$assignment
        ]);
    }

    public function index($id)
    {
        $assignment= Assignments::with('doctor.user', 'nurse.user', 'client.user')->where('assigned_nurse_id', $id)->get();

        return response()->json([
            'assignment'=>$assignment
        ]);
    }


    /**
     * Show the form for creating a new resource.
     */
    public function create(StoreAssignmentsRequest $request)
    {
        $validatedData = $request->validated();
        if($validatedData){
            Log::alert($request);
            $assignment = Assignments::create($validatedData);
            $assignment->save();
        }
        response()->json([
            'success' => 'assignment created'
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreAssignmentsRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
   
    /**
     * Show the form for editing the specified resource.
     */
    public function edit(HttpRequest $request,string $id)
    {
        $requestStatus = $request->status;
        $appointment = Assignments::findorfail($id);
        $appointment->status = $requestStatus;
        $appointment->save();
        return response()->json([
            'Approved'=>'Appointment Edited'
        ]);


    } //
    
    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateAssignmentsRequest $request, Assignments $assignments)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Assignments $assignments)
    {
        //
    }
}
