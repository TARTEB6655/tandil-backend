<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default shipping amount (AED)
    |--------------------------------------------------------------------------
    | Used in cart order_summary and checkout when no custom shipping is set.
    */
    'shipping_amount' => (float) env('SHOP_SHIPPING_AMOUNT', 9.99),

    'currency' => env('SHOP_CURRENCY', 'AED'),
];
