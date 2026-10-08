<?php

return [
    'secret_key' => env('PAYSTACK_SECRET_KEY'),
    'public_key' => env('PAYSTACK_PUBLIC_KEY'),
    'base_url' => env('PAYSTACK_BASE_URL', 'https://api.paystack.co'),

    // Platform commission subaccount — the correct one is chosen automatically from the
    // secret key mode (sk_live_* => live, sk_test_* => test) so a live subaccount is never
    // used with test keys. See PaystackService::__construct().
    'platform_subaccount_live' => env('PAYSTACK_PLATFORM_SUBACCOUNT_LIVE', 'ACCT_1hli5sgrrcfuas9'),
    'platform_subaccount_test' => env('PAYSTACK_PLATFORM_SUBACCOUNT_TEST', 'ACCT_fxd74s1ty1hqs49'),

    'platform_percentage' => env('PAYSTACK_PLATFORM_PERCENTAGE', 15),
];
