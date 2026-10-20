<?php

/** One-time recovery of new HOMYA schemas. No DROP, purge, or synthetic commerce rows. */

use App\Services\CatalogueArchivePayload;
use App\Services\StrictJson;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

require dirname(__DIR__).'/vendor/autoload.php';
$root = dirname(__DIR__);
$run = $root.'/storage/app/private/database-backups/recovery-20261019';
$phase = 'preflight';
$resume = ($argv[1] ?? '') === '--resume-migrations';
$report = ['phase' => $phase, 'restored' => [], 'commercial_data_recovered' => false, 'media_files_recovered' => false];

function saveRecoveryReport(string $run, array $report): void
{
    $path = $run.'/restore-progress.json';
    file_put_contents($path, json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    chmod($path, 0600);
}

function recoveryRowHash(array $row): string
{
    $text = '';
    foreach ($row as $value) {
        $text .= $value === null ? 'N;' : 'B'.strlen((string) $value).':'.(string) $value.';';
    }

    return hash('sha256', $text);
}

try {
    if (PHP_SAPI !== 'cli' || ! is_file($run.'/access-recovered.json') || is_file($run.'/restore-result.json')) {
        throw new RuntimeException('RECOVERY_CONTEXT_INVALID');
    }
    $source = $root.'/u429557141_catalogue.20261018180146.sql';
    $bundle = json_decode(file_get_contents($run.'/bundle.json'), true, 512, JSON_THROW_ON_ERROR);
    if (hash_file('sha256', $source) !== $bundle['source_sha256']) {
        throw new RuntimeException('RECOVERED_SOURCE_CHANGED');
    }
    $access = json_decode(file_get_contents($run.'/accounts.json'), true, 512, JSON_THROW_ON_ERROR);
    $pdo = new PDO('mysql:unix_socket=/Applications/XAMPP/xamppfiles/var/mysql/mysql.sock;charset=utf8mb4', 'homya_recovery_schema', $access['homya_recovery_schema'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_STRINGIFY_FETCHES => false,
    ]);
    $server = $pdo->query('SELECT VERSION() version, @@datadir datadir, @@socket socket, @@port port, @@max_allowed_packet packet, CURRENT_USER() identity')->fetch(PDO::FETCH_ASSOC);
    if ($server['version'] !== '10.4.28-MariaDB' || $server['datadir'] !== '/Applications/XAMPP/xamppfiles/var/mysql/'
        || $server['socket'] !== '/Applications/XAMPP/xamppfiles/var/mysql/mysql.sock' || (int) $server['packet'] < 16777216 || $server['identity'] !== 'homya_recovery_schema@localhost') {
        throw new RuntimeException('XAMPP_TARGET_INVALID');
    }
    $pdo->exec("SET SESSION sql_mode='STRICT_ALL_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");
    $pdo->exec("SET SESSION time_zone='+00:00'");
    $pdo->exec('SET SESSION foreign_key_checks=1, check_constraint_checks=1');
    $targets = ['homya_restore_op07', 'homya_test', 'homya_local'];
    if ($resume) {
        $prior = json_decode(file_get_contents($run.'/restore-progress.json'), true, 512, JSON_THROW_ON_ERROR);
        if (($prior['phase'] ?? '') !== 'migrate_missing_tables' || count($prior['restored'] ?? []) !== 3) {
            throw new RuntimeException('RESUME_STATE_INVALID');
        }
    }
    foreach ($targets as $target) {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=?');
        $stmt->execute([$target]);
        if (! $resume && (int) $stmt->fetchColumn() !== 0) {
            throw new RuntimeException('TARGET_NOT_EMPTY');
        }
    }
    if (is_dir($root.'/storage/app/catalogue')) {
        throw new RuntimeException('FILE_CATALOGUE_ALREADY_EXISTS');
    }
    $envBefore = file_get_contents($root.'/.env');
    if (! $resume && is_file($run.'/.env.before')) {
        throw new RuntimeException('ENV_BACKUP_ALREADY_EXISTS');
    }
    if ($resume) {
        if (file_get_contents($run.'/.env.before') !== $envBefore) {
            throw new RuntimeException('ENV_CHANGED_SINCE_STOP');
        }
    } else {
        file_put_contents($run.'/.env.before', $envBefore, LOCK_EX);
        chmod($run.'/.env.before', 0600);
    }
    $report['source_sha256'] = $bundle['source_sha256'];
    $report['server'] = $server;
    saveRecoveryReport($run, $report);
    foreach ($targets as $target) {
        $phase = 'restore_'.$target;
        $report['phase'] = $phase;
        saveRecoveryReport($run, $report);
        $pdo->exec('USE `'.$target.'`');
        foreach ($bundle['table_order'] as $table) {
            if (! $resume) {
                $pdo->exec($bundle['ddl'][$table]);
            }
        }
        $pdo->beginTransaction();
        $expectedRows = [];
        foreach ($bundle['table_order'] as $table) {
            $rows = $bundle['rows'][$table];
            if ($target === 'homya_test' && $table !== 'migrations') {
                $rows = [];
            } elseif ($target === 'homya_local' && $table !== 'migrations') {
                $rows = array_values(array_filter($rows, fn ($row) => $row[0] === $bundle['lot_key']));
            }
            $columns = $bundle['columns'][$table];
            $sql = 'INSERT INTO `'.$table.'` (`'.implode('`,`', $columns).'`) VALUES ('.implode(',', array_fill(0, count($columns), '?')).')';
            $insert = $pdo->prepare($sql);
            foreach ($rows as $row) {
                if (! $resume) {
                    $insert->execute($row);
                }
            }
            $expectedRows[$table] = $rows;
        }
        // Verify every value before committing the restored data.
        $counts = [];
        $hashes = [];
        foreach ($bundle['table_order'] as $table) {
            $where = $resume && $target === 'homya_local' && $table === 'migrations' ? ' WHERE id<=4' : '';
            $actual = $pdo->query('SELECT `'.implode('`,`', $bundle['columns'][$table]).'` FROM `'.$table.'`'.$where)->fetchAll(PDO::FETCH_NUM);
            $before = array_map('recoveryRowHash', $expectedRows[$table]);
            $after = array_map('recoveryRowHash', $actual);
            sort($before, SORT_STRING);
            sort($after, SORT_STRING);
            if ($before !== $after) {
                throw new RuntimeException('RESTORED_ROWS_MISMATCH');
            }
            $counts[$table] = count($actual);
            $hashes[$table] = hash('sha256', implode('', $after));
        }
        $pdo->commit();
        $report['restored'][$target] = ['rows' => $counts, 'rows_sha256' => $hashes];
        saveRecoveryReport($run, $report);
    }
    unset($expectedRows, $bundle['rows']);
    $phase = 'verify_business_payload';
    $pdo->exec('USE `homya_local`');
    $lot = $pdo->query('SELECT * FROM archive_lots')->fetch(PDO::FETCH_ASSOC);
    $categories = $pdo->query('SELECT * FROM archive_categories')->fetchAll(PDO::FETCH_ASSOC);
    $products = $pdo->query('SELECT * FROM archive_products ORDER BY index_position')->fetchAll(PDO::FETCH_ASSOC);
    $translations = $pdo->query('SELECT * FROM archive_translations ORDER BY locale, source_position')->fetchAll(PDO::FETCH_ASSOC);
    $app = require $root.'/bootstrap/app.php';
    $app->make(Kernel::class)->bootstrap();
    $payload = CatalogueArchivePayload::fromStoredRows($lot, $categories, $products, $translations);
    $payload->assertVerified($lot);
    $report['business_payload_sha256'] = $payload->digest();
    if ($report['business_payload_sha256'] !== '49811854d0b4a8da4e3cb7b3e66c99ed2f43ae9f3a71875c29073fa636ea37e1') {
        throw new RuntimeException('HISTORICAL_PAYLOAD_DIGEST_MISMATCH');
    }
    $archive = $payload->archive();
    unset($payload);
    $phase = 'migrate_missing_tables';
    $report['phase'] = $phase;
    saveRecoveryReport($run, $report);
    $connection = config('database.connections.mariadb');
    $connection['database'] = 'homya_local';
    $connection['username'] = 'homya_recovery_schema';
    $connection['password'] = $access['homya_recovery_schema'];
    $connection['port'] = (string) $server['port'];
    $connection['url'] = null;
    config(['database.default' => 'mariadb', 'database.connections.mariadb' => $connection, 'database.connections.commerce' => $connection]);
    DB::purge('mariadb');
    DB::purge('commerce');
    $migrations = array_merge(glob($root.'/database/migrations/*.php'), glob($root.'/database/migrations/commerce/*.php'));
    sort($migrations, SORT_STRING);
    $paths = array_map(fn ($path) => substr($path, strlen($root) + 1), $migrations);
    $code = Artisan::call('migrate', ['--database' => 'mariadb', '--path' => $paths, '--force' => true]);
    if ($code !== 0) {
        throw new RuntimeException('MIGRATIONS_FAILED');
    }
    $report['applied_migrations'] = array_map('basename', $migrations);
    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    $commerceTables = array_values(array_filter($tables, fn ($name) => str_starts_with($name, 'commerce_')));
    if (count($tables) !== 27 || count($commerceTables) !== 14) {
        throw new RuntimeException('FINAL_TABLE_INVENTORY_MISMATCH');
    }
    foreach ($commerceTables as $table) {
        if ((int) $pdo->query('SELECT COUNT(*) FROM `'.$table.'`')->fetchColumn() !== 0) {
            throw new RuntimeException('UNEXPECTED_COMMERCIAL_DATA');
        }
        $pdo->exec('GRANT SELECT,INSERT,UPDATE,DELETE ON `homya_local`.`'.$table."` TO 'homya_local_commerce'@'localhost'");
    }
    foreach (array_filter($bundle['table_order'], fn ($table) => $table !== 'migrations') as $table) {
        $pdo->exec('GRANT SELECT ON `homya_local`.`'.$table."` TO 'homya_local_reader'@'localhost'");
    }
    foreach (['users', 'password_reset_tokens', 'sessions', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs'] as $table) {
        $pdo->exec('GRANT SELECT,INSERT,UPDATE,DELETE ON `homya_local`.`'.$table."` TO 'homya_local_app'@'localhost'");
    }
    $pdo->exec("GRANT SELECT ON `homya_local`.`migrations` TO 'homya_local_app'@'localhost'");
    $report['table_counts'] = [];
    foreach ($tables as $table) {
        $report['table_counts'][$table] = (int) $pdo->query('SELECT COUNT(*) FROM `'.$table.'`')->fetchColumn();
    }
    $phase = 'rebuild_file_catalogue';
    $stage = $run.'/catalogue-rebuilt';
    if (file_exists($stage)) {
        throw new RuntimeException('CATALOGUE_STAGE_EXISTS');
    }
    mkdir($stage, 0700);
    mkdir($stage.'/products', 0700);
    mkdir($stage.'/localizations', 0700);
    $write = static function (string $path, mixed $value): void {
        if (file_exists($path) || file_put_contents($path, StrictJson::encode($value), LOCK_EX) === false) {
            throw new RuntimeException('FILE_RECONSTRUCTION_FAILED');
        }
        chmod($path, 0600);
    };
    $write($stage.'/index.json', $archive->index);
    $write($stage.'/inventory.json', $archive->inventory);
    $write($stage.'/media-overrides.json', $archive->media_overrides);
    foreach ($products as $row) {
        if (! preg_match('/^[a-z0-9][a-z0-9-]{0,220}$/D', $row['slug'])) {
            throw new RuntimeException('PRODUCT_PATH_INVALID');
        }
        $write($stage.'/products/'.$row['slug'].'.json', StrictJson::decode($row['detail_document'], false));
    }
    foreach (['en', 'de'] as $locale) {
        $dictionary = clone $archive->localization_metadata->{$locale};
        $dictionary->strings = new stdClass;
        foreach ($translations as $row) {
            if ($row['locale'] === $locale) {
                $dictionary->strings->{$row['source_text']} = $row['translated_text'];
            }
        }
        $write($stage.'/localizations/'.$locale.'.json', $dictionary);
    }
    if (! rename($stage, $root.'/storage/app/catalogue')) {
        throw new RuntimeException('CATALOGUE_PUBLICATION_FAILED');
    }
    $phase = 'configure_local_application';
    if (file_get_contents($root.'/.env') !== $envBefore) {
        throw new RuntimeException('ENV_CHANGED_CONCURRENTLY');
    }
    $changes = [
        'DB_CONNECTION' => 'mariadb', 'DB_DATABASE' => 'homya_local', 'DB_HOST' => '127.0.0.1',
        'DB_SOCKET' => $server['socket'], 'DB_PORT' => (string) $server['port'],
        'DB_USERNAME' => 'homya_local_app', 'DB_PASSWORD' => $access['homya_local_app'],
        'HOMYA_COMMERCE_DB_HOST' => '127.0.0.1', 'HOMYA_COMMERCE_DB_PORT' => (string) $server['port'],
        'HOMYA_COMMERCE_DB_SOCKET' => $server['socket'], 'HOMYA_COMMERCE_DB_DATABASE' => 'homya_local',
        'HOMYA_COMMERCE_DB_USERNAME' => 'homya_local_commerce', 'HOMYA_COMMERCE_DB_PASSWORD' => $access['homya_local_commerce'],
        'HOMYA_CATALOGUE_SOURCE' => 'files', 'HOMYA_CHECKOUT_ENABLED' => 'false',
        'HOMYA_DEFERRED_ORDERS_ENABLED' => 'false', 'HOMYA_INVENTORY_ENABLED' => 'false',
    ];
    $updated = $envBefore;
    foreach ($changes as $key => $value) {
        $pattern = '/^'.preg_quote($key, '/').'=.*$/m';
        $count = preg_match_all($pattern, $updated);
        if ($count > 1) {
            throw new RuntimeException('DUPLICATE_ENV_KEY');
        }
        $line = $key.'='.$value;
        $updated = $count === 1 ? preg_replace($pattern, $line, $updated) : rtrim($updated)."\n".$line."\n";
    }
    $temporary = $root.'/.env.recovery-new';
    if (file_exists($temporary)) {
        throw new RuntimeException('ENV_STAGE_EXISTS');
    }
    file_put_contents($temporary, $updated, LOCK_EX);
    chmod($temporary, 0600);
    if (! rename($temporary, $root.'/.env')) {
        throw new RuntimeException('ENV_UPDATE_FAILED');
    }
    $report['environment_keys_updated'] = array_keys($changes);
    $report['catalogue_source'] = 'files';
    $report['phase'] = 'complete';
    $report['completed_at_utc'] = gmdate('c');
    saveRecoveryReport($run, $report);
    file_put_contents($run.'/restore-result.json', json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    chmod($run.'/restore-result.json', 0600);
    echo json_encode(['status' => 'complete', 'databases' => array_keys($report['restored']),
        'local_tables' => count($tables), 'business_products' => count($products),
        'payload_sha256' => $report['business_payload_sha256'], 'commercial_rows' => 0], JSON_THROW_ON_ERROR).PHP_EOL;
} catch (Throwable $error) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $report['phase'] = $phase;
    $report['status'] = 'stopped';
    $report['error_class'] = get_class($error);
    $report['error_code'] = $error->getCode();
    $report['error_file'] = basename($error->getFile()).':'.$error->getLine();
    // Application/PDO messages can contain data or credentials: never echo them.
    $message = $error->getMessage();
    $report['reason'] = preg_match('/^[A-Z_]+$/D', $message) ? $message : 'ERROR_DETAILS_WITHHELD';
    $safeDiagnostic = strtok($message, "\n");
    $safeDiagnostic = explode('(Connection:', $safeDiagnostic)[0];
    // DDL/permission diagnostics contain no application values in this recovery.
    if ($phase === 'migrate_missing_tables') {
        $report['migration_diagnostic'] = substr($safeDiagnostic, 0, 350);
    }
    saveRecoveryReport($run, $report);
    fwrite(STDERR, 'RECOVERY_STOPPED '.$phase.' '.$report['reason'].PHP_EOL);
    exit(1);
}
