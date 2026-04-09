<?php

namespace App\Http\Controllers;

use App\Models\BankAccount;
use App\Models\Doctor;
use App\Models\Nurse;
use App\Models\OtherProfessional;
use App\Services\PaystackService;
use App\Http\Requests\StoreBankAccountRequest;
use Illuminate\Http\Request;

class BankAccountController extends Controller
{
    private PaystackService $paystackService;

    public function __construct(PaystackService $paystackService)
    {
        $this->paystackService = $paystackService;
    }

    /**
     * Get current professional's bank account details
     * Requires user_id in query parameter
     */
    public function index(Request $request)
    {
        $userId = $request->query('user_id');
        if (!$userId) {
            return response()->json(['message' => 'user_id parameter required'], 400);
        }

        $professional = $this->getProfessionalByUserId($userId);

        if (!$professional) {
            return response()->json(['bank_account' => null], 200);
        }

        $bankAccount = BankAccount::where('professionable_id', $professional->id)
            ->where('professionable_type', get_class($professional))
            ->first();

        if (!$bankAccount) {
            return response()->json(['bank_account' => null], 200);
        }

        return response()->json([
            'bank_account' => [
                'id' => $bankAccount->id,
                'account_name' => $bankAccount->account_name,
                'account_number' => $bankAccount->masked_account_number,
                'bank_name' => $bankAccount->bank_name,
                'consultation_fee' => $bankAccount->consultation_fee,
                'paystack_subaccount_code' => $bankAccount->paystack_subaccount_code,
                'status' => $bankAccount->paystack_subaccount_code ? 'active' : 'pending_activation',
            ]
        ]);
    }

    /**
     * Store bank account details
     * Requires user_id in request body
     */
    public function store(StoreBankAccountRequest $request)
    {
        $userId = $request->input('user_id');
        if (!$userId) {
            return response()->json(['message' => 'user_id required'], 400);
        }

        $professional = $this->getProfessionalByUserId($userId);

        if (!$professional) {
            return response()->json(['message' => 'No professional profile found'], 404);
        }

        $validated = $request->validated();

        // Check if bank account already exists
        $existingAccount = BankAccount::where('professionable_id', $professional->id)
            ->where('professionable_type', get_class($professional))
            ->first();

        if ($existingAccount) {
            return response()->json(['message' => 'Bank account already exists. Please update instead.'], 409);
        }

        // Create bank account (subaccount_code is NULL initially - lazy creation)
        $bankAccount = BankAccount::create([
            'account_name' => $validated['account_name'],
            'account_number' => $validated['account_number'],
            'bank_code' => $validated['bank_code'],
            'bank_name' => $validated['bank_name'],
            'paystack_subaccount_code' => null,
            'consultation_fee' => $validated['consultation_fee'],
            'professionable_id' => $professional->id,
            'professionable_type' => get_class($professional),
        ]);

        return response()->json([
            'message' => 'Bank account added successfully',
            'bank_account' => [
                'id' => $bankAccount->id,
                'account_name' => $bankAccount->account_name,
                'account_number' => $bankAccount->masked_account_number,
                'bank_name' => $bankAccount->bank_name,
                'consultation_fee' => $bankAccount->consultation_fee,
                'paystack_subaccount_code' => null,
            ]
        ], 201);
    }

    /**
     * Resolve account number via Paystack
     */
    public function resolveAccount(Request $request)
    {
        $request->validate([
            'account_number' => ['required', 'string', 'size:10'],
            'bank_code' => ['required', 'string'],
        ]);

        $result = $this->paystackService->resolveAccount(
            $request->account_number,
            $request->bank_code
        );

        if (!$result) {
            return response()->json(['message' => 'Could not resolve account number'], 400);
        }

        return response()->json([
            'account_name' => $result['account_name'],
            'account_number' => $result['account_number'],
        ]);
    }

