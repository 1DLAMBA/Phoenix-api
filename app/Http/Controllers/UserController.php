<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Mail\OtpVerificationMail;
use Carbon\Carbon;



class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $users = User::with('doctors', 'nurses', 'clients', 'otherProfessionals')->get();
        return response()->json([
            'users' => $users
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request)
    {
        $validate = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email',
            'password' => 'required|string',
            'phoneno' => 'required|string',
            'user_type' => 'required|string',
            'gender' => 'required|string',
            'passport' => '',
        ]);
        $check = User::where('email', $validate['email'])->first();
        if ($check) {
            return response()->json([
                'error' => 'An account is already registered with this email'
              ], 409);
        } else {
            $user = User::create($validate);
            // Save the user to the database
            $user->save();
            
            // Generate and send OTP for professionals (doctor, nurse, other_professional)
            $professionalTypes = ['doctor', 'nurse', 'other_professional'];
            if (in_array($validate['user_type'], $professionalTypes)) {
                $this->generateAndSendOtp($user);
            }
            
            return response()->json([
                'success' => 'Registered!',
                'user' => $user,
                'requires_verification' => in_array($validate['user_type'], $professionalTypes)
            ]);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|string|email',
            'password' => 'required|string',
        ]);

        $credentials = $request->only('email', 'password', 'id');

        if (auth()->attempt($credentials)) {
            $user = auth()->user()->load('doctors', 'nurses', 'clients', 'otherProfessionals', 'sentMessages', 'receivedMessages');
            
            // Check if email is verified for professionals
            $professionalTypes = ['doctor', 'nurse', 'other_professional'];
            if (in_array($user->user_type, $professionalTypes) && !$user->email_verified_at) {
                return response()->json([
                    'error' => 'Email not verified',
                    'requires_verification' => true,
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'user_type' => $user->user_type
                ], 403);
            }
            
            return response()->json([
                'user' => $user,
            ]);
        }

        return response($body = 'invalid credentials', $status = 400, $headers = ['error']);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $user = User::with('doctors', 'nurses', 'clients', 'otherProfessionals', 'clients.appointments.doctor.user')->findorfail($id);
        return response()->json([
            'user' => $user
        ]);
    }


    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }

    /**
     * Generate and send OTP to user's email
     */
    private function generateAndSendOtp(User $user)
    {
        // Generate 4-digit OTP
        $otp = str_pad((string)rand(0, 9999), 4, '0', STR_PAD_LEFT);
        
        // Set OTP expiration (15 minutes from now)
        $expiresAt = Carbon::now()->addMinutes(15);
        
        // Save OTP to user
        $user->email_verification_otp = $otp;
        $user->otp_expires_at = $expiresAt;
        $user->save();
        
        // Send OTP email
        try {
            Mail::to($user->email)->send(new OtpVerificationMail($user, $otp));
        } catch (\Exception $e) {
            Log::error('Failed to send OTP email: ' . $e->getMessage());
        }
    }

    /**
     * Verify OTP
     */
    public function verifyOtp(Request $request)
    {
        $request->validate([
            'user_id' => 'required|integer',
            'otp' => 'required|string|size:4',
        ]);

        $user = User::find($request->user_id);
        
        if (!$user) {
            return response()->json([
                'error' => 'User not found'
            ], 404);
        }

        // Check if OTP matches
        if ($user->email_verification_otp !== $request->otp) {
            return response()->json([
                'error' => 'Invalid OTP code'
            ], 400);
        }

        // Check if OTP has expired
        if ($user->otp_expires_at && Carbon::now()->gt($user->otp_expires_at)) {
            return response()->json([
                'error' => 'OTP has expired. Please request a new one.'
            ], 400);
        }

        // Verify email
        $user->email_verified_at = Carbon::now();
        $user->email_verification_otp = null;
        $user->otp_expires_at = null;
        $user->save();

        return response()->json([
            'success' => 'Email verified successfully',
            'user' => $user
        ]);
    }

    /**
     * Regenerate and resend OTP
     */
    public function regenerateOtp(Request $request)
    {
        $request->validate([
            'user_id' => 'required|integer',
        ]);

        $user = User::find($request->user_id);
        
        if (!$user) {
            return response()->json([
                'error' => 'User not found'
            ], 404);
        }

        // Check if email is already verified
        if ($user->email_verified_at) {
            return response()->json([
                'error' => 'Email is already verified'
            ], 400);
        }

        // Generate and send new OTP
        $this->generateAndSendOtp($user);

        return response()->json([
            'success' => 'New OTP has been sent to your email'
        ]);
    }
}
