<?php

namespace App\Console\Commands;

use App\Services\Catalogue;
use App\Services\FileCatalogueSource;
use App\Services\MerchantProducts;
use App\Services\Seo;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

final class GoogleReadinessAudit extends Command
{
    protected $signature = 'homya:google-audit
        {--locale=fr : fr, en, de or all}
        {--json : Machine-readable report}
        {--offline : Read only the file archive, without administration or database access}
        {--output= : Save reports and an unapproved review template in an output/ subdirectory}';

    protected $description = 'Audit public catalogue and Merchant data without querying inventory or contacting Google';

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
        $originalLocale = app()->getLocale();
        $settings = config()->getMany(['catalogue.source', 'dashboard.enabled', 'commerce.inventory_enabled']);
        $offline = (bool) $this->option('offline');
        if ($offline) {
            config(['catalogue.source' => 'files', 'dashboard.enabled' => false, 'commerce.inventory_enabled' => false]);
            $catalogue = new Catalogue(new FileCatalogueSource);
            $merchant = new MerchantProducts($seo, $catalogue);
        }
        try {
            $reports = [];
            $templates = [];
            $locales = $locale === 'all' ? config('homya.locales') : [$locale];
            foreach ($locales as $language) {
                $report = $this->audit($catalogue, $merchant, $seo, $language, $offline, $templates);
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
            }
            if ($this->option('json')) {
                $this->line(json_encode($locale === 'all' ? $reports : $reports[$locale], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            } else {
                foreach ($reports as $report) {
                    $this->info($report['products'].' products audited ('.$report['locale'].', '.$report['catalogue_scope'].'). Stock and Google accounts not checked.');
                    $counts = $report['problem_counts'];
                    $this->table(['Problem', 'Products'], array_map(fn ($key, $count) => [$key, $count], array_keys($counts), $counts));
                }
                if ($directory) {
                    $this->info('Saved local reports, products-to-complete.csv and a review template with approved=false under '.$directory.'.');
                }
            }

            return self::SUCCESS;
        } finally {
            app()->setLocale($originalLocale);
            config($settings);
        }
    }

    private function audit(Catalogue $catalogue, MerchantProducts $merchant, Seo $seo, string $locale, bool $offline, array &$templates): ?array
    {
        app()->setLocale($locale);
        $products = $catalogue->products();
        if ($catalogue->sourceUnavailable() || $catalogue->translationUnavailable() || $products->isEmpty()) {
            return null;
        }
        $reviews = $merchant->reviews();
        $rows = [];
        $counts = [];
        foreach ($products as $summary) {
            $product = $catalogue->product($summary['slug']);
            if ($product === null) {
                return null;
            }
            $problems = $merchant->problems($product, $reviews[$product['slug']] ?? [], $locale);
            foreach ($problems as $problem) {
                $counts[$problem] = ($counts[$problem] ?? 0) + 1;
            }
            $variantRows = [];
            $template = &$templates[$product['slug']];
            $template ??= $this->template($product);
            $template['fingerprints'][$locale] = MerchantProducts::fingerprint($product);
            if ($product['has_variants']) {
                foreach ($merchant->variantProducts($product, $reviews[$product['slug']] ?? [], $locale) as [$variant, $variantReview]) {
                    $variantRows[] = ['id' => MerchantProducts::id($variant), 'variant' => $variant['variant_id'],
                        'title' => $variant['variant_title'], 'url_path' => route('product', ['locale' => $locale, 'slug' => $product['slug'], 'variant' => $variant['variant_id']], false),
                        'fingerprint' => MerchantProducts::fingerprint($variant), 'problems' => array_values($merchant->problems($variant, $variantReview, $locale))];
                    $variantTemplate = &$template['variants'][$variant['variant_id']];
                    $variantTemplate ??= $this->template($variant, true);
                    $variantTemplate['option_titles'][$locale] = $variant['variant_title'];
                    $variantTemplate['attributes'][$locale] = [];
                    // Capture after the attributes and the exact variant image have been supplied.
                    // A current empty variant projection cannot be approved with this draft hash.
                    $variantTemplate['fingerprints'][$locale] = null;
                    unset($variantTemplate);
                }
            }
            unset($template);
            $rows[] = ['slug' => $product['slug'], 'reference' => $product['reference'], 'name' => $product['name'],
                'url_path' => route('product', ['locale' => $locale, 'slug' => $product['slug']], false),
                'product_type' => MerchantProducts::productType($product, $locale),
                'fingerprint' => MerchantProducts::fingerprint($product),
                'problems' => array_values($problems), 'catalogue_issues' => $product['issues'],
                'specification_reviews' => $product['specification_reviews'] ?? [], 'variants' => $variantRows];
        }
        ksort($counts);

        return ['checked_at' => now()->toIso8601String(), 'locale' => $locale, 'products' => count($rows),
            'catalogue_scope' => $offline ? 'file_archive' : 'application_catalogue',
            'catalogue_source' => config('catalogue.source'), 'administration_checked' => ! $offline && config('dashboard.enabled') === true,
            'indexing_configured' => $seo->enabled(),
            'merchant_configured' => $merchant->ready(), 'stock_checked' => false, 'google_contacted' => false,
            'problem_counts' => $counts, 'items' => $rows];
    }

    private function template(array $product, bool $variant = false): array
    {
        $manufacturer = ($product['manufacturer']['verified'] ?? false) === true && ! $variant ? $product['manufacturer'] : [];

        return ['approved' => false, 'fingerprints' => [], 'gtin' => $manufacturer['gtin'] ?? null,
            'mpn' => $manufacturer['mpn'] ?? null, 'identifier_exists' => null,
            'google_product_category' => null, 'eprel_code' => null, 'source_urls' => empty($manufacturer['source_url']) ? [] : [$manufacturer['source_url']],
            'title_source' => $product['title_source'] ?? null, 'description_source' => $product['description_source'] ?? null]
            + ($variant ? ['image_verified' => false, 'images' => [], 'option_titles' => [], 'attributes' => []] : []);
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
                    $row = array_map(fn ($value) => preg_match('/\A[\s]*[=+@-]/u', (string) $value) ? "'".$value : $value, $row);
                    fputcsv($stream, $row, ';', '"', '');
                }
            }
        }
        rewind($stream);
        $this->write($path, stream_get_contents($stream));
        fclose($stream);
    }
}
