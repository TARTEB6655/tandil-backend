<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default shipping amount (AED). Use 0 for free shipping.
    |--------------------------------------------------------------------------
    */
    'shipping_amount' => (float) env('SHOP_SHIPPING_AMOUNT', 0),

    /*
    |--------------------------------------------------------------------------
    | Tax percentage applied to subtotal (e.g. 5 = 5%)
    |--------------------------------------------------------------------------
    */
    'tax_percent' => (float) env('SHOP_TAX_PERCENT', 5),

    'currency' => env('SHOP_CURRENCY', 'AED'),
];
