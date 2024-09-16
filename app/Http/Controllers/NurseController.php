<?php

namespace App\Http\Controllers;

use App\Models\Nurse;
use App\Http\Requests\StoreNurseRequest;
use App\Http\Requests\UpdateNurseRequest;

class NurseController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $nurse = Nurse::with('user')->get();
        return response()->json([
            'nurse'=>$nurse
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(StoreNurseRequest $request)
    {
        $validatedData = $request->validated();
        if($validatedData){
            $nurse = Nurse::create($validatedData);
            $nurse->save();

            return response()->json([
                'success'=>'registered as a nurse'
            ]);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreNurseRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(Nurse $nurse, $id)
    {
        $nurse = Nurse::with('user')->findOrFail($id)->first();
        return response()->json([
            'nurse'=>$nurse
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Nurse $nurse)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateNurseRequest $request, Nurse $nurse)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Nurse $nurse)
    {
        //
    }
}
