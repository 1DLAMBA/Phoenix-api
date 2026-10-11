<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Support\ProfessionalRegistration;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use App\Mail\OtpVerificationMail;
use App\Mail\VerifyEmailMail;
use App\Mail\WelcomeMail;
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

            // Send an email verification link for professionals (doctor, nurse, other_professional)
            $professionalTypes = ['doctor', 'nurse', 'other_professional'];
            if (in_array($validate['user_type'], $professionalTypes)) {
                // Give professionals a shareable vanity slug (phoenix.com/<slug>).
                $user->slug = User::generateSlug($user->name);
                $user->save();

                $this->generateAndSendVerificationLink($user);
            } else {
                // No OTP required — sign-up is complete; send welcome email
                try {
                    Mail::to($user->email)->send(new WelcomeMail($user));
                } catch (\Exception $e) {
                    Log::error('Failed to send welcome email: ' . $e->getMessage());
                }
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

            $professionalTypes = ['doctor', 'nurse', 'other_professional'];
            if (in_array($user->user_type, $professionalTypes, true) && $user->email_verified_at) {
                ProfessionalRegistration::ensureStubForVerifiedProfessional($user);
                $user->refresh()->load(['doctors', 'nurses', 'clients', 'otherProfessionals', 'sentMessages', 'receivedMessages']);
            }

            // Check if email is verified for professionals
            if (in_array($user->user_type, $professionalTypes) && !$user->email_verified_at) {
                return response()->json([
                    'error' => 'Email not verified',
                    'requires_verification' => true,
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'user_type' => $user->user_type
                ], 403);
            }

            if (in_array($user->user_type, $professionalTypes) && $user->email_verified_at) {
                ProfessionalRegistration::ensureStubForVerifiedProfessional($user);
                $user->refresh();
                $user->load(['doctors', 'nurses', 'otherProfessionals', 'clients', 'sentMessages', 'receivedMessages']);
            }

            return response()->json([
                'user' => $this->userWithFlags($user),
            ]);
        }

        return response($body = 'invalid credentials', $status = 400, $headers = ['error']);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $user = User::with('doctors', 'nurses', 'clients', 'otherProfessionals', 'clients.appointments.otherProfessional.user','clients.appointments.doctor.user')->findorfail($id);

        return response()->json([
            'user' => $this->userWithFlags($user),
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
     * Patch basic user fields (e.g. passport path after upload) for the logged-in account.
     */
    public function patchProfile(Request $request, string $id)
    {
        $validated = $request->validate([
            'passport' => 'sometimes|nullable|string|max:2048',
        ]);

        $user = User::findOrFail($id);

        if (isset($validated['passport'])) {
            $user->passport = $validated['passport'];
            $user->save();
        }

        $user->load(['doctors', 'nurses', 'otherProfessionals', 'clients']);

        return response()->json([
            'success' => true,
            'user' => $this->userWithFlags($user),
        ]);
    }

    private function userWithFlags(User $user): array
    {
        $user->loadMissing(['doctors', 'nurses', 'otherProfessionals', 'clients']);

        return array_merge($user->toArray(), ProfessionalRegistration::appendFlagsToUser($user));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $user = User::find($id);
        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }
        // Revoke active sessions, then soft-delete (deactivate — reversible).
        try { $user->tokens()->delete(); } catch (\Exception $e) {}
        $user->delete();

        return response()->json(['success' => 'User deactivated']);
    }

    /**
     * Reactivate a previously deactivated (soft-deleted) user.
     */
    public function restore(string $id)
    {
        $user = User::withTrashed()->find($id);
        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }
        $user->restore();

        return response()->json(['success' => 'User reactivated']);
    }

    /**
     * Admin listing — includes deactivated users so they can be reactivated.
     */
    public function adminIndex()
    {
        $users = User::withTrashed()
            ->with('doctors', 'nurses', 'clients', 'otherProfessionals')
            ->orderByDesc('created_at')
            ->get();

        return response()->json(['users' => $users]);
    }

    /**
     * Send a broadcast email to a selected set of users.
     * Expects: subject, message, user_ids[]. Sends synchronously (chunked).
     */
    private const BROADCAST_RULES = [
        'subject' => 'required|string|max:255',
        'message' => 'required|string|max:20000',
        'headline' => 'nullable|string|max:150',
        'banner_url' => 'nullable|url:http,https|max:500',
        'cta_label' => 'nullable|string|max:40|required_with:cta_url',
        'cta_url' => 'nullable|url:http,https|max:500|required_with:cta_label',
    ];

    private function broadcastOptions(array $validated): array
    {
        return [
            'headline' => $validated['headline'] ?? null,
            'banner_url' => $validated['banner_url'] ?? null,
            'cta_label' => $validated['cta_label'] ?? null,
            'cta_url' => $validated['cta_url'] ?? null,
        ];
    }

    public function broadcast(Request $request)
    {
        $validated = $request->validate(self::BROADCAST_RULES + [
            'user_ids' => 'required|array|min:1',
            'user_ids.*' => 'integer',
        ]);
        $options = $this->broadcastOptions($validated);

        $sent = 0;
        $failed = 0;

        User::whereIn('id', $validated['user_ids'])
            ->whereNotNull('email')
            ->chunk(100, function ($users) use ($validated, $options, &$sent, &$failed) {
                foreach ($users as $user) {
                    try {
                        Mail::to($user->email)->send(
                            new \App\Mail\BroadcastMail($user, $validated['subject'], $validated['message'], $options)
                        );
                        $sent++;
                    } catch (\Exception $e) {
                        $failed++;
                        Log::error('Broadcast email failed for ' . $user->email . ': ' . $e->getMessage());
                    }
                }
            });

        return response()->json([
            'success' => 'Broadcast complete',
            'sent' => $sent,
            'failed' => $failed,
        ]);
    }

    /**
     * Render the newsletter exactly as recipients will see it, using the admin's own name.
     */
    public function broadcastPreview(Request $request)
    {
        $validated = $request->validate(self::BROADCAST_RULES);
        $viewer = (new User())->forceFill(['name' => $request->user()->name ?? 'there']);
        $mail = new \App\Mail\BroadcastMail($viewer, $validated['subject'], $validated['message'], $this->broadcastOptions($validated));

        return response()->json(['html' => view('emails.broadcast', $mail->viewData())->render()]);
    }

    /**
     * Generate a verification token and email the user a verification link.
     */
    private function generateAndSendVerificationLink(User $user)
    {
        // Secure, single-use, unguessable token.
        $token = Str::random(64);

        // Link is valid for 60 minutes (reuses the otp_expires_at column).
        $user->email_verification_token = $token;
        $user->otp_expires_at = Carbon::now()->addMinutes(60);
        // Clear any legacy OTP so only the link works.
        $user->email_verification_otp = null;
        $user->save();

        $frontendUrl = rtrim(config('app.frontend_url'), '/');
        $verificationUrl = $frontendUrl . '/verify-email?token=' . $token;

        // In local/dev, always record the link so testing never depends on email
        // delivery (Brevo/DNS can be flaky, and link emails often land in spam).
        if (!app()->environment('production')) {
            Log::info('Email verification link for ' . $user->email . ': ' . $verificationUrl);
        }

        try {
            Mail::to($user->email)->send(new VerifyEmailMail($user, $verificationUrl));
        } catch (\Exception $e) {
            Log::error('Failed to send verification email: ' . $e->getMessage());
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

        ProfessionalRegistration::ensureStubForVerifiedProfessional($user);
        $user->refresh();
        $user->load(['doctors', 'nurses', 'otherProfessionals', 'clients']);

        // Sign-up is complete; send welcome email
        try {
            Mail::to($user->email)->send(new WelcomeMail($user));
        } catch (\Exception $e) {
            Log::error('Failed to send welcome email: ' . $e->getMessage());
        }

        return response()->json([
            'success' => 'Email verified successfully',
            'user' => $this->userWithFlags($user),
        ]);
    }

    /**
     * Verify email from the link the user clicked (token-based).
     */
    public function verifyEmail(Request $request)
    {
        $request->validate([
            'token' => 'required|string',
        ]);

        $user = User::where('email_verification_token', $request->token)->first();

        if (!$user) {
            return response()->json([
                'error' => 'This verification link is invalid.',
            ], 400);
        }

        // Already verified — treat as success (idempotent for double clicks).
        if ($user->email_verified_at) {
            return response()->json([
                'success' => 'Email already verified',
                'user' => $this->userWithFlags($user->load(['doctors', 'nurses', 'otherProfessionals', 'clients'])),
            ]);
        }

        if ($user->otp_expires_at && Carbon::now()->gt($user->otp_expires_at)) {
            return response()->json([
                'error' => 'This verification link has expired. Please request a new one.',
                'expired' => true,
                'user_id' => $user->id,
            ], 400);
        }

        $user->email_verified_at = Carbon::now();
        $user->email_verification_token = null;
        $user->email_verification_otp = null;
        $user->otp_expires_at = null;
        $user->save();

        ProfessionalRegistration::ensureStubForVerifiedProfessional($user);
        $user->refresh();
        $user->load(['doctors', 'nurses', 'otherProfessionals', 'clients']);

        // Sign-up is complete; send welcome email
        try {
            Mail::to($user->email)->send(new WelcomeMail($user));
        } catch (\Exception $e) {
            Log::error('Failed to send welcome email: ' . $e->getMessage());
        }

        return response()->json([
            'success' => 'Email verified successfully',
            'user' => $this->userWithFlags($user),
        ]);
    }

    /**
     * Resend the email verification link.
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

        // Generate and send a fresh verification link
        $this->generateAndSendVerificationLink($user);

        return response()->json([
            'success' => 'A new verification link has been sent to your email'
        ]);
    }
}
