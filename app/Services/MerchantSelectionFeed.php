<?php

namespace App\Services;

use DOMDocument;
use DOMElement;
use DOMXPath;
use RuntimeException;

/** Primary feed for exactly the 150 imported offers; only price and stock vary. */
final class MerchantSelectionFeed
{
    private const COUNT = 150;

    private const MAX_FILE_BYTES = 1_000_000;

    public function __construct(private Catalogue $catalogue, private MerchantProducts $merchant, private Seo $seo) {}

    /** Safe diagnostics for a private preflight; the controller never returns them. */
    public function readinessChecks(): array
    {
        $checks = $this->merchant->readinessChecks();
        unset($checks['merchant_feed_enabled']);

        return $checks + [
            'selection_feed_enabled' => config('merchant-selection.enabled') === true,
            'selection_origin_matches' => $this->seo->origin() === config('merchant-selection.public_origin'),
        ];
    }

    public function ready(): bool
    {
        foreach ($this->readinessChecks() as $ready) {
            if ($ready !== true) {
                return false;
            }
        }

        return true;
    }

    /** No cache or stock-expiry call: every response reads the current public offer. */
    public function xml(): string
    {
        $previousLocale = app()->getLocale();
        app()->setLocale('fr');
        try {
            [$manifest, $document, $xpath, $items] = $this->selection();
            $this->assertCatalogueAvailable();
            // MerchantProducts uses a single SELECT and never expires or releases orders.
            $inventory = $this->merchant->inventoryRows();

            foreach ($manifest['products'] as $index => $approved) {
                $product = $this->catalogue->product($approved['slug']);
                $this->assertCatalogueAvailable();
                $this->assertProduct($product, $approved);
                $stock = $inventory->get($approved['slug']);
                if ($this->merchant->inventoryProblems($product, $stock) !== []) {
                    throw new RuntimeException('Selection inventory requires review: '.$approved['slug']);
                }
                // PDO can return integer columns as decimal strings. Reject coercible
                // floats, booleans, negative values and missing/malformed columns.
                $quantity = $this->integer($stock->quantity ?? null);
                $reserved = $this->integer($stock->reserved ?? null);
                $approvedPrice = $this->integer($stock->approved_price_cents ?? null);
                if (! in_array($stock->sale_enabled ?? null, [true, 1, '1'], true)
                    || ! is_string($stock->validated_at ?? null) || trim($stock->validated_at) === ''
                    || $quantity === null || $reserved === null || $reserved > $quantity
                    || $approvedPrice !== $product['price_cents']) {
                    throw new RuntimeException('Selection inventory invalid: '.$approved['slug']);
                }

                $this->one($xpath, $items[$index], 'g:price')->textContent =
                    intdiv($product['price_cents'], 100).'.'.str_pad((string) ($product['price_cents'] % 100), 2, '0', STR_PAD_LEFT).' EUR';
                $this->one($xpath, $items[$index], 'g:availability')->textContent =
                    $quantity > $reserved ? 'in_stock' : 'out_of_stock';
            }
            $this->assertCatalogueAvailable();

            return $document->saveXML() ?: throw new RuntimeException('Selection XML unavailable.');
        } finally {
            app()->setLocale($previousLocale);
        }
    }

    private function assertCatalogueAvailable(): void
    {
        if ($this->catalogue->sourceUnavailable() || $this->catalogue->translationUnavailable()) {
            throw new RuntimeException('Selection catalogue unavailable.');
        }
    }

    private function assertProduct(?array $product, array $approved): void
    {
        if ($product === null || ($product['admin_published'] ?? true) !== true) {
            // Removing a primary-feed item is a separate merchant decision.
            throw new RuntimeException('Selection product unpublished or unavailable: '.$approved['slug']);
        }
        foreach (['slug', 'reference', 'name', 'description', 'brand'] as $key) {
            if (($product[$key] ?? null) !== $approved[$key]) {
                throw new RuntimeException('Selection product identity changed: '.$approved['slug']);
            }
        }
        if (MerchantProducts::condition($product) !== $approved['condition']
            || ($product['has_variants'] ?? null) !== false || ($product['variants'] ?? []) !== []
            || isset($product['variant_id'])
            // A reviewed feed photograph may be another public gallery view of
            // the same model. Its exact path must remain in that public gallery.
            || ! is_array($product['images'] ?? null)
            || ! in_array($approved['main_image_path'], array_column($product['images'], 'local_url'), true)
            || (isset($product['sales_pack']) && MerchantProducts::salesPack($product) === null)
            || MerchantProducts::salesPack($product) !== $approved['sales_pack']) {
            throw new RuntimeException('Selection product facts changed: '.$approved['slug']);
        }
        $maxPrice = config('commerce.max_total_cents');
        if (! is_int($product['price_cents'] ?? null) || ! is_int($maxPrice)
            || $product['price_cents'] <= 0 || $product['price_cents'] > $maxPrice
            || ($product['currency'] ?? null) !== 'EUR') {
            throw new RuntimeException('Selection product price invalid: '.$approved['slug']);
        }
    }

    private function integer(mixed $value): ?int
    {
        if (! is_int($value) && (! is_string($value) || ! preg_match('/\A(?:0|[1-9][0-9]*)\z/', $value))) {
            return null;
        }
        $number = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);

