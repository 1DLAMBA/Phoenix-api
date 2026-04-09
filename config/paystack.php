<?php

return [
    'secret_key' => env('PAYSTACK_SECRET_KEY'),
    'public_key' => env('PAYSTACK_PUBLIC_KEY'),
    'base_url' => env('PAYSTACK_BASE_URL', 'https://api.paystack.co'),
    'platform_subaccount' => env('PAYSTACK_PLATFORM_SUBACCOUNT', 'ACCT_32iz48sbi1fshex'),
    'platform_percentage' => env('PAYSTACK_PLATFORM_PERCENTAGE', 15),
];
