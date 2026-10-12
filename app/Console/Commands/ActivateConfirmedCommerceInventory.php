<?php

namespace App\Console\Commands;

use App\Services\Catalogue;
use App\Services\Commerce\InitialInventory;
use App\Services\Commerce\OrderStore;
use Illuminate\Console\Command;
use Throwable;

final class ActivateConfirmedCommerceInventory extends Command
{
    protected $signature = 'homya:commerce-activate-inventory {--apply : Apply the reviewed 500-unit manifest to the existing zero-stock inventory} {--sha256= : Exact reviewed manifest digest}';

    protected $description = 'Prepare or apply the user-confirmed 500-unit commerce inventory activation.';

    public function handle(Catalogue $catalogue, InitialInventory $inventory, OrderStore $orders): int
    {
        $directory = storage_path('app/private/commerce');
        $path = $directory.'/activation-inventory-500.json';

        try {
            if ($this->option('apply')) {
                if (! is_file($path) || is_link($path)
                    || ! hash_equals(hash_file('sha256', $path), (string) $this->option('sha256'))) {
                    throw new \RuntimeException;
                }
                $manifest = json_decode(file_get_contents($path), true, 64, JSON_THROW_ON_ERROR);
                if (($manifest['version'] ?? null) !== 3 || ($manifest['source'] ?? null) !== 'user_confirmed_initial_quantity_500') {
                    throw new \RuntimeException;
                }
                $summary = $inventory->activateExisting($manifest, $catalogue, $orders);
                $this->line(json_encode($summary + ['applied' => true], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

                return self::SUCCESS;
            }

            $manifest = $inventory->prepareConfirmedActivation($catalogue);
            if (! is_dir($directory) && ! mkdir($directory, 0700, true)) {
                throw new \RuntimeException;
            }
            if (is_link($path) || file_exists($path)) {
                throw new \RuntimeException;
            }

            $previousMask = umask(0077);
            try {
                $stream = fopen($path, 'x');
            } finally {
                umask($previousMask);
            }
            if ($stream === false) {
                throw new \RuntimeException;
            }

            $bytes = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)."\n";
            try {
                if (fwrite($stream, $bytes) !== strlen($bytes)) {
                    throw new \RuntimeException;
                }
            } finally {
                fclose($stream);
            }

            $this->line(json_encode($manifest['summary'] + ['applied' => false], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
            $this->line('Manifest SHA256: '.hash_file('sha256', $path));
            $this->line('SQL executed: no');

            return self::SUCCESS;
        } catch (Throwable) {
            $this->error('Inventory activation refused or incomplete. No private diagnostics displayed.');

            return self::FAILURE;
        }
    }
}
