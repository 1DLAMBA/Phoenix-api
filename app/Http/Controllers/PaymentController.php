<?php

namespace App\Http\Controllers;

use App\Events\NotificationSent;
use App\Models\Appointment;
use App\Models\Notification;
use App\Services\PaystackService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PaymentController extends Controller
{
    public function __construct(
        private PaystackService $paystackService,
        private BankAccountController $bankAccountController,
    ) {
    }

    /**
     * Initialize a consultation payment for an already-accepted appointment.
     * Returns a Paystack authorization_url the client is redirected to.
     */
    public function initialize(Request $request)
    {
        $validated = $request->validate([
            'appointment_id' => ['required', 'integer', 'exists:appointments,id'],
            'callback_url' => ['nullable', 'url'],
        ]);

        $appointment = Appointment::with(['client.user', 'doctor.user', 'otherProfessional.user', 'nurse.user'])
            ->findOrFail($validated['appointment_id']);

        // Payment is only for accepted consultations that carry a fee and aren't paid yet.
        if (strtolower((string) $appointment->status) !== 'accepted') {
            return response()->json([
                'error' => 'Payment is only available once the professional has accepted the appointment.',
                'error_code' => 'NOT_ACCEPTED',
            ], 422);
        }

        if ($appointment->payment_status === 'paid') {
            return response()->json([
                'error' => 'This consultation has already been paid for.',
                'error_code' => 'ALREADY_PAID',
            ], 409);
        }

        if ($appointment->payment_status === 'not_required' || (float) $appointment->amount <= 0) {
            return response()->json([
                'error' => 'This consultation does not require payment.',
                'error_code' => 'NOT_REQUIRED',
            ], 422);
        }

        $client = $appointment->client;
        $email = $client && $client->user ? $client->user->email : null;
        if (!$email) {
            return response()->json([
                'error' => 'A valid client email is required to take payment.',
                'error_code' => 'NO_CLIENT_EMAIL',
            ], 422);
        }

        // Resolve the professional and their Paystack subaccount (lazily created).
        $professional = $appointment->doctor ?? $appointment->otherProfessional ?? $appointment->nurse;
        if (!$professional) {
            return response()->json([
                'error' => 'The professional for this appointment could not be found.',
                'error_code' => 'NO_PROFESSIONAL',
            ], 422);
        }

        $subaccountCode = $this->bankAccountController->getOrCreateSubaccount(
            $professional->id,
            get_class($professional)
        );

        if (!$subaccountCode) {
            return response()->json([
                'error' => 'This professional has not finished setting up payouts yet.',
                'error_code' => 'NO_SUBACCOUNT',
            ], 422);
        }

        $reference = 'PHX-' . $appointment->id . '-' . strtoupper(Str::random(10));

        $result = $this->paystackService->initializePayment(
            (float) $appointment->amount,
            $subaccountCode,
            $email,
            $reference,
            [
                'appointment_id' => $appointment->id,
                'client_id' => $appointment->client_id,
                'professional_type' => get_class($professional),
                'professional_id' => $professional->id,
            ],
            $validated['callback_url'] ?? null
        );

        if (!$result || empty($result['authorization_url'])) {
            return response()->json([
                'error' => 'Could not start the payment. Please try again.',
                'error_code' => 'INIT_FAILED',
            ], 502);
        }

        $appointment->payment_status = 'pending';
        $appointment->payment_reference = $reference;
        $appointment->save();

        return response()->json([
            'message' => 'Payment initialized',
            'data' => [
                'authorization_url' => $result['authorization_url'],
                'access_code' => $result['access_code'] ?? null,
                'reference' => $reference,
                'amount' => (float) $appointment->amount,
            ],
        ]);
    }

    /**
     * Verify a consultation payment after the client returns from Paystack.
     */
    public function verify(Request $request)
    {
        $validated = $request->validate([
            'reference' => ['required', 'string'],
        ]);

        $appointment = Appointment::where('payment_reference', $validated['reference'])->first();
        if (!$appointment) {
            return response()->json([
                'error' => 'No appointment matches this payment reference.',
                'error_code' => 'UNKNOWN_REFERENCE',
            ], 404);
        }

        // Idempotency: if we already recorded this as paid, just report success.
        if ($appointment->payment_status === 'paid') {
            return response()->json([
                'message' => 'Payment already confirmed',
                'data' => ['appointment' => $appointment->fresh()],
            ]);
        }

        $data = $this->paystackService->verifyTransaction($validated['reference']);

        if (!$data || ($data['status'] ?? null) !== 'success') {
            $appointment->payment_status = 'failed';
            $appointment->save();

            return response()->json([
                'error' => 'Payment was not successful.',
                'error_code' => 'VERIFICATION_FAILED',
            ], 402);
        }

        // Guard against amount tampering: Paystack reports the charged amount in kobo.
        $expectedKobo = (int) round(((float) $appointment->amount) * 100);
        if ((int) ($data['amount'] ?? 0) < $expectedKobo) {
            Log::warning('Paystack amount mismatch on verify', [
                'reference' => $validated['reference'],
                'expected_kobo' => $expectedKobo,
                'paid_kobo' => $data['amount'] ?? null,
            ]);

            return response()->json([
                'error' => 'The amount paid did not match the consultation fee.',
                'error_code' => 'AMOUNT_MISMATCH',
            ], 402);
        }

        $this->markPaid($appointment);

        return response()->json([
            'message' => 'Payment confirmed',
            'data' => ['appointment' => $appointment->fresh(['doctor.user', 'otherProfessional.user', 'nurse.user', 'client.user'])],
        ]);
    }

    /**
     * Paystack server-to-server webhook (charge.success). Verifies the signature and
     * marks the matching appointment paid, idempotently.
     */
    public function webhook(Request $request)
    {
        $signature = $request->header('x-paystack-signature');
        $secret = config('paystack.secret_key');
        $computed = hash_hmac('sha512', $request->getContent(), (string) $secret);

        if (!$signature || !hash_equals($computed, $signature)) {
            return response()->json(['status' => false], 401);
        }

        $event = $request->input('event');
        $reference = $request->input('data.reference');

        if ($event === 'charge.success' && $reference) {
            $appointment = Appointment::where('payment_reference', $reference)->first();
            if ($appointment && $appointment->payment_status !== 'paid') {
                $this->markPaid($appointment);
            }
        }

        return response()->json(['status' => true]);
    }

    /**
     * List a professional's consultation transactions (their paid appointments),
     * with an earnings summary. Owner-only data — keyed by the professional's user_id.
     */
    public function transactions(Request $request)
    {
        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $user = \App\Models\User::with(['doctors', 'nurses', 'otherProfessionals'])
            ->findOrFail($validated['user_id']);

        $query = Appointment::with('client.user')->where('payment_status', 'paid');

        if ($user->doctors) {
            $query->where('doctor_id', $user->doctors->id);
        } elseif ($user->nurses) {
            $query->where('nurse_id', $user->nurses->id);
        } elseif ($user->otherProfessionals) {
            $query->where('other_professional_id', $user->otherProfessionals->id);
        } else {
            return response()->json([
                'data' => ['transactions' => [], 'total_earned' => 0, 'count' => 0],
            ]);
        }

        $appointments = $query->orderByDesc('paid_at')->get();

        $transactions = $appointments->map(function (Appointment $a) {
            return [
                'id' => $a->id,
                'reference' => $a->payment_reference,
                'patient' => $a->client && $a->client->user ? $a->client->user->name : 'Patient',
                'amount' => (float) $a->amount,
                'platform_fee' => (float) $a->platform_fee,
                'earnings' => (float) $a->professional_amount,
                'paid_at' => optional($a->paid_at)->toIso8601String(),
                'status' => $a->payment_status,
            ];
        });

        return response()->json([
            'data' => [
                'transactions' => $transactions,
                'total_earned' => round($transactions->sum('earnings'), 2),
                'count' => $transactions->count(),
            ],
        ]);
    }

    /**
     * Mark an appointment paid and notify the professional that payment was received.
     */
    private function markPaid(Appointment $appointment): void
    {
        $appointment->payment_status = 'paid';
        $appointment->paid_at = now();
        $appointment->save();

        $appointment->load(['doctor.user', 'otherProfessional.user', 'nurse.user', 'client.user']);

        $professional = $appointment->doctor ?? $appointment->otherProfessional ?? $appointment->nurse;
        if (!$professional || !$professional->user) {
            return;
        }

        $clientName = $appointment->client && $appointment->client->user
            ? $appointment->client->user->name
            : 'A client';

        try {
            $notification = Notification::create([
                'user_id' => $professional->user->id,
                'type' => 'consultation_paid',
                'title' => 'Consultation Payment Received',
                'message' => $clientName . ' has paid the consultation fee. The consultation is now confirmed.',
                'related_id' => $appointment->id,
                'related_type' => 'Appointment',
            ]);

            event(new NotificationSent($notification));
        } catch (\Exception $e) {
            Log::error('Failed to send consultation_paid notification: ' . $e->getMessage(), [
                'appointment_id' => $appointment->id,
            ]);
        }
    }
}
