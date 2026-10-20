<?php

// Local PHP CLI server only. Never expose source/configuration or execute uploads.
if (PHP_SAPI !== 'cli-server') {
    http_response_code(404);
    exit;
}

$public = realpath(__DIR__.'/../public');
$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/');
$denied = preg_match('~(?:^|/)(?:\.|app(?:/|$)|bootstrap(?:/|$)|config(?:/|$)|database(?:/|$)|vendor(?:/|$)|node_modules(?:/|$)|scripts(?:/|$)|tests(?:/|$)|output(?:/|$)|docs(?:/|$))~i', $path)
    || preg_match('~\.(?:php[0-9]*|phps|pht|phtml|phar|sql|sqlite3?|db|log|env|ini|bak|backup|orig|old|save|pem|key|sh|py)(?:[./]|$)~i', $path)
    || preg_match('~\A/(?:artisan|composer\.(?:json|lock)|package(?:-lock)?\.json|phpunit\.xml|vite\.config\.[^/]+|storage)(?:/|$)~i', $path)
    || str_contains($path, "\0") || str_contains($path, '\\');
if ($denied && $path !== '/index.php') {
    http_response_code(403);
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    exit;
}

$file = realpath($public.$path);
if ($path !== '/' && $path !== '/index.php' && $path !== '/robots.txt'
    && $file !== false && str_starts_with($file, $public.DIRECTORY_SEPARATOR) && is_file($file)
    && preg_match('~\.(?:css|js|map|png|jpe?g|gif|webp|avif|svg|ico|woff2?|ttf|otf|pdf|txt|xml|json)\z~i', $file)) {
    return false;
}

require $public.'/index.php';
