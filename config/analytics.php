<?php

return [
    'enabled' => env('HOMYA_GA4_ENABLED', false),
    'measurement_id' => env('HOMYA_GA4_MEASUREMENT_ID', 'G-MW0CXWV7F9'),
    'gtm_container_id' => env('HOMYA_GTM_CONTAINER_ID', ''),
    'gtm_manages_ga4' => env('HOMYA_GTM_MANAGES_GA4', false),
    'api_secret' => env('HOMYA_GA4_API_SECRET', ''),
];
