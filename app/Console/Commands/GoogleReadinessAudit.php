<?php

namespace App\Console\Commands;

use App\Services\Catalogue;
use App\Services\FileCatalogueSource;
use App\Services\MerchantProducts;
use App\Services\Seo;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Throwable;

final class GoogleReadinessAudit extends Command
{
    protected $signature = 'homya:google-audit
        {--locale=fr : fr, en, de or all}
        {--json : Machine-readable report}
        {--offline : Read only the file archive, without administration or database access}
        {--with-inventory : Read the inventory once, without modifying orders or reservations}
        {--output= : Save reports and an unapproved review template in an output/ subdirectory}';

    protected $description = 'Audit catalogue and Merchant readiness; inventory is read only when explicitly requested';

    private const MAX_REPORT_BYTES = 64_000_000;

    public function handle(Catalogue $catalogue, MerchantProducts $merchant, Seo $seo): int
    {
        $locale = $this->option('locale');
        if ($locale !== 'all' && ! in_array($locale, config('homya.locales'), true)) {
            $this->error('Unsupported locale.');

            return self::FAILURE;
        }
        $directory = $this->option('output');
        if ($directory && ! $this->safeDirectory($directory)) {
            $this->error('Choose a relative directory under output/, without traversal or symbolic links.');

            return self::FAILURE;
        }
        $offline = (bool) $this->option('offline');
        $withInventory = (bool) $this->option('with-inventory');
        if ($offline && $withInventory) {
            $this->error('--offline cannot be combined with --with-inventory.');

            return self::FAILURE;
        }
        $originalLocale = app()->getLocale();
        $settings = config()->getMany(['catalogue.source', 'dashboard.enabled', 'commerce.inventory_enabled']);
        if ($offline) {
            config(['catalogue.source' => 'files', 'dashboard.enabled' => false, 'commerce.inventory_enabled' => false]);
            $catalogue = new Catalogue(new FileCatalogueSource);
            $merchant = new MerchantProducts($seo, $catalogue);
        }
        try {
            $reports = [];
            $templates = [];
            $previews = [];
            $reviewSnapshot = $this->reviewSnapshot();
            // Resolve the global gate before inspecting products; neither this nor
            // the optional inventory query expires orders or changes reservations.
            $globalChecks = $merchant->readinessChecks(! $offline);
            $inventory = null;
            if ($withInventory) {
                try {
                    $inventory = $merchant->inventoryRows();
                } catch (Throwable) {
                    $this->error('Inventory unavailable. No report was generated and no inventory was modified.');

                    return self::FAILURE;
                }
            }
            $locales = $locale === 'all' ? config('homya.locales') : [$locale];
            foreach ($locales as $language) {
                $report = $this->audit($catalogue, $merchant, $seo, $language, $offline, $reviewSnapshot,
                    $globalChecks, $inventory, $templates, $previews);
                if ($report === null) {
                    $this->error('Catalogue unavailable. For a database-free archive audit use --offline. An offline audit does not check administrative corrections.');

                    return self::FAILURE;
                }
                $reports[$language] = $report;
            }
            if ($directory) {
                $path = base_path($directory);
                File::ensureDirectoryExists($path, 0700);
                foreach ($reports as $language => $report) {
                    $this->writeJson($path.'/google-audit-'.$language.'.json', $report);
                }
                $this->writeJson($path.'/merchant-products-review-template.json', $templates);
                $this->writeCsv($path.'/products-to-complete.csv', $reports);
                $this->writeCsv($path.'/catalogue-incomplete.csv', $reports, true);
                $this->writeSelectionCsv($path.'/merchant-product-selection.csv', $reports);
                $this->writeSupplierCsv($path.'/supplier-data-to-complete.csv', $reports, $templates);
                foreach ($previews as $language => $items) {
                    if ($items !== []) {
                        $this->write($path.'/merchant-preview-'.$language.'.xml', $merchant->xml($items, $language));
                    }
                }
            }
            if ($this->option('json')) {
                $json = json_encode($locale === 'all' ? $reports : $reports[$locale], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                if (strlen($json) > self::MAX_REPORT_BYTES) {
                    throw new \RuntimeException('Report exceeds the local size limit. Audit fewer locales per command.');
                }
                $this->line($json);
            } else {
                foreach ($reports as $report) {
                    $this->info($report['products'].' products audited ('.$report['locale'].', '.$report['catalogue_scope'].'). '
                        .$report['data_ready'].' data-ready items; '.$report['currently_exportable'].' currently exportable. '
                        .($report['stock_checked'] ? 'Inventory read without changes. ' : 'Inventory not checked. ').'Google accounts not checked.');
                    $counts = $report['problem_counts'];
                    $this->table(['Problem', 'Products'], array_map(fn ($key, $count) => [$key, $count], array_keys($counts), $counts));
                }
                if ($directory) {
                    $this->info('Saved local reports, grouped product selection and supplier drafts with approved=false under '.$directory.'.');
                }
            }

            return self::SUCCESS;
        } finally {
            app()->setLocale($originalLocale);
            config($settings);
        }
    }

    private function audit(Catalogue $catalogue, MerchantProducts $merchant, Seo $seo, string $locale, bool $offline,
        array $reviewSnapshot, array $globalChecks, ?Collection $inventory, array &$templates, array &$previews): ?array
    {
        app()->setLocale($locale);
        $products = $catalogue->products();
        if ($catalogue->sourceUnavailable() || $catalogue->translationUnavailable() || $products->isEmpty()) {
            return null;
        }
        $reviews = $reviewSnapshot['reviews'];
        $rows = [];
        $counts = [];
        $warningCounts = [];
        $inventoryCounts = [];
        $dataReady = 0;
        $dataReadyProducts = 0;
        $exportable = 0;
        $exportableProducts = 0;
        $globalReady = $globalChecks !== [] && array_filter($globalChecks, fn ($value) => $value !== true) === [];
        $previews[$locale] = [];
        $completeProducts = [];
        $gtinOccurrences = [];
        foreach ($products as $summary) {
            $product = $catalogue->product($summary['slug']);
            if ($product === null) {
                return null;
            }
            $completeProducts[] = $product;
            if (! ($product['has_variants'] ?? true)) {
                foreach ($this->specificationGtins($product) as $gtin => $label) {
                    $gtinOccurrences[$gtin] = ($gtinOccurrences[$gtin] ?? 0) + 1;
                }
            }
        }
        foreach ($completeProducts as $product) {
            $review = $reviews[$product['slug']] ?? [];
            $identifierDraft = $this->identifierDraft($product, $review, $gtinOccurrences);
            $problems = array_values($merchant->problems($product, $review, $locale));
            $warnings = array_values(array_unique([...$merchant->warnings($product, $review), ...$identifierDraft['warnings']]));
            $stock = $inventory?->get($product['slug']);
            $inventoryProblems = $inventory === null ? null : $merchant->inventoryProblems($product, $stock);
            foreach ($problems as $problem) {
                $counts[$problem] = ($counts[$problem] ?? 0) + 1;
            }
            foreach ($warnings as $warning) {
                $warningCounts[$warning] = ($warningCounts[$warning] ?? 0) + 1;
            }
            foreach ($inventoryProblems ?? [] as $problem) {
                $inventoryCounts[$problem] = ($inventoryCounts[$problem] ?? 0) + 1;
            }
            $variantRows = [];
            $template = &$templates[$product['slug']];
            $template ??= $this->template($product, $review, false, $identifierDraft);
            $template['fingerprints'][$locale] = MerchantProducts::fingerprint($product);
            if ($seo->origin() !== null) {
                $template['source_urls'] = array_values(array_unique([...$template['source_urls'],
                    $seo->url('product', ['locale' => $locale, 'slug' => $product['slug']])]));
            }
            $eligibleItems = [];
            if ($product['has_variants']) {
                foreach ($merchant->variantProducts($product, $review, $locale) as [$variant, $variantReview]) {
                    $variantRows[] = ['id' => MerchantProducts::id($variant), 'variant' => $variant['variant_id'],
                        'title' => $variant['variant_title'], 'url_path' => route('product', ['locale' => $locale, 'slug' => $product['slug'], 'variant' => $variant['variant_id']], false),
                        'fingerprint' => MerchantProducts::fingerprint($variant), 'problems' => array_values($merchant->problems($variant, $variantReview, $locale)),
                        'warnings' => array_values($merchant->warnings($variant, $variantReview))];
                    $eligibleItems[] = [$variant, $variantReview];
                    $variantTemplate = &$template['variants'][$variant['variant_id']];
                    $variantTemplate ??= $this->template($variant, $variantReview, true);
                    $variantTemplate['option_titles'][$locale] = $variant['variant_title'];
                    $variantTemplate['attributes'][$locale] = $variant['variant_attributes'];
                    // Capture after the attributes and the exact variant image have been supplied.
                    // A current empty variant projection cannot be approved with this draft hash.
                    $variantTemplate['fingerprints'][$locale] = $variant['images'] !== [] && $variant['variant_attributes'] !== []
                        ? MerchantProducts::fingerprint($variant) : null;
                    unset($variantTemplate);
                }
            } else {
                $eligibleItems[] = [$product, $review];
            }
            unset($template);
            $isDataReady = $problems === [];
            $isExportable = $isDataReady && $inventoryProblems === [] && $globalReady;
            if ($isDataReady) {
                $dataReady += count($eligibleItems);
                $dataReadyProducts++;
                if ($inventoryProblems === []) {
                    // This is a local, protected preview, not a published feed.
                    foreach ($eligibleItems as [$item, $itemReview]) {
                        $previews[$locale][] = [$item, $itemReview, (int) $stock->quantity > (int) $stock->reserved];
                    }
                }
            }
            if ($isExportable) {
                $exportable += count($eligibleItems);
                $exportableProducts++;
            }
            $rows[] = ['slug' => $product['slug'], 'reference' => $product['reference'], 'name' => $product['name'],
                'brand' => $product['brand'] ?? null,
                'url_path' => route('product', ['locale' => $locale, 'slug' => $product['slug']], false),
                'product_type' => MerchantProducts::productType($product, $locale),
                'fingerprint' => MerchantProducts::fingerprint($product),
                'data_ready' => $isDataReady, 'currently_exportable' => $isExportable,
                'problems' => $problems, 'warnings' => $warnings, 'inventory_problems' => $inventoryProblems,
                'manufacturer_identifier_evidence' => $identifierDraft['evidence'],
                'catalogue_issues' => $product['issues'],
                'specification_reviews' => $product['specification_reviews'] ?? [], 'variants' => $variantRows];
        }
        if ($catalogue->sourceUnavailable() || $catalogue->translationUnavailable()) {
            return null;
        }
        ksort($counts);
        ksort($warningCounts);
        ksort($inventoryCounts);

        return ['checked_at' => now()->toIso8601String(), 'locale' => $locale, 'products' => count($rows),
            'catalogue_scope' => $offline ? 'file_archive' : 'application_catalogue',
            'catalogue_source' => config('catalogue.source'), 'administration_checked' => ! $offline && config('dashboard.enabled') === true,
            'indexing_configured' => $seo->enabled(),
            'global_checks' => $globalChecks, 'review_file_status' => $reviewSnapshot['status'],
            'merchant_configured' => $globalReady, 'stock_checked' => $inventory !== null, 'google_contacted' => false,
            'data_ready' => $dataReady, 'data_ready_products' => $dataReadyProducts,
            'currently_exportable' => $exportable, 'currently_exportable_products' => $exportableProducts,
            'counts_unit' => 'offer_items_including_variants', 'private_preview_items' => count($previews[$locale]),
            'problem_counts' => $counts, 'warning_counts' => $warningCounts,
            'inventory_problem_counts' => $inventoryCounts, 'items' => $rows];
    }

    private function template(array $product, array $review, bool $variant = false, ?array $identifierDraft = null): array
    {
        $manufacturer = ($product['manufacturer']['verified'] ?? false) === true && ! $variant ? $product['manufacturer'] : [];

        $template = ['approved' => false, 'fingerprints' => [], 'gtin' => $manufacturer['gtin'] ?? null,
            'mpn' => $manufacturer['mpn'] ?? null, 'identifier_exists' => null,
            'google_product_category' => null, 'energy_label_required' => null, 'eprel_code' => null,
            'allow_google_energy_lookup' => false,
            'essential_details_verified' => false, 'source_urls' => $this->publicSourceUrls(empty($manufacturer['source_url']) ? [] : [$manufacturer['source_url']]),
            'title_source' => $product['title_source'] ?? null, 'description_source' => $product['description_source'] ?? null];
        // Reuse the operator's existing evidence as a draft, never its approval.
        foreach (['gtin', 'mpn', 'google_product_category', 'eprel_code', 'title_source', 'description_source'] as $key) {
            if (isset($review[$key]) && (is_string($review[$key]) || is_int($review[$key]))) {
                $template[$key] = $review[$key];
            }
        }
        foreach (['identifier_exists', 'energy_label_required', 'essential_details_verified', 'allow_google_energy_lookup'] as $key) {
            if (is_bool($review[$key] ?? null)) {
                $template[$key] = $review[$key];
            }
        }
        if (isset($review['source_urls'])) {
            $template['source_urls'] = $this->publicSourceUrls($review['source_urls']);
        }
        $identifierDraft ??= $this->identifierDraft($product, $review, [], $variant);
        $template['gtin'] = $identifierDraft['gtin'];
        $template['mpn'] = $identifierDraft['mpn'];
        $template['manufacturer_identifier_evidence'] = $identifierDraft['evidence'];

        return $template + ($variant ? ['image_verified' => ($review['image_verified'] ?? false) === true,
            'images' => array_column($product['images'], 'local_url'), 'option_titles' => [], 'attributes' => []] : []);
    }

    /** Only explicitly labelled EAN/GTIN values can provide a draft identifier. */
    private function specificationGtins(array $product): array
    {
        $values = [];
        foreach ($product['specifications'] ?? [] as $specification) {
            $label = is_string($specification['label'] ?? null) ? strtoupper(trim($specification['label'])) : '';
            $value = is_string($specification['value'] ?? null) ? trim($specification['value']) : null;
            if (in_array($label, ['EAN', 'GTIN'], true) && MerchantProducts::validGtin($value)) {
                $values[$value] ??= $label;
            }
        }

        return $values;
    }

    private function identifierDraft(array $product, array $review, array $occurrences, bool $variant = false): array
    {
        $manufacturer = ! $variant && ($product['manufacturer']['verified'] ?? false) === true ? $product['manufacturer'] : [];
        $gtin = $manufacturer['gtin'] ?? $review['gtin'] ?? null;
        $mpn = $manufacturer['mpn'] ?? $review['mpn'] ?? null;
        $warnings = [];
        $evidence = null;
        if (isset($manufacturer['gtin'], $review['gtin']) && $manufacturer['gtin'] !== $review['gtin']) {
            $warnings[] = 'manufacturer_gtin_conflicts_with_review';
        }
        if ($variant || ($product['has_variants'] ?? true)) {
            return compact('gtin', 'mpn', 'warnings', 'evidence');
        }
        $candidates = $this->specificationGtins($product);
        if (count($candidates) > 1) {
            $warnings[] = 'catalogue_gtin_ambiguous';
            $evidence = ['type' => 'catalogue_specification:ambiguous', 'values' => array_map('strval', array_keys($candidates)),
                'independent_attribution_verified' => false,
                'note' => 'Several valid identifiers are labelled EAN/GTIN; no catalogue value was selected automatically.'];
        } elseif ($candidates !== []) {
            $candidate = (string) array_key_first($candidates);
            $unique = ($occurrences[$candidate] ?? 0) === 1;
            $conflict = $gtin !== null && $gtin !== $candidate;
            $exemptionConflict = ($review['identifier_exists'] ?? null) === false;
            if (! $unique) {
                $warnings[] = 'catalogue_gtin_not_unique';
            }
            if ($conflict) {
                $warnings[] = 'catalogue_gtin_conflicts_with_declared_identifier';
            }
            if ($exemptionConflict) {
                $warnings[] = 'catalogue_gtin_conflicts_with_identifier_exemption';
            }
            $evidence = ['type' => 'catalogue_specification:'.$candidates[$candidate], 'value' => $candidate,
                'selected_source' => isset($manufacturer['gtin']) ? 'verified_administration'
                    : ($gtin !== null ? 'existing_merchant_review' : ($unique && ! $exemptionConflict ? 'catalogue_specification' : null)),
                'syntax_valid' => true, 'unique_in_audited_catalogue' => $unique,
                'conflicts_with_declared_identifier' => $conflict, 'conflicts_with_identifier_exemption' => $exemptionConflict,
                'independent_attribution_verified' => false,
                'note' => 'Check digit and catalogue uniqueness do not independently verify the manufacturer or model attribution.'];
            if ($gtin === null && $unique && ! $exemptionConflict) {
                $gtin = $candidate;
            }
        }

        return compact('gtin', 'mpn', 'warnings', 'evidence');
    }

    /** A bounded snapshot avoids a different review file between language reports. */
    private function reviewSnapshot(): array
    {
        $path = config('merchant.reviews_path');
        if (! is_string($path) || ! file_exists($path)) {
            return ['status' => 'absent', 'reviews' => []];
        }
        try {
            if (! is_file($path) || ! is_readable($path) || filesize($path) > 4_000_000) {
                return ['status' => 'invalid', 'reviews' => []];
            }
            $contents = file_get_contents($path, false, null, 0, 4_000_001);
            if (! is_string($contents) || strlen($contents) > 4_000_000) {
                return ['status' => 'invalid', 'reviews' => []];
            }
            $reviews = json_decode($contents, true, 32, JSON_THROW_ON_ERROR);
            if (! is_array($reviews)) {
                return ['status' => 'invalid', 'reviews' => []];
            }
            foreach ($reviews as $slug => $review) {
                if (! is_string($slug) || ! is_array($review)) {
                    return ['status' => 'invalid', 'reviews' => []];
                }
            }

            return ['status' => 'valid', 'reviews' => $reviews];
        } catch (Throwable) {
            return ['status' => 'invalid', 'reviews' => []];
        }
    }

    /** Supplier proof URLs are public evidence; discard credentials and URL tokens. */
    private function publicSourceUrls(mixed $urls): array
    {
        if (! is_array($urls)) {
            return [];
        }
        $result = [];
        foreach (array_slice($urls, 0, 20) as $url) {
            if (! is_string($url) || strlen($url) > 2048 || ! filter_var($url, FILTER_VALIDATE_URL)) {
                continue;
            }
            $parts = parse_url($url);
            if (! in_array($parts['scheme'] ?? null, ['http', 'https'], true)
                || isset($parts['user']) || isset($parts['pass'])) {
                continue;
            }
            $result[] = $parts['scheme'].'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '').($parts['path'] ?? '/');
        }

        return array_values(array_unique($result));
    }

    private function safeDirectory(string $directory): bool
    {
        if (! preg_match('~\Aoutput(?:/[A-Za-z0-9][A-Za-z0-9_-]*)+\z~D', $directory)) {
            return false;
        }
        $path = base_path();
        foreach (explode('/', $directory) as $part) {
            $path .= '/'.$part;
            if (is_link($path) || (file_exists($path) && ! is_dir($path))) {
                return false;
            }
        }

        return true;
    }

    private function writeJson(string $path, array $data): void
    {
        $this->write($path, json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)."\n");
    }

