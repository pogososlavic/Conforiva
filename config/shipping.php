<?php

$address = 'Rue Aristote, Mont de Terre, Fives, 59000 Lille, France';
$structured = [
    'street' => 'Rue Aristote, Mont de Terre, Fives',
    'postal_code' => '59000',
    'city' => 'Lille',
    'country' => 'FR',
];

return [
    // Dispatch and receipt of returns confirmed by the owner on 2026-10-20.
    // These operational addresses do not replace the company's registered office.
    'dispatch' => ['verified' => true, 'reviewed_on' => '2026-10-20', 'address' => $address, 'address_structured' => $structured],
    'returns' => ['verified' => true, 'reviewed_on' => '2026-10-20', 'address' => $address, 'address_structured' => $structured],
];
