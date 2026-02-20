<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Checkout.com Gateway (cards, Apple Pay, Samsung Pay, Tabby, Tamara)
    |--------------------------------------------------------------------------
    */
    'secret_key' => env('CHECKOUT_SECRET_KEY', ''),
    'public_key' => env('CHECKOUT_PUBLIC_KEY', ''),
    'sandbox' => env('CHECKOUT_SANDBOX', true),
    'webhook_secret' => env('CHECKOUT_WEBHOOK_SECRET', ''),

    'currency' => env('SHOP_CURRENCY', 'AED'),
];
