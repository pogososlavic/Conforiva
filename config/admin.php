<?php

return [
    // Remote administration stays disabled unless it is explicitly enabled in production.
    'remote_enabled' => env('HOMYA_ADMIN_REMOTE_ENABLED', false),
    'username' => env('HOMYA_ADMIN_USERNAME', ''),
    'password_hash' => env('HOMYA_ADMIN_PASSWORD_HASH', ''),
    'idle_seconds' => 1800,
    'absolute_seconds' => 28800,
];
