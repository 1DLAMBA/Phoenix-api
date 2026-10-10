<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\BankAccount;
use App\Models\Client;
use App\Models\Notification;
use Illuminate\Support\Facades\DB;
use App\Events\NotificationSent;
use App\Http\Requests\StoreAppointmentRequest;
use App\Http\Requests\UpdateAppointmentRequest;
use App\Support\ProfessionalRegistration;
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
        // Return the client's free-consultation credit if this was their free appointment.
        $appointment = Appointment::find($id);
        if ($appointment && $appointment->is_free_first) {
            $this->releaseFreeConsultation($appointment);
        }

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
        $appointment = Appointment::with(['doctor.user', 'otherProfessional.user', 'nurse.user'])->findOrFail($id);

        if (ProfessionalRegistration::statusChangeRequiresProfessionalResponse((string) $requestStatus)) {
            $professionalUser = null;
            if ($appointment->doctor_id && $appointment->doctor && $appointment->doctor->user) {
                $professionalUser = $appointment->doctor->user;
            } elseif ($appointment->other_professional_id && $appointment->otherProfessional && $appointment->otherProfessional->user) {
                $professionalUser = $appointment->otherProfessional->user;
            } elseif ($appointment->nurse_id && $appointment->nurse && $appointment->nurse->user) {
                $professionalUser = $appointment->nurse->user;
            }

            if ($professionalUser && !ProfessionalRegistration::registrationComplete($professionalUser)) {
                return response()->json([
                    'error' => 'This professional must complete registration before accepting or declining appointments.',
                    'error_code' => 'REGISTRATION_INCOMPLETE',
                ], 403);
            }
        }

        $appointment->status = $requestStatus;

        // When the professional accepts, decide whether this consultation needs payment.
        // If the professional has a consultation fee configured, the client must pay before
        // consulting (pay-after-acceptance); otherwise the consultation stays free.
        if (in_array(strtolower((string) $requestStatus), ['accepted', 'approved'], true)) {
            $this->applyPaymentRequirement($appointment);
        } elseif (in_array(strtolower((string) $requestStatus), ['declined', 'cancelled', 'canceled', 'pending'], true)) {
            // Declining/cancelling a free first consultation returns the client's free credit.
            $this->releaseFreeConsultation($appointment);
        }

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
     * Snapshot the consultation fee and payment split onto the appointment when it is accepted.
     *
     * Leaves an already-paid appointment untouched. If the professional has no bank account /
     * consultation fee configured, the consultation is marked "not_required" (free).
     */
    private function applyPaymentRequirement(Appointment $appointment): void
    {
        // Never re-price an appointment that has already been paid for.
        if ($appointment->payment_status === 'paid') {
            return;
        }

        $professional = $appointment->doctor ?? $appointment->otherProfessional ?? $appointment->nurse;

        $bankAccount = $professional
            ? BankAccount::where('professionable_id', $professional->id)
                ->where('professionable_type', get_class($professional))
                ->first()
            : null;

        $fee = $bankAccount ? (float) $bankAccount->consultation_fee : 0.0;

        if (!$bankAccount || $fee <= 0) {
            // Free consultation — no payment required.
            $appointment->amount = null;
            $appointment->platform_fee = null;
            $appointment->professional_amount = null;
            $appointment->payment_status = 'not_required';
            return;
        }

        // First-consultation-free: if enabled and this client still has their one-time
        // free credit, this otherwise-chargeable consultation is free (professional absorbs it).
        if (config('consultation.first_free', true) && $this->consumeFreeConsultation($appointment)) {
            $appointment->amount = 0;
            $appointment->platform_fee = 0;
            $appointment->professional_amount = 0;
            $appointment->payment_status = 'not_required';
            $appointment->is_free_first = true;
            return;
        }

        $platformPercentage = (float) config('paystack.platform_percentage', 15);
        $platformFee = round($fee * ($platformPercentage / 100), 2);

        $appointment->amount = $fee;
        $appointment->platform_fee = $platformFee;
        $appointment->professional_amount = round($fee - $platformFee, 2);
        // Only (re)set to unpaid if a prior payment isn't already in flight/succeeded.
        if (!in_array($appointment->payment_status, ['pending', 'paid'], true)) {
            $appointment->payment_status = 'unpaid';
        }
    }

    /**
     * Atomically consume the client's one-time free-consultation credit for this appointment.
     * Returns true if the credit was granted to this appointment (so it should be free).
     */
    private function consumeFreeConsultation(Appointment $appointment): bool
    {
        if ($appointment->is_free_first) {
            return true; // idempotent re-accept of an already-free appointment
        }
        if (!$appointment->client_id) {
            return false;
        }

        return DB::transaction(function () use ($appointment) {
            $client = Client::where('id', $appointment->client_id)->lockForUpdate()->first();
            if (!$client || $client->free_consultation_used) {
                return false;
            }
            $client->free_consultation_used = true;
            $client->free_consultation_used_at = now();
            $client->free_consultation_appointment_id = $appointment->id;
            $client->save();
            return true;
        });
    }

    /**
     * Return a client's free-consultation credit if the free appointment is declined/cancelled,
     * so a no-show professional doesn't cost the patient their freebie. Leaves the (unsaved)
     * appointment's is_free_first cleared for the caller's save.
     */
    private function releaseFreeConsultation(Appointment $appointment): void
    {
        if (!$appointment->is_free_first || !$appointment->client_id) {
            return;
        }
        DB::transaction(function () use ($appointment) {
            $client = Client::where('id', $appointment->client_id)->lockForUpdate()->first();
            if ($client && (int) $client->free_consultation_appointment_id === (int) $appointment->id) {
                $client->free_consultation_used = false;
                $client->free_consultation_used_at = null;
                $client->free_consultation_appointment_id = null;
                $client->save();
            }
        });
        $appointment->is_free_first = false;
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
