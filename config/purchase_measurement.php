<?php

return [
    // Internal paid-purchase ledger only. No browser tag or Google transmission is enabled here.
    'enabled' => env('HOMYA_PAID_PURCHASE_MEASUREMENT_ENABLED', false),
    'start_date' => env('HOMYA_PAID_PURCHASE_MEASUREMENT_START_DATE', ''),
];
