<?php

return [
    // Observation only until real visitor IPs and ordinary crawl volume have been checked.
    'mode' => env('HOMYA_STOREFRONT_PROTECTION_MODE', 'observe'),
    'client_ip_verified' => env('HOMYA_STOREFRONT_CLIENT_IP_VERIFIED', false) === true,
    'cache_store' => env('HOMYA_STOREFRONT_PROTECTION_CACHE_STORE', 'storefront_protection'),

    // Provisional ceilings, shared by all protected pages and query string variants.
    'requests_per_minute' => (int) env('HOMYA_STOREFRONT_REQUESTS_PER_MINUTE', 180),
    'requests_per_hour' => (int) env('HOMYA_STOREFRONT_REQUESTS_PER_HOUR', 1800),

    // Written by the dedicated CLI refresh command; no network lookup during a visit.
    'crawler_ranges_path' => storage_path('app/private/security/google-crawler-ranges.json'),
    'crawler_ranges_max_age_hours' => 48,
];
