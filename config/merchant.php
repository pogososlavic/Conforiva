<?php

return [
    'enabled' => env('HOMYA_MERCHANT_FEED_ENABLED', false),
    // Mirrors the confirmed selling destinations; a country is not an approval by Google.
    'target_countries' => ['FR', 'BE', 'DE'],
    // Merchant validation is separate from catalogue publication. No private record
    // or diagnostic is exposed through the public XML endpoint.
    'reviews_path' => storage_path('app/private/google/merchant-products.json'),
];
