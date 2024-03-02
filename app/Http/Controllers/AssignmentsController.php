<?php

namespace App\Http\Controllers;

use App\Models\Assignments;
use App\Http\Requests\StoreAssignmentsRequest;
use Illuminate\Http\Request;
use App\Http\Requests\UpdateAssignmentsRequest;
use App\Models\User;

class AssignmentsController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function show($id)
    {
        $assignment= Assignments::findorfail($id);
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
    public function edit(Assignments $assignments)
    {
        //
    }

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
