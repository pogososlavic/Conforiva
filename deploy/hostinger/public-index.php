<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Optional front controller when Hostinger imposes public_html. Install as
// public_html/index.php only after reviewing the sibling private directory.
$applicationRoot = dirname(__DIR__).'/home-confort/current';

if (! is_file($applicationRoot.'/vendor/autoload.php') || ! is_file($applicationRoot.'/bootstrap/app.php')) {
    http_response_code(503);
    exit('Service unavailable.');
}

if (file_exists($maintenance = $applicationRoot.'/storage/framework/maintenance.php')) {
    require $maintenance;
}

require $applicationRoot.'/vendor/autoload.php';
/** @var Application $app */
$app = require_once $applicationRoot.'/bootstrap/app.php';
$app->usePublicPath(__DIR__);
$app->handleRequest(Request::capture());
