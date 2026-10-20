<?php

/** Local developer migrations using the private schema account; dry-run by default. */

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

try {
    if (PHP_SAPI !== 'cli' || ! app()->isLocal() || app()->configurationIsCached()
        || count($argv) > 2 || (isset($argv[1]) && $argv[1] !== '--apply')) {
        throw new RuntimeException('LOCAL_MIGRATION_CONTEXT_REQUIRED');
    }
    $path = storage_path('app/private/database-backups/recovery-20261019/schema.cnf');
    if (! is_file($path) || is_link($path) || (fileperms($path) & 0777) !== 0600 || fileowner($path) !== posix_geteuid()) {
        throw new RuntimeException('PRIVATE_SCHEMA_ACCESS_REQUIRED');
    }
    $access = parse_ini_file($path, true, INI_SCANNER_RAW)['client'];
    if (($access['user'] ?? '') !== 'homya_recovery_schema' || ! preg_match('/^[a-f0-9]{64}$/D', $access['password'] ?? '')
        || ($access['socket'] ?? '') !== config('homya.xampp_socket')) {
        throw new RuntimeException('SCHEMA_ACCESS_TARGET_INVALID');
    }
    $config = config('database.connections.mariadb');
    $config = array_replace($config, ['database' => 'homya_local', 'host' => '127.0.0.1', 'url' => null,
        'username' => $access['user'], 'password' => $access['password'], 'unix_socket' => $access['socket']]);
    config(['database.default' => 'mariadb', 'database.connections.mariadb' => $config, 'database.connections.commerce' => $config]);
    DB::purge('mariadb');
    DB::purge('commerce');
    $server = DB::connection('mariadb')->selectOne('SELECT DATABASE() AS db, CURRENT_USER() AS identity, @@datadir AS datadir, @@socket AS socket');
    if ($server->db !== 'homya_local' || $server->identity !== 'homya_recovery_schema@localhost'
        || $server->datadir !== config('homya.xampp_datadir') || $server->socket !== config('homya.xampp_socket')) {
        throw new RuntimeException('LOCAL_XAMPP_TARGET_INVALID');
    }
    $code = Artisan::call('migrate', ['--database' => 'mariadb', '--path' => ['database/migrations', 'database/migrations/commerce'],
        '--pretend' => ($argv[1] ?? '') !== '--apply', '--force' => true]);
    echo Artisan::output();
    exit($code);
} catch (Throwable) {
    fwrite(STDERR, "LOCAL_MIGRATION_REFUSED: verify the local schema access and migration in a private terminal.\n");
    exit(1);
}
