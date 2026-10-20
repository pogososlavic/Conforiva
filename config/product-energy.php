<?php

// Previously audited documents remain the fallback; registry contains exact-model verified additions.
$existing = [
    'froid-rb33b610esa-samsung-combines-congelateur-en-bas' => [
        'reference' => 'RB33B610ESA', 'class' => 'E', 'scale' => 'A–G', 'color' => '#FFB900',
        'eprel' => '1293184',
        'label' => '/media/energy/samsung-rb33b610esa-label.pdf',
        'image' => '/media/energy/samsung-rb33b610esa-label.png',
        'sheets' => [
            'fr' => '/media/energy/samsung-rb33b610esa-sheet-fr.pdf',
            'en' => '/media/energy/samsung-rb33b610esa-sheet-en.pdf',
            'de' => '/media/energy/samsung-rb33b610esa-sheet-de.pdf',
        ],
    ],
    'lg-f14r15whs-lave-linge' => [
        'reference' => 'F14R15WHS', 'class' => 'A', 'scale' => 'A–G', 'color' => '#00A651',
        'eprel' => '1673637',
        'label' => '/media/energy/lg-f14r15whs-label.pdf',
        'image' => '/media/energy/lg-f14r15whs-label.png',
        'sheets' => [
            'fr' => '/media/energy/lg-f14r15whs-sheet-fr.pdf',
            'en' => '/media/energy/lg-f14r15whs-sheet-en.pdf',
            'de' => '/media/energy/lg-f14r15whs-sheet-de.pdf',
        ],
    ],
];

$path = resource_path('data/product-energy-labels.json');
$verified = is_file($path) && ! is_link($path) && filesize($path) <= 4 * 1024 * 1024
    ? json_decode((string) file_get_contents($path), true) : [];

return is_array($verified) ? array_replace($existing, $verified) : $existing;
