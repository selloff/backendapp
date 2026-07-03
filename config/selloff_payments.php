<?php

return [
    'wallet' => [
        'enabled' => env('SELLOFF_WALLET_ENABLED', true),
    ],

    'bank_transfer' => [
        'enabled' => env('SELLOFF_BANK_TRANSFER_ENABLED', true),
        'instructions' => env('SELLOFF_BANK_TRANSFER_INSTRUCTIONS', 'Transfer to Selloff Demo Bank — Acct 0123456789'),
    ],

    'cash_on_delivery' => [
        'enabled' => env('SELLOFF_COD_ENABLED', true),
    ],

    'stripe' => [
        'enabled' => env('SELLOFF_STRIPE_ENABLED', false),
        'public_key' => env('STRIPE_KEY'),
        'secret_key' => env('STRIPE_SECRET'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
        'verify_webhook' => env('SELLOFF_STRIPE_VERIFY_WEBHOOK', true),
        'success_url' => env('STRIPE_SUCCESS_URL', env('FRONTEND_URL', 'http://localhost:5173').'/orders?stripe=success'),
        'cancel_url' => env('STRIPE_CANCEL_URL', env('FRONTEND_URL', 'http://localhost:5173').'/cart?stripe=cancelled'),
    ],
];
