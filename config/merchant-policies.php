<?php

return [
    // Existing published commitments, checked against /fr/livraison and
    // /fr/retractation on 2026-10-23. Keep these in sync with the policy pages.
    // Destinations, currency and shipping price come from commerce.php.
    'shipping' => [
        'verified' => true,
        'handling_days' => ['min' => 1, 'max' => 2],
        'transit_days' => ['min' => 2, 'max' => 5],
    ],
    'returns' => [
        'verified' => true,
        'days' => 14,
        'free' => true,
    ],
];
