<?php

return [
    'merchant_code' => env('TRIPAY_MERCHANT_CODE'),
    'api_key' => env('TRIPAY_API_KEY'),
    'private_key' => env('TRIPAY_PRIVATE_KEY'),
    'mode' => env('TRIPAY_MODE', 'sandbox'),
    
    'api_url' => env('TRIPAY_MODE', 'sandbox') === 'production' 
        ? 'https://tripay.co.id/api' 
        : 'https://tripay.co.id/api-sandbox',
];