        return $number === false ? null : $number;
    }

    private function sealedFile(string $kind): string
    {
        $path = config('merchant-selection.'.$kind.'_path');
        $expected = config('merchant-selection.'.$kind.'_sha256');
        if (! is_string($path) || ! is_file($path) || is_link($path)
            || ! is_string($expected) || ! preg_match('/\A[a-f0-9]{64}\z/', $expected)) {
            throw new RuntimeException('Selection sealed file unavailable.');
        }
        $contents = file_get_contents($path, false, null, 0, self::MAX_FILE_BYTES + 1);
        if (! is_string($contents) || $contents === '' || strlen($contents) > self::MAX_FILE_BYTES
            || ! hash_equals($expected, hash('sha256', $contents))) {
            throw new RuntimeException('Selection sealed file changed.');
        }

        return $contents;
    }

    private function selection(): array
    {
        $manifest = json_decode($this->sealedFile('manifest'), true, 32, JSON_THROW_ON_ERROR);
        if (! is_array($manifest) || ($manifest['version'] ?? null) !== 1
            || ($manifest['locale'] ?? null) !== 'fr' || ($manifest['count'] ?? null) !== self::COUNT
            || ($manifest['public_origin'] ?? null) !== config('merchant-selection.public_origin')
            || ($manifest['template_sha256'] ?? null) !== config('merchant-selection.template_sha256')
            || ! is_array($manifest['products'] ?? null) || ! array_is_list($manifest['products'])
            || count($manifest['products']) !== self::COUNT) {
            throw new RuntimeException('Selection manifest invalid.');
        }
        $xml = $this->sealedFile('template');
        if (preg_match('/<!\s*(?:DOCTYPE|ENTITY)\b/i', $xml)) {
            throw new RuntimeException('Selection XML declarations forbidden.');
        }
        $document = new DOMDocument;
        $document->resolveExternals = false;
        $document->substituteEntities = false;
        $previousErrors = libxml_use_internal_errors(true);
        try {
            libxml_clear_errors();
            $valid = $document->loadXML($xml, LIBXML_NONET | LIBXML_COMPACT);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previousErrors);
        }
        if (! $valid || $document->doctype !== null || $document->documentElement?->tagName !== 'rss'
            || $document->documentElement->getAttribute('version') !== '2.0') {
            throw new RuntimeException('Selection XML invalid.');
        }
        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('g', 'http://base.google.com/ns/1.0');
        $channels = $xpath->query('/rss/channel');
        $nodes = $xpath->query('/rss/channel/item');
        if ($channels->length !== 1 || $nodes->length !== self::COUNT) {
            throw new RuntimeException('Selection XML product count invalid.');
        }
        $items = iterator_to_array($nodes);
        $ids = $slugs = [];
        foreach ($manifest['products'] as $index => $product) {
            $this->assertManifestProduct($product);
            if (isset($ids[$product['id']]) || isset($slugs[$product['slug']])) {
                throw new RuntimeException('Selection product duplicated.');
            }
            $ids[$product['id']] = $slugs[$product['slug']] = true;
            $item = $items[$index];
            foreach (['id', 'brand', 'condition'] as $key) {
                if ($this->one($xpath, $item, 'g:'.$key)->textContent !== $product[$key]) {
                    throw new RuntimeException('Selection XML identity mismatch.');
                }
            }
            foreach (['name' => 'g:structured_title/g:content', 'description' => 'g:structured_description/g:content',
                'main_image_path' => 'g:image_link', 'slug' => 'g:link'] as $key => $path) {
                $expected = match ($key) {
                    'main_image_path' => $manifest['public_origin'].$product[$key],
                    'slug' => $manifest['public_origin'].'/fr/produit/'.$product[$key],
                    default => $product[$key],
                };
                if ($this->one($xpath, $item, $path)->textContent !== $expected) {
                    throw new RuntimeException('Selection XML content mismatch.');
                }
            }
            foreach (['multipack', 'unit_pricing_measure', 'unit_pricing_base_measure'] as $key) {
                $nodes = $xpath->query('g:'.$key, $item);
                $expected = $product['sales_pack'][$key] ?? null;
                if (($expected === null && $nodes->length !== 0)
                    || ($expected !== null && ($nodes->length !== 1 || $nodes->item(0)->textContent !== (string) $expected))) {
                    throw new RuntimeException('Selection XML pack mismatch.');
                }
            }
            // They are the only fields this service is allowed to overwrite.
            $this->one($xpath, $item, 'g:price');
            $this->one($xpath, $item, 'g:availability');
        }

        return [$manifest, $document, $xpath, $items];
    }

    private function assertManifestProduct(mixed $product): void
    {
        if (! is_array($product)) {
            throw new RuntimeException('Selection manifest product invalid.');
        }
        foreach (['id', 'slug', 'reference', 'name', 'description', 'brand', 'condition', 'main_image_path'] as $key) {
            if (! is_string($product[$key] ?? null) || trim($product[$key]) === '') {
                throw new RuntimeException('Selection manifest fact missing.');
            }
        }
        if (! preg_match('/\A[a-z0-9][a-z0-9-]{0,220}\z/', $product['slug'])
            || MerchantProducts::id($product) !== $product['id']
            || ! in_array($product['condition'], ['new', 'used', 'refurbished'], true)
            || ! str_starts_with($product['main_image_path'], '/media/catalogue/')
            || ! array_key_exists('sales_pack', $product)
            || ($product['sales_pack'] !== null && ! is_array($product['sales_pack']))) {
            throw new RuntimeException('Selection manifest product facts invalid.');
        }
    }

    private function one(DOMXPath $xpath, DOMElement $item, string $path): DOMElement
    {
        $nodes = $xpath->query($path, $item);
        if ($nodes->length !== 1 || ! $nodes->item(0) instanceof DOMElement) {
            throw new RuntimeException('Selection XML field invalid.');
        }

        return $nodes->item(0);
    }
}
