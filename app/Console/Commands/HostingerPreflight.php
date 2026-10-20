<?php

namespace App\Console\Commands;

use App\Services\Admin\Messages;
use App\Services\ProductionSecurity;
use Illuminate\Console\Command;

final class HostingerPreflight extends Command
{
    protected $signature = 'homya:hostinger-preflight {--json}';

    protected $description = 'Read-only Hostinger readiness checks; no network, database or migrations';

    public function handle(ProductionSecurity $security): int
    {
        $checks = $security->checks();
        // Symfony versions pinned in composer.lock require this, beyond Laravel's own minimum.
        $checks['php_matches_locked_dependencies'] = PHP_VERSION_ID >= 80401;
        $checks['catalogue_files_source'] = config('catalogue.source') === 'files';
        $catalogue = json_decode(@file_get_contents(config('catalogue.archive_path')) ?: '', true);
        $checks['catalogue_files_present'] = is_array($catalogue) && ($catalogue['meta']['complete'] ?? false) === true
            && is_array($catalogue['products'] ?? null) && count($catalogue['products']) > 0;
        $manifest = json_decode(@file_get_contents(public_path('build/manifest.json')) ?: '', true);
        $checks['vite_assets_present'] = is_array($manifest) && isset($manifest['resources/css/app.css'], $manifest['resources/js/app.js']);
        foreach (is_array($manifest) ? $manifest : [] as $entry) {
            foreach (array_merge([$entry['file'] ?? ''], $entry['css'] ?? []) as $asset) {
                $checks['vite_assets_present'] = $checks['vite_assets_present'] && $asset !== ''
                    && ! str_contains($asset, '..') && is_file(public_path('build/'.$asset));
            }
        }
        $commerce = config('commerce.inventory_enabled') || config('commerce.enabled') || config('dashboard.enabled');
        $db = config('database.connections.commerce', []);
        $checks['commerce_target_not_xampp'] = ! $commerce || (($db['unix_socket'] ?? '') === ''
            && ! empty($db['database']) && ! empty($db['username'])
            && ($db['username'] ?? '') !== 'root' && ! str_ends_with($db['username'] ?? '', '_reader'));
        $checks['mail_transport_ready_for_checkout'] = ! config('commerce.enabled')
            || app(Messages::class)->emailReady();
        $checks['security_log_private'] = config('logging.channels.security.permission') === 0600;
        $passed = ! in_array(false, $checks, true);
        $result = ['configuration_passed' => $passed, 'checks' => $checks,
            'external_checks_required' => ['web_document_root', 'tls', 'database_grants_and_schema', 'smtp_delivery', 'backup_restore', 'media_transfer', 'catalogue_review']];
        $this->line(json_encode($result, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));

        return $passed ? self::SUCCESS : self::FAILURE;
    }
}
