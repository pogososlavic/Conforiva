<?php

namespace App\Console\Commands;

use App\Services\Ga4Purchases;
use Illuminate\Console\Command;

final class SendGa4Purchases extends Command
{
    protected $signature = 'homya:ga4-purchases {--apply : Submit verified, consented purchases} {--validate : Send to the Google validation endpoint without recording a conversion}';

    protected $description = 'Preview GA4 eligible receipts; no network unless --apply or --validate is requested.';

    public function handle(Ga4Purchases $purchases): int
    {
        try {
            if ($this->option('apply') && $this->option('validate')) {
                throw new \RuntimeException;
            }
            $summary = $purchases->dispatch((bool) $this->option('apply'), (bool) $this->option('validate'));
            $this->line(json_encode($summary, JSON_THROW_ON_ERROR));

            return $summary['unknown'] ? self::FAILURE : self::SUCCESS;
        } catch (\Throwable) {
            $this->error('GA4 measurement unavailable. Check configuration and schema; no private details displayed.');

            return self::FAILURE;
        }
    }
}
