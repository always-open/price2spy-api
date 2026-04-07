<?php

return [
    'base_url' => env('PRICE2SPY_API_BASE_URL', 'https://api.price2spy.com/rest/v1/'),
    'api_key' => env('PRICE2SPY_API_KEY'),
    'timeout' => env('PRICE2SPY_API_TIMEOUT', 300),
];
