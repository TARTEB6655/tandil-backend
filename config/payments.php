<?php

return [
    'paypal' => [
        'client_id' => env('PAYPAL_CLIENT_ID', ''),
        'secret' => env('PAYPAL_SECRET', ''),
        'sandbox' => env('PAYPAL_SANDBOX', true),
        // webhook id for verifying events if available
        'webhook_id' => env('PAYPAL_WEBHOOK_ID', ''),
    ],
];
