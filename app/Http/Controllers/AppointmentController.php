<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Notification;
use App\Events\NotificationSent;
use App\Http\Requests\StoreAppointmentRequest;
use App\Http\Requests\UpdateAppointmentRequest;
use App\Mail\AppointmentBookedMail;
use App\Mail\AppointmentAcceptedMail;
use Illuminate\Http\Request as HttpRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

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
            
            // Load relationships for email
            $appointment->load('doctor.user', 'client.user');
            
            // Send email notification to doctor
            try {
                if ($appointment->doctor && $appointment->doctor->user && $appointment->doctor->user->email) {
                    Mail::to($appointment->doctor->user->email)->send(new AppointmentBookedMail($appointment));
                }
            } catch (\Exception $e) {
                // Log the error but don't fail the appointment creation
                Log::error('Failed to send appointment email: ' . $e->getMessage());
            }

            // Create notification for doctor about new appointment booking
            if ($appointment->doctor && $appointment->doctor->user) {
                $clientName = $appointment->client && $appointment->client->user 
                    ? $appointment->client->user->name 
                    : 'A client';
                
                $notification = Notification::create([
                    'user_id' => $appointment->doctor->user->id,
                    'type' => 'appointment_booking',
                    'title' => 'New Appointment Booking',
                    'message' => $clientName . ' has booked an appointment with you for ' . date('M d, Y h:i A', strtotime($appointment->date_time)),
                    'related_id' => $appointment->id,
                    'related_type' => 'Appointment',
                ]);

                // Broadcast the notification in real-time
                try {
                    event(new NotificationSent($notification));
                } catch (\Exception $e) {
                    Log::error('Failed to broadcast appointment booking notification: ' . $e->getMessage());
                }
            }
        }
        
        return response()->json([
            'success' => 'Appointment created successfully'
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

        // Refresh the appointment to ensure relationships are available
        $appointment->refresh();
        
        // Load relationships
        $appointment->load('doctor.user', 'client.user');

        // Create notification when appointment is accepted
        // Check for both lowercase and capitalized versions
        $statusLower = strtolower($requestStatus);
        if ($statusLower === 'accepted' || $statusLower === 'approved') {
            try {
                // Check if client relationship exists
                if ($appointment->client_id && $appointment->client) {
                    $client = $appointment->client;
                    
                    // Check if client has a user relationship
                    if ($client->user_id && $client->user) {
                        $doctorName = 'Your doctor';
                        if ($appointment->doctor_id && $appointment->doctor && $appointment->doctor->user) {
                            $doctorName = $appointment->doctor->user->name;
                        }
                        
                        $notification = Notification::create([
                            'user_id' => $client->user->id,
                            'type' => 'appointment_accepted',
                            'title' => 'Appointment Accepted',
                            'message' => $doctorName . ' has accepted your appointment scheduled for ' . date('M d, Y h:i A', strtotime($appointment->date_time)),
                            'related_id' => $appointment->id,
                            'related_type' => 'Appointment',
                        ]);

                        // Broadcast the notification in real-time
                        try {
                            event(new NotificationSent($notification));
                        } catch (\Exception $e) {
                            Log::error('Failed to broadcast appointment acceptance notification: ' . $e->getMessage());
                        }
                        
                        // Send email notification to client
                        try {
                            if ($client->user->email) {
                                Mail::to($client->user->email)->send(new AppointmentAcceptedMail($appointment));
                            }
                        } catch (\Exception $e) {
                            Log::error('Failed to send appointment acceptance email: ' . $e->getMessage());
                        }
                    } else {
                        Log::warning('Appointment notification: Client user not found', [
                            'appointment_id' => $appointment->id,
                            'client_id' => $appointment->client_id,
                            'client_user_id' => $client->user_id ?? 'null'
                        ]);
                    }
                } else {
                    Log::warning('Appointment notification: Client not found', [
                        'appointment_id' => $appointment->id,
                        'client_id' => $appointment->client_id ?? 'null'
                    ]);
                }
            } catch (\Exception $e) {
                Log::error('Failed to create appointment acceptance notification: ' . $e->getMessage(), [
                    'appointment_id' => $appointment->id,
                    'error' => $e->getTraceAsString()
                ]);
            }
        }

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
