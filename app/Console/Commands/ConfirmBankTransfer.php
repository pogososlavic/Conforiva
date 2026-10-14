<?php

namespace App\Console\Commands;

use App\Services\Commerce\OrderStore;
use App\Services\PrivateLocalFiles;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Throwable;

final class ConfirmBankTransfer extends Command
{
    protected $signature = 'homya:confirm-bank-transfer {receipt : Absolute path to a private, merchant-verified JSON receipt} {--apply : Confirm a verified receipt and settle stock once}';

    protected $description = 'Preview or apply a bank receipt verified by HOMYA. Never accepts a customer declaration.';

    public function handle(OrderStore $orders): int
    {
        try {
            $receipt = json_decode($this->receiptBytes($this->argument('receipt')), true, 8, JSON_THROW_ON_ERROR);
            if (count($receipt) !== 5 || ! Str::isUuid($receipt['order_id'] ?? '') || ! is_int($receipt['amount_cents'] ?? null)
                || ($receipt['currency'] ?? '') !== 'EUR' || ($receipt['merchant_verified'] ?? false) !== true
                || ! is_string($receipt['bank_reference'] ?? null) || trim($receipt['bank_reference']) === '') {
                throw new \RuntimeException;
            }
            $order = $orders->connection()->table('commerce_orders')->where('id', $receipt['order_id'])->firstOrFail();
            if ((int) $order->total_cents !== $receipt['amount_cents']) {
                throw new \RuntimeException;
            }
            if ($this->option('apply')) {
                $orders->confirmBankReceipt($receipt['order_id'], $receipt['amount_cents'], trim($receipt['bank_reference']));
            }
            $this->line(json_encode(['receipt_amount_matches' => true, 'applied' => (bool) $this->option('apply')], JSON_THROW_ON_ERROR));

            return self::SUCCESS;
        } catch (Throwable) {
            $this->error('Bank receipt refused or unavailable. No private details displayed.');

            return self::FAILURE;
        }
    }

    private function receiptBytes(mixed $requested): string
    {
        $relative = 'app/private/commerce/receipts';
        $parent = PrivateLocalFiles::path(storage_path(), $relative);
        if (! is_string($requested) || ! str_starts_with($requested, '/')
            || ! in_array(dirname($requested), [storage_path($relative), $parent], true)) {
            throw new \RuntimeException;
        }
        $path = PrivateLocalFiles::path($parent, basename($requested));
        PrivateLocalFiles::requireMode($parent, 0700, true);
        PrivateLocalFiles::requireMode($path, 0600);
        $expected = lstat($path);
        $handle = @fopen($path, 'rb');
        if ($handle === false) {
            throw new \RuntimeException;
        }
        try {
            $stat = fstat($handle);
            // Inspect the opened file, including its identity and link count, before reading any bytes.
            if ($expected === false || $stat === false || $stat['dev'] !== $expected['dev'] || $stat['ino'] !== $expected['ino']
                || ($stat['mode'] & 0170000) !== 0100000 || ($stat['mode'] & 0777) !== 0600
                || $stat['uid'] !== posix_geteuid() || $stat['nlink'] !== 1 || $stat['size'] > 8192
                || PrivateLocalFiles::path(storage_path(), $relative.'/'.basename($requested)) !== $path) {
                throw new \RuntimeException;
            }
            $bytes = stream_get_contents($handle, 8193);
            if ($bytes === false || strlen($bytes) > 8192) {
                throw new \RuntimeException;
            }

            return $bytes;
        } finally {
            fclose($handle);
        }
    }
}
