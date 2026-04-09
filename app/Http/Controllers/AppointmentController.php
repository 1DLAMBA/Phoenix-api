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
        $appointment = Appointment::with('doctor.user', 'otherProfessional.user', 'nurse.user', 'client.user');
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
            
            // Refresh to ensure we have the latest data
            $appointment->refresh();
            
            // Load relationships for email - handle both doctor and other_professional
            $appointment->load('doctor.user', 'otherProfessional.user', 'nurse.user', 'client.user');
            
            // Determine which professional to notify
            $professional = $appointment->doctor ?? $appointment->otherProfessional ?? $appointment->nurse;
            
            // Send email notification to professional
            if ($professional && $professional->user) {
                try {
                    if ($professional->user->email) {
                        Mail::to($professional->user->email)->send(new AppointmentBookedMail($appointment));
                    }
                } catch (\Exception $e) {
                    // Log the error but don't fail the appointment creation
                    Log::error('Failed to send appointment email: ' . $e->getMessage(), [
                        'appointment_id' => $appointment->id,
                        'professional_id' => $professional->id,
                        'error' => $e->getMessage()
                    ]);
                }

                // Create notification for professional about new appointment booking
                $clientName = $appointment->client && $appointment->client->user 
                    ? $appointment->client->user->name 
                    : 'A client';
                
                $professionalName = $professional->user->name ?? 'Healthcare Professional';
                
                $notification = Notification::create([
                    'user_id' => $professional->user->id,
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
                    Log::error('Failed to broadcast appointment booking notification: ' . $e->getMessage(), [
                        'notification_id' => $notification->id,
                        'error' => $e->getMessage()
                    ]);
                }
            } else {
                Log::warning('Appointment created but professional not found for notification', [
                    'appointment_id' => $appointment->id,
                    'doctor_id' => $appointment->doctor_id,
                    'other_professional_id' => $appointment->other_professional_id,
                    'nurse_id' => $appointment->nurse_id
                ]);
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
        $appointment = Appointment::with('doctor.user', 'otherProfessional.user', 'nurse.user', 'client.user')->findorfail($id);
        return response()->json([
            'appointments'=>$appointment
        ]);
    }
    public function showDoc($id)
    {
        $appointment = Appointment::with(['client.user', 'otherProfessional.user', 'nurse.user'])
            ->where('doctor_id', $id)
            ->orderBy('created_at', 'desc')
            ->get();
        return response()->json([
            'appointments'=>$appointment
        ]);
    }
    
    public function showOtherProfessional($id)
    {
        $appointment = Appointment::with(['client.user', 'doctor.user', 'nurse.user'])
            ->where('other_professional_id', $id)
            ->orderBy('created_at', 'desc')
            ->get();
        return response()->json([
            'appointments'=>$appointment
        ]);
    }

    public function showNurse($id)
    {
        $appointment = Appointment::with(['client.user', 'doctor.user', 'otherProfessional.user'])
            ->where('nurse_id', $id)
            ->orderBy('created_at', 'desc')
            ->get();
        return response()->json([
            'appointments'=>$appointment
        ]);
    }
    public function showCli($id)
    {
        $appointment = Appointment::with('doctor.user', 'otherProfessional.user', 'nurse.user')->where('client_id', $id)->get();
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

        // Refresh the appointment to ensure we have the latest data
        $appointment->refresh();
        
        // Load relationships - handle both doctor and other_professional
        // Use fresh() to ensure we get the latest relationships
        $appointment = $appointment->fresh(['doctor.user', 'otherProfessional.user', 'nurse.user', 'client.user']);
        
        Log::info('Appointment status updated', [
            'appointment_id' => $appointment->id,
            'status' => $requestStatus,
            'doctor_id' => $appointment->doctor_id,
            'other_professional_id' => $appointment->other_professional_id,
            'nurse_id' => $appointment->nurse_id,
            'has_doctor' => isset($appointment->doctor),
            'has_other_professional' => isset($appointment->otherProfessional),
            'has_nurse' => isset($appointment->nurse),
            'has_client' => isset($appointment->client)
        ]);

        // Create notification when appointment is accepted
        // Check for both lowercase and capitalized versions
        $statusLower = strtolower($requestStatus);
        if ($statusLower === 'accepted' || $statusLower === 'approved') {
            try {
                Log::info('Processing appointment acceptance', [
                    'appointment_id' => $appointment->id,
                    'doctor_id' => $appointment->doctor_id,
                    'other_professional_id' => $appointment->other_professional_id,
                    'client_id' => $appointment->client_id
                ]);
                
                // Check if client relationship exists
                if ($appointment->client_id && $appointment->client) {
                    $client = $appointment->client;
                    
                    // Check if client has a user relationship
                    if ($client->user_id && $client->user) {
                        // Determine which professional accepted
                        $professional = $appointment->doctor ?? $appointment->otherProfessional ?? $appointment->nurse;
                        $professionalName = 'Your healthcare provider';
                        $professionalType = 'unknown';
                        
                        if ($appointment->doctor && $appointment->doctor->user) {
                            $professionalName = $appointment->doctor->user->name;
                            $professionalType = 'doctor';
                        } elseif ($appointment->otherProfessional && $appointment->otherProfessional->user) {
                            $professionalName = $appointment->otherProfessional->user->name;
                            $professionalType = 'other_professional';
                        } elseif ($appointment->nurse && $appointment->nurse->user) {
                            $professionalName = $appointment->nurse->user->name;
                            $professionalType = 'nurse';
                        }
                        
                        Log::info('Creating notification for appointment acceptance', [
                            'appointment_id' => $appointment->id,
                            'client_user_id' => $client->user->id,
                            'professional_type' => $professionalType,
                            'professional_name' => $professionalName
                        ]);
                        
                        $notification = Notification::create([
                            'user_id' => $client->user->id,
                            'type' => 'appointment_accepted',
                            'title' => 'Appointment Accepted',
                            'message' => $professionalName . ' has accepted your appointment scheduled for ' . date('M d, Y h:i A', strtotime($appointment->date_time)),
                            'related_id' => $appointment->id,
                            'related_type' => 'Appointment',
                        ]);

                        // Ensure notification is saved and has an ID
                        if (!$notification->id) {
                            Log::error('Notification was not saved properly', [
                                'notification_data' => $notification->toArray()
                            ]);
                        } else {
                            // Refresh notification to ensure all attributes are loaded
                            $notification->refresh();
                            
                            Log::info('Notification created successfully', [
                                'notification_id' => $notification->id,
                                'user_id' => $notification->user_id,
                                'type' => $notification->type,
                                'title' => $notification->title
                            ]);

                            // Broadcast the notification in real-time
                            try {
                                Log::info('Broadcasting notification via websocket', [
                                    'notification_id' => $notification->id,
                                    'user_id' => $notification->user_id,
                                    'channel' => 'notifications-channel'
                                ]);
                                
                                // Create and dispatch the event
                                $event = new NotificationSent($notification);
                                event($event);
                                
                                Log::info('Notification broadcasted successfully', [
                                    'notification_id' => $notification->id,
                                    'event_dispatched' => true
                                ]);
                            } catch (\Exception $e) {
                                Log::error('Failed to broadcast appointment acceptance notification', [
                                    'notification_id' => $notification->id ?? 'unknown',
                                    'user_id' => $notification->user_id ?? 'unknown',
                                    'error' => $e->getMessage(),
                                    'trace' => $e->getTraceAsString()
                                ]);
                            }
                        }
                        
                        // Send email notification to client
                        try {
                            if ($client->user->email) {
                                Mail::to($client->user->email)->send(new AppointmentAcceptedMail($appointment));
                                Log::info('Appointment acceptance email sent', [
                                    'client_email' => $client->user->email
                                ]);
                            }
                        } catch (\Exception $e) {
                            Log::error('Failed to send appointment acceptance email', [
                                'client_email' => $client->user->email ?? 'unknown',
                                'error' => $e->getMessage()
                            ]);
                        }
                    } else {
                        Log::warning('Appointment notification: Client user not found', [
                            'appointment_id' => $appointment->id,
                            'client_id' => $appointment->client_id,
                            'client_user_id' => $client->user_id ?? 'null',
                            'has_client_user' => isset($client->user)
                        ]);
                    }
                } else {
                    Log::warning('Appointment notification: Client not found', [
                        'appointment_id' => $appointment->id,
                        'client_id' => $appointment->client_id ?? 'null',
                        'has_client' => isset($appointment->client)
                    ]);
                }
            } catch (\Exception $e) {
                Log::error('Failed to create appointment acceptance notification', [
                    'appointment_id' => $appointment->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
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
