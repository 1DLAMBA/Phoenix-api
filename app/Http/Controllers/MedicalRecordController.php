<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use App\Models\MedicalRecord;
use App\Models\Notification;
use App\Events\NotificationSent;
use App\Mail\MedicalRecordCreatedMail;
use App\Http\Requests\StoreMedicalRecordRequest;
use App\Http\Requests\UpdateMedicalRecordRequest;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

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

            // Load relationships - handle both doctor and other_professional
            $medical_record->load('client.user', 'doctor.user', 'otherProfessional.user');

            // Create notification for client about medical record creation
            if ($medical_record->client && $medical_record->client->user) {
                // Determine which professional (doctor or other_professional)
                $professional = $medical_record->doctor ?? $medical_record->otherProfessional;
                $professionalName = $professional && $professional->user 
                    ? $professional->user->name 
                    : 'Your healthcare provider';
                
                $notification = Notification::create([
                    'user_id' => $medical_record->client->user->id,
                    'type' => 'medical_record_created',
                    'title' => 'New Medical Record',
                    'message' => $professionalName . ' has created a new medical record for you (Record #' . $medical_record->record_number . ')',
                    'related_id' => $medical_record->id,
                    'related_type' => 'MedicalRecord',
                ]);

                // Broadcast the notification in real-time (ensure notification is saved before broadcasting)
                if ($notification->id) {
                    try {
                        event(new NotificationSent($notification));
                    } catch (\Exception $e) {
                        Log::error('Failed to broadcast medical record notification: ' . $e->getMessage());
                    }
                }
                
                // Send email notification to client
                try {
                    if ($medical_record->client->user->email) {
                        Mail::to($medical_record->client->user->email)->send(new MedicalRecordCreatedMail($medical_record));
                    }
                } catch (\Exception $e) {
                    Log::error('Failed to send medical record email: ' . $e->getMessage());
                }
            }

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
        $docMedRec = MedicalRecord::Where('assigned_doctor_id', $doc_id)->with('doctor.user','client.user')->get();
        return response()->json([
            'record' => $docMedRec
                    ]);
    }
    
    public function getClientRecord($client_id)
    {
        //
        $clientMedRec = MedicalRecord::Where('client_id', $client_id)->with('doctor.user', 'otherProfessional.user', 'client.user')->get();
        return response()->json([
            'record' => $clientMedRec
                    ]);
    }
}
