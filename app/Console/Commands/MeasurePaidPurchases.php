<?php

namespace App\Console\Commands;

use App\Services\Commerce\PaidPurchases;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

final class MeasurePaidPurchases extends Command
{
    protected $signature = 'homya:measure-paid-purchases {--from= : Inclusive UTC date YYYY-MM-DD} {--to= : Exclusive UTC date YYYY-MM-DD} {--scheduled : Use the configured start date through today, only when enabled} {--apply : Record immutable internal purchase snapshots; no Google transmission}';

    protected $description = 'Measure only verified bank receipts. Read-only by default; never sends Google conversions.';

    public function handle(PaidPurchases $purchases): int
    {
        try {
            $from = (string) $this->option('from');
            $to = (string) $this->option('to');
            if ($this->option('scheduled')) {
                if (config('purchase_measurement.enabled') !== true || $from !== '' || $to !== '') {
                    throw new \RuntimeException;
                }
                $from = (string) config('purchase_measurement.start_date');
                $to = Carbon::now('UTC')->addDay()->format('Y-m-d');
            }
            foreach ([$from, $to] as $date) {
                if (! preg_match('/\A\d{4}-\d{2}-\d{2}\z/', $date) || Carbon::parse($date, 'UTC')->format('Y-m-d') !== $date) {
                    throw new \RuntimeException;
                }
            }
            if ($from >= $to) {
                throw new \RuntimeException;
            }
            $this->line(json_encode($purchases->reconcile($from.' 00:00:00', $to.' 00:00:00', (bool) $this->option('apply')), JSON_THROW_ON_ERROR));

            return self::SUCCESS;
        } catch (\Throwable) {
            $this->error('Purchase measurement unavailable: check the dates, schema and commerce configuration. No private details displayed.');

            return self::FAILURE;
        }
    }
}
