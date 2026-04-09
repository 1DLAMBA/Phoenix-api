<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PaystackService
{
    private string $baseUrl;
    private string $secretKey;
    private string $platformSubaccount;
    private int $platformPercentage;

    public function __construct()
    {
        $this->baseUrl = config('paystack.base_url', 'https://api.paystack.co');
        $this->secretKey = config('paystack.secret_key');
        $this->platformSubaccount = config('paystack.platform_subaccount', 'ACCT_32iz48sbi1fshex');
        $this->platformPercentage = config('paystack.platform_percentage', 15);
    }

    /**
     * Resolve account number to verify it exists and get account holder name
     */
    public function resolveAccount(string $accountNumber, string $bankCode): ?array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->secretKey,
                'Content-Type' => 'application/json',
            ])->get("{$this->baseUrl}/bank/resolve", [
                'account_number' => $accountNumber,
                'bank_code' => $bankCode,
            ]);

            if ($response->successful() && $response->json('status')) {
                return $response->json('data');
            }

            Log::warning('Paystack account resolution failed', [
                'account_number' => substr($accountNumber, -4),
                'bank_code' => $bankCode,
                'response' => $response->json(),
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('Paystack resolve account error', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Create a subaccount for a professional
     * Professional gets 0% charge on their subaccount (100% of what flows to them)
     */
    public function createSubaccount(string $businessName, string $bankCode, string $accountNumber): ?array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->secretKey,
                'Content-Type' => 'application/json',
            ])->post("{$this->baseUrl}/subaccount", [
                'business_name' => $businessName,
                'settlement_bank' => $bankCode,
                'account_number' => $accountNumber,
                'percentage_charge' => 0, // Professional keeps 100% of what flows to their subaccount
                'description' => 'Consultation payments subaccount',
            ]);

            if ($response->successful() && $response->json('status')) {
                return $response->json('data');
            }

            Log::warning('Paystack subaccount creation failed', [
                'business_name' => $businessName,
                'response' => $response->json(),
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('Paystack create subaccount error', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Get list of supported Nigerian banks
     * Falls back to common banks if Paystack API is unreachable
     * Stops after 2 attempts
     */
    public function listBanks(): array
    {
        // Attempt 1: Normal request
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->secretKey,
                'Accept' => 'application/json',
            ])->withOptions([
                'verify' => true,
                'timeout' => 10,
                'connect_timeout' => 5,
            ])->get("{$this->baseUrl}/bank", [
                'country' => 'nigeria',
            ]);

            if ($response->successful() && $response->json('status')) {
                Log::info('Paystack banks fetched successfully');
                return $response->json('data');
            }
        } catch (\Exception $e) {
            Log::warning('Paystack attempt 1 failed', ['error' => $e->getMessage()]);
        }

        // Attempt 2: file_get_contents alternative
        $fallbackResult = $this->tryFileGetContentsBanks();
        if ($fallbackResult !== null) {
            return $fallbackResult;
        }

        // Stop here - use fallback
        Log::info('Using fallback bank list after 2 failed attempts');
        return $this->getFallbackBanks();
    }

    /**
     * Try using file_get_contents as alternative to cURL
     */
    private function tryFileGetContentsBanks(): ?array
    {
        try {
            $url = "{$this->baseUrl}/bank?country=nigeria";
            $context = stream_context_create([
                'http' => [
                    'method' => 'GET',
                    'header' => [
                        'Authorization: Bearer ' . $this->secretKey,
                        'Accept: application/json',
                    ],
                    'timeout' => 15,
                    'ignore_errors' => true,
                ],
                'ssl' => [
                    'verify_peer' => true,
                    'verify_peer_name' => true,
                ],
            ]);

            $response = @file_get_contents($url, false, $context);

            if ($response === false) {
                return null;
            }

            $data = json_decode($response, true);

            if (isset($data['status']) && $data['status'] && isset($data['data'])) {
                Log::info('Paystack banks fetched via file_get_contents');
                return $data['data'];
            }
        } catch (\Exception $e) {
            Log::warning('file_get_contents fallback failed', ['error' => $e->getMessage()]);
        }

        return null;
    }

    /**
     * Fallback bank list for development/offline use
     */
    private function getFallbackBanks(): array
    {
        return [
            ['name' => 'Access Bank', 'code' => '044', 'slug' => 'access-bank'],
            ['name' => 'Access Bank (Diamond)', 'code' => '063', 'slug' => 'access-bank-diamond'],
            ['name' => 'Citibank Nigeria', 'code' => '023', 'slug' => 'citibank-nigeria'],
            ['name' => 'EcoBank Nigeria', 'code' => '050', 'slug' => 'ecobank-nigeria'],
            ['name' => 'Fidelity Bank', 'code' => '070', 'slug' => 'fidelity-bank'],
            ['name' => 'First Bank of Nigeria', 'code' => '011', 'slug' => 'first-bank-of-nigeria'],
            ['name' => 'First City Monument Bank (FCMB)', 'code' => '214', 'slug' => 'fcmb'],
            ['name' => 'Guaranty Trust Bank (GTB)', 'code' => '058', 'slug' => 'gtb'],
            ['name' => 'Heritage Bank', 'code' => '030', 'slug' => 'heritage-bank'],
            ['name' => 'Keystone Bank', 'code' => '082', 'slug' => 'keystone-bank'],
            ['name' => 'Moniepoint MFB', 'code' => '50515', 'slug' => 'moniepoint-mfb'],
            ['name' => 'Opay', 'code' => '999992', 'slug' => 'opay'],
            ['name' => 'Palmpay', 'code' => '999991', 'slug' => 'palmpay'],
            ['name' => 'Polaris Bank', 'code' => '076', 'slug' => 'polaris-bank'],
            ['name' => 'Stanbic IBTC Bank', 'code' => '221', 'slug' => 'stanbic-ibtc-bank'],
            ['name' => 'Standard Chartered Bank', 'code' => '068', 'slug' => 'standard-chartered-bank'],
            ['name' => 'Sterling Bank', 'code' => '232', 'slug' => 'sterling-bank'],
            ['name' => 'SunTrust Bank', 'code' => '100', 'slug' => 'suntrust-bank'],
            ['name' => 'Union Bank of Nigeria', 'code' => '032', 'slug' => 'union-bank-of-nigeria'],
            ['name' => 'United Bank for Africa (UBA)', 'code' => '033', 'slug' => 'uba'],
            ['name' => 'Unity Bank', 'code' => '215', 'slug' => 'unity-bank'],
            ['name' => 'Wema Bank', 'code' => '035', 'slug' => 'wema-bank'],
            ['name' => 'Zenith Bank', 'code' => '057', 'slug' => 'zenith-bank'],
        ];
    }

    /**
     * Initialize payment with transaction split
     * 85% to professional, 15% to platform
     */
    public function initializePayment(
        float $amount,
        string $professionalSubaccount,
        string $email,
        string $reference,
        array $metadata = []
    ): ?array {
        try {
            // Calculate splits: 85% professional, 15% platform
            $professionalShare = round($amount * 0.85);
            $platformShare = round($amount * 0.15);

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->secretKey,
                'Content-Type' => 'application/json',
            ])->post("{$this->baseUrl}/transaction/initialize", [
                'email' => $email,
                'amount' => $amount * 100, // Paystack expects amount in kobo
                'reference' => $reference,
                'subaccount' => $professionalSubaccount,
                'transaction_charge' => $platformShare * 100, // Platform fee in kobo
                'metadata' => $metadata,
            ]);

            if ($response->successful() && $response->json('status')) {
                return $response->json('data');
            }

            Log::warning('Paystack initialize payment failed', [
                'reference' => $reference,
                'response' => $response->json(),
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('Paystack initialize payment error', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Verify payment transaction
     */
    public function verifyTransaction(string $reference): ?array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->secretKey,
            ])->get("{$this->baseUrl}/transaction/verify/{$reference}");

            if ($response->successful() && $response->json('status')) {
                return $response->json('data');
            }

            return null;
        } catch (\Exception $e) {
            Log::error('Paystack verify transaction error', ['error' => $e->getMessage()]);
            return null;
        }
    }

    public function getPlatformSubaccount(): string
    {
        return $this->platformSubaccount;
    }

    public function getPlatformPercentage(): int
    {
        return $this->platformPercentage;
    }
}
