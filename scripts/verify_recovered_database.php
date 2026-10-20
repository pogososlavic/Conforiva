<?php

/** Read-only checks of this machine's restored HOMYA databases and application. */

use App\Services\Catalogue;
use App\Services\Commerce\OrderStore;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$run = storage_path('app/private/database-backups/recovery-20261019');
$result = json_decode(file_get_contents($run.'/restore-result.json'), true, 512, JSON_THROW_ON_ERROR);
$access = json_decode(file_get_contents($run.'/accounts.json'), true, 512, JSON_THROW_ON_ERROR);
$report = [];

try {
    if (! app()->isLocal() || config('catalogue.source') !== 'files') {
        throw new RuntimeException('LOCAL_FILES_CONTEXT_REQUIRED');
    }
    $dsn = 'mysql:unix_socket=/Applications/XAMPP/xamppfiles/var/mysql/mysql.sock;charset=utf8mb4';
    $pdo = new PDO($dsn, 'homya_recovery_schema', $access['homya_recovery_schema'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    foreach (['homya_local' => 27, 'homya_test' => 5, 'homya_restore_op07' => 5] as $schema => $expected) {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=?');
        $stmt->execute([$schema]);
        if ((int) $stmt->fetchColumn() !== $expected) {
            throw new RuntimeException('TABLE_COUNT_MISMATCH');
        }
        $report['tables'][$schema] = $expected;
    }
    $pdo->exec('USE homya_local');
    foreach ($result['table_counts'] as $table => $count) {
        if ((int) $pdo->query('SELECT COUNT(*) FROM `'.$table.'`')->fetchColumn() !== $count) {
            throw new RuntimeException('ROW_COUNTS_CHANGED');
        }
    }
    $checks = $pdo->query("SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA='homya_local' AND TABLE_NAME LIKE 'archive_%' AND CONSTRAINT_TYPE='CHECK'")->fetchColumn();
    if ((int) $checks !== 27) {
        throw new RuntimeException('ARCHIVE_CHECK_CONSTRAINTS_MISMATCH');
    }
    $report['archive_checks'] = (int) $checks;
    $column = $pdo->query("SELECT COLUMN_DEFAULT,IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='homya_local' AND TABLE_NAME='commerce_paid_purchases' AND COLUMN_NAME='created_at'")->fetch(PDO::FETCH_ASSOC);
    if (strtolower((string) $column['COLUMN_DEFAULT']) !== 'current_timestamp()' || $column['IS_NULLABLE'] !== 'NO') {
        throw new RuntimeException('STRICT_TIMESTAMP_DEFAULT_INVALID');
    }
    $report['timestamp_default'] = 'current_timestamp(), NOT NULL';
    $report['app_identity'] = DB::connection()->selectOne('SELECT CURRENT_USER() AS identity')->identity;
    $commerce = app(OrderStore::class)->connection();
    $report['commerce_identity'] = $commerce->selectOne('SELECT CURRENT_USER() AS identity')->identity;
    if ($report['app_identity'] !== 'homya_local_app@localhost' || $report['commerce_identity'] !== 'homya_local_commerce@localhost') {
        throw new RuntimeException('APPLICATION_IDENTITY_MISMATCH');
    }
    $report['commerce_orders'] = $commerce->table('commerce_orders')->count();
    $report['commerce_inventory'] = $commerce->table('commerce_inventory')->count();
    if ($report['commerce_orders'] !== 0 || $report['commerce_inventory'] !== 0) {
        throw new RuntimeException('UNEXPECTED_COMMERCIAL_ROWS');
    }
    $reader = new PDO($dsn.';dbname=homya_local', 'homya_local_reader', $access['homya_local_reader'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $report['reader_products'] = (int) $reader->query('SELECT COUNT(*) FROM archive_products')->fetchColumn();
    try {
        $reader->query('SELECT * FROM commerce_orders LIMIT 0');
        throw new RuntimeException('READER_ACCESS_TOO_BROAD');
    } catch (PDOException $error) {
        if (($error->errorInfo[1] ?? null) !== 1142) {
            throw $error;
        }
        $report['reader_commerce_refused'] = true;
    }
    foreach (['fr', 'en', 'de'] as $locale) {
        app()->setLocale($locale);
        $catalogue = new Catalogue;
        $count = $catalogue->products()->count();
        if ($count !== 3709 || $catalogue->sourceUnavailable() || $catalogue->translationUnavailable()) {
            throw new RuntimeException('LOCALIZED_CATALOGUE_UNAVAILABLE');
        }
        $first = $catalogue->products()->first();
        if ($catalogue->product($first['slug']) === null) {
            throw new RuntimeException('LOCALIZED_PRODUCT_UNAVAILABLE');
        }
        $report['catalogue'][$locale] = ['products' => $count, 'sample_slug' => $first['slug']];
    }
    $report['source_sha256'] = hash_file('sha256', base_path('u429557141_catalogue.20261018180146.sql'));
    if ($report['source_sha256'] !== $result['source_sha256']) {
        throw new RuntimeException('SOURCE_DUMP_CHANGED');
    }
    $report['status'] = 'passed';
    $report['verified_at_utc'] = gmdate('c');
    file_put_contents($run.'/verification.json', json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    chmod($run.'/verification.json', 0600);
    echo json_encode($report, JSON_THROW_ON_ERROR).PHP_EOL;
} catch (Throwable $error) {
    $report['status'] = 'failed';
    $report['reason'] = preg_match('/^[A-Z_]+$/D', $error->getMessage()) ? $error->getMessage() : get_class($error);
    file_put_contents($run.'/verification.json', json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    chmod($run.'/verification.json', 0600);
    fwrite(STDERR, 'RECOVERY_VERIFICATION_FAILED '.$report['reason'].PHP_EOL);
    exit(1);
}