    /**
     * Update consultation fee
     * Requires user_id in request body
     */
    public function update(Request $request, $id)
    {
        $userId = $request->input('user_id');
        if (!$userId) {
            return response()->json(['message' => 'user_id required'], 400);
        }

        $professional = $this->getProfessionalByUserId($userId);

        if (!$professional) {
            return response()->json(['message' => 'No professional profile found'], 404);
        }

        $request->validate([
            'consultation_fee' => ['required', 'numeric', 'min:0'],
        ]);

        $bankAccount = BankAccount::where('id', $id)
            ->where('professionable_id', $professional->id)
            ->where('professionable_type', get_class($professional))
            ->first();

        if (!$bankAccount) {
            return response()->json(['message' => 'Bank account not found'], 404);
        }

        $bankAccount->update([
            'consultation_fee' => $request->consultation_fee,
        ]);

        return response()->json([
            'message' => 'Consultation fee updated successfully',
            'bank_account' => [
                'id' => $bankAccount->id,
                'account_name' => $bankAccount->account_name,
                'account_number' => $bankAccount->masked_account_number,
                'bank_name' => $bankAccount->bank_name,
                'consultation_fee' => $bankAccount->consultation_fee,
                'paystack_subaccount_code' => $bankAccount->paystack_subaccount_code,
                'status' => $bankAccount->paystack_subaccount_code ? 'active' : 'pending_activation',
            ]
        ]);
    }

    /**
     * Soft delete bank account
     * Requires user_id in query parameter
     */
    public function destroy(Request $request, $id)
    {
        $userId = $request->query('user_id');
        if (!$userId) {
            return response()->json(['message' => 'user_id parameter required'], 400);
        }

        $professional = $this->getProfessionalByUserId($userId);

        if (!$professional) {
            return response()->json(['message' => 'No professional profile found'], 404);
        }

        $bankAccount = BankAccount::where('id', $id)
            ->where('professionable_id', $professional->id)
            ->where('professionable_type', get_class($professional))
            ->first();

        if (!$bankAccount) {
            return response()->json(['message' => 'Bank account not found'], 404);
        }

        $bankAccount->delete();

        return response()->json(['message' => 'Bank account removed successfully']);
    }

    /**
     * Get list of Nigerian banks
     */
    public function getBanks()
    {
        $banks = $this->paystackService->listBanks();

        // Sort by name for better UX
        usort($banks, fn($a, $b) => strcmp($a['name'], $b['name']));

        return response()->json(['banks' => $banks]);
    }

    /**
     * Internal method: Get or create Paystack subaccount
     * Called by PaymentController when client initiates payment
     */
    public function getOrCreateSubaccount($professionalId, string $professionalType): ?string
    {
        $bankAccount = BankAccount::where('professionable_id', $professionalId)
            ->where('professionable_type', $professionalType)
            ->first();

        if (!$bankAccount) {
            return null;
        }

        // If subaccount already exists, return it
        if ($bankAccount->paystack_subaccount_code) {
            return $bankAccount->paystack_subaccount_code;
        }

        // Create subaccount via Paystack
        $user = $bankAccount->professionable->user;
        $businessName = $user->name ?? 'Professional ' . $professionalId;

        $result = $this->paystackService->createSubaccount(
            $businessName,
            $bankAccount->bank_code,
            $bankAccount->account_number
        );

        if (!$result) {
            return null;
        }

        // Save subaccount code
        $bankAccount->update([
            'paystack_subaccount_code' => $result['subaccount_code'],
            'paystack_response' => $result,
        ]);

        return $result['subaccount_code'];
    }

    /**
     * Get professional model based on user ID
     */
    private function getProfessionalByUserId($userId)
    {
        // Try Doctor first
        $doctor = Doctor::where('user_id', $userId)->first();
        if ($doctor) {
            return $doctor;
        }

        // Try Nurse
        $nurse = Nurse::where('user_id', $userId)->first();
        if ($nurse) {
            return $nurse;
        }

        // Try Other Professional
        $otherProfessional = OtherProfessional::where('user_id', $userId)->first();
        if ($otherProfessional) {
            return $otherProfessional;
        }

        return null;
    }
}
