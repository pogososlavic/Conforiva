<?php

return [
    // This separate, owner-requested feed never opens the general catalogue feed.
    'enabled' => env('HOMYA_MERCHANT_SELECTION_ENABLED', true),
    'public_origin' => 'https://home-confort.org',
    'manifest_path' => resource_path('data/merchant-selection-150.json'),
    'manifest_sha256' => '06acf9df785da6a36d126bb3e2ffc777a89a9ecbd18563680d711d7beed4dd18',
    'template_path' => resource_path('data/merchant-selection-150.xml'),
    'template_sha256' => '421772030a7f54330be28eca7aca4dc79abb6d20688d4a985e54c487b382a4ff',
];