    private function write(string $path, string $contents): void
    {
        if (strlen($contents) > self::MAX_REPORT_BYTES) {
            throw new \RuntimeException('Report exceeds the local size limit. Audit fewer locales per output directory.');
        }
        if (file_exists($path) || is_link($path)) {
            throw new \RuntimeException('Report already exists. Choose a new output directory.');
        }
        $stream = fopen($path, 'x');
        if ($stream === false) {
            throw new \RuntimeException('Unable to create local report.');
        }
        try {
            chmod($path, 0600);
            if (fwrite($stream, $contents) !== strlen($contents)) {
                throw new \RuntimeException('Unable to finish local report.');
            }
        } finally {
            fclose($stream);
        }
    }

    private function writeCsv(string $path, array $reports, bool $catalogueOnly = false): void
    {
        $stream = fopen('php://temp', 'w+');
        fputcsv($stream, ['langue', 'slug', 'reference', 'titre', 'chemin_page', 'architecture', 'blocage', 'caracteristiques_a_confirmer'], ';', '"', '');
        foreach ($reports as $report) {
            foreach ($report['items'] as $item) {
                foreach ($item['problems'] as $problem) {
                    if ($catalogueOnly && $problem !== 'catalogue_issues_require_review') {
                        continue;
                    }
                    $row = [$report['locale'], $item['slug'], $item['reference'], $item['name'], $item['url_path'], $item['product_type'],
                        $problem, json_encode($item['specification_reviews'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)];
                    $this->csvRow($stream, $row);
                }
            }
        }
        rewind($stream);
        $this->write($path, stream_get_contents($stream));
        fclose($stream);
    }

    /** One product per row, with the decisions for every audited language together. */
    private function writeSelectionCsv(string $path, array $reports): void
    {
        $products = [];
        foreach ($reports as $locale => $report) {
            foreach ($report['items'] as $item) {
                $products[$item['slug']][$locale] = $item;
            }
        }
        $stream = fopen('php://temp/maxmemory:2000000', 'w+');
        $this->csvRow($stream, ['slug', 'reference', 'title', 'brand', 'languages', 'data_ready_languages',
            'currently_exportable_languages', 'blocking_reasons_by_language', 'inventory_problems_by_language', 'warnings_by_language']);
        foreach ($products as $slug => $localized) {
            $first = reset($localized);
            $ready = [];
            $exportable = [];
            $problems = [];
            $warnings = [];
            $inventoryProblems = [];
            foreach ($localized as $locale => $item) {
                if ($item['data_ready']) {
                    $ready[] = $locale;
                }
                if ($item['currently_exportable']) {
                    $exportable[] = $locale;
                }
                $problems[$locale] = ['product' => $item['problems'], 'variants' => array_map(
                    fn ($variant) => ['variant' => $variant['variant'], 'problems' => $variant['problems']], $item['variants'])];
                $warnings[$locale] = ['product' => $item['warnings'], 'variants' => array_map(
                    fn ($variant) => ['variant' => $variant['variant'], 'warnings' => $variant['warnings']], $item['variants'])];
                $inventoryProblems[$locale] = $item['inventory_problems'];
            }
            $this->csvRow($stream, [$slug, $first['reference'], $first['name'], $first['brand'], implode(',', array_keys($localized)),
                implode(',', $ready), implode(',', $exportable), $this->jsonCell($problems),
                $this->jsonCell($inventoryProblems), $this->jsonCell($warnings)]);
        }
        rewind($stream);
        $this->write($path, stream_get_contents($stream));
        fclose($stream);
    }

    private function writeSupplierCsv(string $path, array $reports, array $templates): void
    {
        $products = [];
        foreach ($reports as $locale => $report) {
            foreach ($report['items'] as $item) {
                $products[$item['slug']] ??= $item;
            }
        }
        $stream = fopen('php://temp/maxmemory:2000000', 'w+');
        $this->csvRow($stream, ['slug', 'variant_id', 'reference', 'title', 'brand', 'gtin', 'mpn', 'identifier_exists',
            'source_urls', 'energy_label_required', 'eprel_code', 'title_source', 'description_source', 'essential_details_verified', 'approved',
            'manufacturer_identifier_evidence', 'allow_google_energy_lookup']);
        foreach ($products as $slug => $product) {
            $this->supplierRow($stream, $product, '', $product['name'], $templates[$slug]);
            foreach ($product['variants'] as $variant) {
                $this->supplierRow($stream, $product, $variant['variant'], $variant['title'], $templates[$slug]['variants'][$variant['variant']]);
            }
        }
        rewind($stream);
        $this->write($path, stream_get_contents($stream));
        fclose($stream);
    }

    private function supplierRow($stream, array $product, string $variant, string $title, array $review): void
    {
        $bool = fn ($value) => is_bool($value) ? ($value ? 'true' : 'false') : '';
        $this->csvRow($stream, [$product['slug'], $variant, $product['reference'], $title, $product['brand'],
            $review['gtin'], $review['mpn'], $bool($review['identifier_exists']), $this->jsonCell($review['source_urls']),
            $bool($review['energy_label_required']), $review['eprel_code'], $review['title_source'], $review['description_source'],
            $bool($review['essential_details_verified']), 'false', $this->jsonCell($review['manufacturer_identifier_evidence'] ?? []),
            $bool($review['allow_google_energy_lookup'])]);
    }

    private function jsonCell(array $value): string
    {
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    private function csvRow($stream, array $row): void
    {
        // A supplier title or reference must never become a spreadsheet formula.
        $row = array_map(fn ($value) => preg_match('/\A[\s]*[=+@-]/u', (string) $value) ? "'".$value : $value, $row);
        if (fputcsv($stream, $row, ';', '"', '') === false || ftell($stream) > self::MAX_REPORT_BYTES) {
            throw new \RuntimeException('Unable to finish CSV within the local report size limit.');
        }
    }
}
