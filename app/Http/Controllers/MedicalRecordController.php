<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use App\Models\MedicalRecord;
use App\Http\Requests\StoreMedicalRecordRequest;
use App\Http\Requests\UpdateMedicalRecordRequest;

class MedicalRecordController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    

    public function index()
    {
        //
        
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreMedicalRecordRequest $request)
    {
        $validateData = $request->validated();
        if($validateData){
            $medical_record = MedicalRecord::create($validateData);
            $medical_record->save();
            return response()->json([
                'record' => $medical_record
                        ]);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $record = MedicalRecord::findOrFail($id);
        $this->authorize('view', $record);

        return response()->json([
            'record' => $record
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(MedicalRecord $medicalRecord)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateMedicalRecordRequest $request, MedicalRecord $medicalRecord)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(MedicalRecord $medicalRecord)
    {
        //
    }
    public function getDocRecord($doc_id)
    {
        //
        $docMedRec = MedicalRecord::Where('assigned_doctor_id', $doc_id)->with('doctor.user')->get();
        return response()->json([
            'record' => $docMedRec
                    ]);
    }
    
    public function getClientRecord($client_id)
    {
        //
        $clientMedRec = MedicalRecord::Where('client_id', $client_id)->with('doctor.user')->get();
        return response()->json([
            'record' => $clientMedRec
                    ]);
    }
}
