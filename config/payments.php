<?php

return [
    'paypal' => [
        'client_id' => env('PAYPAL_CLIENT_ID', ''),
        'secret' => env('PAYPAL_SECRET', ''),
        'sandbox' => env('PAYPAL_SANDBOX', true),
        'webhook_id' => env('PAYPAL_WEBHOOK_ID', ''),
        // PayPal Orders API only supports certain currencies. For others we convert to USD.
        'supported_currencies' => ['USD', 'EUR', 'GBP', 'CAD', 'AUD', 'JPY', 'CHF', 'SGD', 'HKD', 'SEK', 'NOK', 'DKK', 'PLN', 'CZK', 'HUF', 'BRL', 'MXN', 'PHP', 'TWD', 'THB', 'ILS', 'RUB', 'MYR', 'NZD'],
        'fallback_currency' => 'USD',
        // Exchange rates to USD (1 unit of key = value USD). Update periodically or use an API.
        'exchange_rates_to_usd' => [
            'AED' => 0.27,
            'INR' => 0.012,
            'PKR' => 0.0036,
            'EGP' => 0.032,
            'SAR' => 0.27,
            'QAR' => 0.27,
            'KWD' => 3.26,
            'BHD' => 2.65,
            'OMR' => 2.60,
        ],
    ],
];
