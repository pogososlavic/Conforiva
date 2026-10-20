<?php

namespace App\Services;

use App\Services\Commerce\CheckoutReadiness;
use App\Services\Commerce\OrderStore;
use Illuminate\Support\Collection;
use Throwable;
use XMLWriter;

final class MerchantProducts
{
    private const VARIANT_ATTRIBUTE_LIMITS = ['color' => 100, 'material' => 200, 'pattern' => 100, 'size' => 100];

    private const EU_COUNTRIES = ['AT', 'BE', 'BG', 'HR', 'CY', 'CZ', 'DK', 'EE', 'FI', 'FR', 'DE', 'GR',
        'HU', 'IE', 'IT', 'LV', 'LT', 'LU', 'MT', 'NL', 'PL', 'PT', 'RO', 'SK', 'SI', 'ES', 'SE'];

    public function __construct(private Seo $seo, private Catalogue $catalogue) {}

    public function ready(): bool
    {
        foreach ($this->readinessChecks() as $ready) {
            if ($ready !== true) {
                return false;
            }
        }

        return true;
    }

    /** Safe gate results; null means deliberately not evaluated by an offline audit. */
    public function readinessChecks(bool $checkPayment = true): array
    {
        $paymentReady = null;
        if ($checkPayment) {
            try {
                $readiness = app(CheckoutReadiness::class);
                $paymentReady = $readiness->bankReady() || $readiness->cardReady();
            } catch (Throwable) {
                // Unreadable private payment settings close the feed without leaking their contents.
                $paymentReady = false;
            }
        }

        return [
            'seo_indexing' => $this->seo->enabled(),
            'merchant_feed_enabled' => config('merchant.enabled') === true,
            'inventory_enabled' => config('commerce.inventory_enabled') === true,
            'legal_ready' => config('commerce.legal_ready') === true,
            'canonical_origin_matches' => rtrim((string) config('app.url'), '/') === $this->seo->origin(),
            'checkout_payment_ready' => $paymentReady,
        ];
    }

    public function reviews(): array
    {
        try {
            $path = config('merchant.reviews_path');
            if (! is_file($path) || filesize($path) > 4_000_000) {
                return [];
            }
            $rows = json_decode(file_get_contents($path), true, 32, JSON_THROW_ON_ERROR);

            return is_array($rows) ? array_filter($rows, 'is_array') : [];
        } catch (Throwable) {
            return [];
        }
    }

    public static function fingerprint(array $product): string
    {
        $facts = [];
        foreach (['slug', 'reference', 'name', 'description', 'brand', 'universe', 'category', 'manufacturer', 'condition', 'price_cents', 'currency', 'has_variants', 'variants', 'images', 'specifications', 'specification_reviews', 'issues', 'variant_id', 'variant_attributes', 'title_source', 'description_source', 'sales_pack'] as $key) {
            $facts[$key] = $product[$key] ?? null;
        }

        return hash('sha256', json_encode($facts, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    public static function validGtin(mixed $gtin): bool
    {
        if (! is_string($gtin) || ! preg_match('/\A(?:\d{8}|\d{12}|\d{13}|\d{14})\z/', $gtin) || (int) $gtin === 0) {
            return false;
        }
        $sum = 0;
        for ($i = strlen($gtin) - 2, $weight = 3; $i >= 0; $i--, $weight = 4 - $weight) {
            $sum += (int) $gtin[$i] * $weight;
        }

        return (10 - $sum % 10) % 10 === (int) substr($gtin, -1);
    }

    /** Owner-confirmed retailer lots; all prices and stock remain per complete lot. */
    public static function salesPack(array $product): ?array
    {
        $pack = $product['sales_pack'] ?? null;
        if (! is_array($pack) || ($pack['type'] ?? null) !== 'merchant_multipack'
            || ($pack['identifier_scope'] ?? null) !== 'unit_bag'
            || ! is_int($pack['count'] ?? null) || $pack['count'] < 2 || $pack['count'] > 1000
            || ! is_int($pack['unit_weight_kg'] ?? null) || $pack['unit_weight_kg'] < 1 || $pack['unit_weight_kg'] > 1000
            || ! is_string($pack['owner_confirmed_on'] ?? null)
            || ! preg_match('/\A\d{4}-\d{2}-\d{2}\z/', $pack['owner_confirmed_on'])) {
            return null;
        }

        return ['multipack' => $pack['count'], 'unit_pricing_measure' => ($pack['count'] * $pack['unit_weight_kg']).'kg',
            'unit_pricing_base_measure' => '1kg'];
    }

    /** Only reviewed gallery URLs; an unknown URL cannot introduce a remote image. */
    public function reviewedImages(array $product, array $review): array
    {
        $images = $product['images'] ?? [];
        if (! array_key_exists('approved_google_image_urls', $review)) {
            return $images;
        }
        $urls = $review['approved_google_image_urls'];
        if (! is_array($urls) || ! array_is_list($urls) || $urls === [] || count($urls) > 11
            || count(array_filter($urls, 'is_string')) !== count($urls)
            || count(array_unique($urls)) !== count($urls)
            || ($urls[0] ?? null) !== ($images[0]['local_url'] ?? null)) {
            return [];
        }
        $selected = [];
        foreach ($urls as $url) {
            $matches = array_values(array_filter($images, static fn (array $image): bool => ($image['local_url'] ?? null) === $url));
            if (count($matches) !== 1) {
                return [];
            }
            $selected[] = $matches[0];
        }

        return $selected;
    }

    public static function condition(array $product): ?string
    {
        // Existing catalogue vocabulary, mapped to Google's accepted English values.
        return ['new' => 'new', 'neuf' => 'new', 'used' => 'used', 'occasion' => 'used',
            'refurbished' => 'refurbished', 'reconditionne' => 'refurbished'][$product['condition'] ?? ''] ?? null;
    }

    public function problems(array $product, array $review, string $locale): array
    {
        $problems = [];
        $fingerprint = is_array($review['fingerprints'] ?? null) ? ($review['fingerprints'][$locale] ?? null) : null;
        if (($review['approved'] ?? false) !== true || ! is_string($fingerprint)
            || ! hash_equals(self::fingerprint($product), $fingerprint)) {
            $problems[] = 'merchant_review_required';
        }
        if (empty($product['name']) || mb_strlen($product['name']) > 150 || empty($product['description']) || mb_strlen($product['description']) > 5000) {
            $problems[] = 'title_or_description_invalid';
        }
        if (preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', ($product['name'] ?? '').($product['description'] ?? '').($product['brand'] ?? ''))
            || mb_strlen($product['brand'] ?? '') > 70) {
            $problems[] = 'text_format_invalid';
        }
        if (empty($product['images'])) {
            $problems[] = 'image_missing';
        }
        if (! is_int($product['price_cents'] ?? null) || $product['price_cents'] <= 0
            || $product['price_cents'] > config('commerce.max_total_cents') || ($product['currency'] ?? null) !== 'EUR') {
            $problems[] = 'price_or_currency_invalid';
        }
        if (self::condition($product) === null) {
            $problems[] = 'condition_required';
        }
        if (isset($review['google_product_category']) && self::googleCategory($review['google_product_category']) === null) {
            $problems[] = 'google_product_category_invalid';
        }
        if (isset($review['eprel_code']) && self::eprelCode($review['eprel_code']) === null) {
            $problems[] = 'eprel_code_invalid';
        }
        if (! ($product['has_variants'] ?? false)
            && array_intersect(config('merchant.target_countries', []), self::EU_COUNTRIES) !== []) {
            // A feed review cannot override an unresolved reservation on the exact
            // model's supplier documents (for example the Aquaflam fiche typo).
            $documents = config('product-energy.'.($product['slug'] ?? ''));
            if (is_array($documents) && ($documents['reference'] ?? null) === ($product['reference'] ?? null)
                && ($documents['price_display'] ?? true) === false) {
                $problems[] = 'energy_documents_require_correction';
            }
            // Obligation is a reviewed product fact, never inferred from a broad store category.
            if (! is_bool($review['energy_label_required'] ?? null)) {
                $problems[] = 'energy_label_review_required';
            } elseif ($review['energy_label_required'] && self::eprelCode($review['eprel_code'] ?? null) === null
                && ! $this->googleEnergyLookupAllowed($product, $review)) {
                $problems[] = 'eprel_code_required';
            }
        }
        if ($product['has_variants'] ?? true) {
            $variants = $this->variantProducts($product, $review, $locale);
            if (! $variants) {
                $problems[] = 'variant_data_required';
            }
            $attributeKeys = null;
            $combinations = [];
            foreach ($variants as [$variant, $variantReview]) {
                $variantProblems = $this->problems($variant, $variantReview, $locale);
                if ($variantProblems) {
                    $problems[] = 'variant_review_required';
                    // Keep actionable attribute/certification causes visible to the private audit.
                    $problems = array_merge($problems, $variantProblems);
                }
                $attributes = $variant['variant_attributes'];
                ksort($attributes);
                $keys = array_keys($attributes);
                if ($attributeKeys !== null && $keys !== $attributeKeys) {
                    $problems[] = 'variant_attributes_inconsistent';
                }
                $attributeKeys = $keys;
                $combinations[] = json_encode($attributes, JSON_THROW_ON_ERROR);
            }
            if (count($combinations) !== count(array_unique($combinations))) {
                $problems[] = 'variant_attributes_not_distinct';
            }
        }
        if (isset($product['variant_id']) && empty($product['variant_attributes'])) {
            $problems[] = 'variant_attributes_required';
        }
        foreach (self::VARIANT_ATTRIBUTE_LIMITS as $attribute => $limit) {
            $value = $product['variant_attributes'][$attribute] ?? null;
            if (is_string($value) && mb_strlen($value) > $limit) {
                $problems[] = 'variant_'.$attribute.'_too_long';
            }
        }
        $issues = $product['issues'] ?? [];
        if (($review['essential_details_verified'] ?? null) === true) {
            $issues = array_diff($issues, ['missing_specifications']);
        }
        if ($issues !== []) {
            $problems[] = 'catalogue_issues_require_review';
        }
        if (! ($product['has_variants'] ?? false)) {
            $gtin = $review['gtin'] ?? null;
            $mpn = $review['mpn'] ?? null;
            if ($gtin !== null && ! self::validGtin($gtin)) {
                $problems[] = 'gtin_invalid';
            }
            if ($mpn !== null && (! is_string($mpn) || trim($mpn) === '' || mb_strlen($mpn) > 70)) {
                $problems[] = 'mpn_invalid';
            }
            if (($review['identifier_exists'] ?? null) === false) {
                if ($gtin !== null || $mpn !== null) {
                    $problems[] = 'identifiers_contradict_exemption';
                }
            } elseif (! self::validGtin($gtin) && ! (is_string($mpn) && trim($mpn) !== '' && ! empty($product['brand']))) {
                $problems[] = 'manufacturer_identifiers_required';
            }
        }
        if (array_key_exists('approved_google_image_urls', $review) && $this->reviewedImages($product, $review) === []) {
            $problems[] = 'image_review_required';
        }
        if (isset($product['sales_pack']) || isset($review['multipack'])
            || ($review['identifier_scope'] ?? null) === 'unit_bag') {
            $pack = self::salesPack($product);
            if ($pack === null || ($review['multipack'] ?? null) !== $pack['multipack']
                || ($review['identifier_scope'] ?? null) !== 'unit_bag') {
                $problems[] = 'multipack_review_required';
            }
            if (($review['identifier_exists'] ?? null) === false) {
                $problems[] = 'multipack_identifiers_required';
            }
            foreach (['gtin', 'mpn'] as $identifier) {
                if (isset($review[$identifier]) && (($product['manufacturer']['identifier_scope'] ?? null) !== 'unit_bag'
                    || $review[$identifier] !== ($product['manufacturer'][$identifier] ?? null))) {
                    $problems[] = 'multipack_identifier_mismatch';
                }
            }
        }
        foreach ($this->reviewedImages($product, $review) as $image) {
            if ((is_int($image['width'] ?? null) && $image['width'] < 500)
                || (is_int($image['height'] ?? null) && $image['height'] < 500)) {
                $problems[] = 'image_resolution_below_preparation_target';
            }
        }
        // AI provenance must be confirmed, not guessed from the language or source.
        foreach (['title_source', 'description_source'] as $field) {
            if (! in_array($review[$field] ?? null, ['default', 'trained_algorithmic_media'], true)
                || (isset($product[$field]) && ($review[$field] ?? null) !== $product[$field])) {
                $problems[] = 'text_provenance_required';
                break;
            }
        }

        return array_values(array_unique($problems));
    }

    /** Missing structured specifications remain visible after an explicit essential-details review. */
    public function warnings(array $product, array $review): array
    {
        $warnings = [];
        if (($review['essential_details_verified'] ?? null) === true
            && in_array('missing_specifications', $product['issues'] ?? [], true)) {
            $warnings[] = 'missing_specifications';
        }
        if (! ($product['has_variants'] ?? false) && ($review['energy_label_required'] ?? null) === true
            && array_intersect(config('merchant.target_countries', []), self::EU_COUNTRIES) !== []
            && $this->googleEnergyLookupAllowed($product, $review)) {
            $warnings[] = 'eprel_google_lookup_pending';
        }

        return $warnings;
    }

    private function googleEnergyLookupAllowed(array $product, array $review): bool
    {
        if (($review['allow_google_energy_lookup'] ?? null) !== true || ($review['approved'] ?? null) !== true
            || ($review['eprel_code'] ?? null) !== null) {
            return false;
        }
        // Google may resolve EPREL from reviewed manufacturer identifiers; a match is not guaranteed.
        $mpn = $review['mpn'] ?? null;
        $brand = $product['brand'] ?? null;

        return self::validGtin($review['gtin'] ?? null)
            || (is_string($mpn) && trim($mpn) !== '' && mb_strlen($mpn) <= 70
                && is_string($brand) && trim($brand) !== '' && mb_strlen($brand) <= 70);
    }

    /** Shared parent price/inventory (DECISION-061); separate identities, images and factual reviews. */
    public function variantProducts(array $product, array $review, string $locale): array
    {
        $items = [];
        foreach ($product['variants'] ?? [] as $index => $option) {
            if (! is_string($option['title'] ?? null) || trim($option['title']) === '') {
                return [];
            }
            $id = 'v'.($index + 1);
            $variantReview = is_array($review['variants'][$id] ?? null) ? $review['variants'][$id] : [];
            $variant = $product;
            // The parent identifiers do not establish this option's manufacturer identity.
            unset($variant['manufacturer']);
            $variant['has_variants'] = false;
            $variant['variant_id'] = $id;
            $variant['variant_title'] = $option['title'];
            $variant['variants'] = [];
            $suffix = ' — '.$option['title'];
            $variant['name'] = rtrim(mb_substr($product['name'], 0, max(1, 150 - mb_strlen($suffix)))).$suffix;
            $variant['title_source'] = 'trained_algorithmic_media';
            $variant['variant_attributes'] = [];
            foreach (self::VARIANT_ATTRIBUTE_LIMITS as $attribute => $limit) {
                $value = $variantReview['attributes'][$locale][$attribute] ?? null;
                if (is_string($value) && trim($value) !== ''
                    && ! preg_match('/[\x00-\x1F]/', $value)) {
                    // Preserve an overlong value so problems() reports it instead of silently dropping it.
                    $variant['variant_attributes'][$attribute] = $value;
                }
            }
            // A gallery is not proof that a photo represents this particular finish.
            $variant['images'] = [];
            if (($variantReview['image_verified'] ?? false) === true
                && ($variantReview['option_titles'][$locale] ?? null) === $option['title']) {
                foreach ($variantReview['images'] ?? [] as $url) {
                    foreach ($product['images'] ?? [] as $image) {
                        if ($image['local_url'] === $url && ! in_array($image, $variant['images'], true)) {
                            $variant['images'][] = $image;
                        }
                    }
                }
            }
            $items[] = [$variant, $variantReview];
        }

        return $items;
    }

    public static function id(array $product): string
    {
        return 'homya-'.substr(hash('sha256', $product['slug']), 0, 40).(isset($product['variant_id']) ? '-'.$product['variant_id'] : '');
    }

    /** A reviewed Google taxonomy ID; this validates syntax, not category attribution. */
    public static function googleCategory(mixed $category): ?string
    {
        if (is_int($category)) {
            $category = (string) $category;
        }

        return is_string($category) && preg_match('/\A[1-9][0-9]{0,5}\z/', $category) ? $category : null;
    }

    /** Reviewed registry ID; the local size bound does not verify EPREL attribution. */
    public static function eprelCode(mixed $code): ?string
    {
        return is_string($code) && preg_match('/\A[1-9][0-9]{0,19}\z/', $code) ? $code : null;
    }

    public static function productType(array $product, string $locale): ?string
    {
        $universe = __('home.categories.'.($product['universe'] ?? '').'.name', [], $locale);
        $category = $product['category']['name'] ?? null;
        if (! is_string($universe) || str_starts_with($universe, 'home.categories.')
            || ! is_string($category) || trim($category) === '') {
            return null;
        }
        // Category names come from the same localized projection as the page.
        $levels = array_unique(array_map(fn ($value) => trim(preg_replace('/\s+/u', ' ', $value)), [$universe, $category]));
        $type = implode(' > ', $levels);

        return mb_strlen($type) <= 750 && ! preg_match('/[\x00-\x1F]/', $type) ? $type : null;
    }

    public function entries(string $locale): array
    {
        app()->setLocale($locale);
        $summaries = $this->catalogue->products();
        if ($this->catalogue->sourceUnavailable() || $this->catalogue->translationUnavailable() || $summaries->isEmpty()) {
            throw new \RuntimeException('Catalogue unavailable.');
        }
        $reviews = $this->reviews();
        $inventory = $this->inventoryRows();
        $items = [];
        foreach ($summaries as $summary) {
            $slug = $summary['slug'];
            if (($reviews[$slug]['approved'] ?? false) !== true) {
                continue;
            }
            $product = $this->catalogue->product($slug);
            if ($product === null || $this->problems($product, $reviews[$slug], $locale) !== []) {
                continue;
            }
            $stock = $inventory->get($slug);
            if ($this->inventoryProblems($product, $stock) !== []) {
                continue;
            }
            if ($product['has_variants']) {
                foreach ($this->variantProducts($product, $reviews[$slug], $locale) as [$variant, $variantReview]) {
                    $items[] = [$variant, $variantReview, (int) $stock->quantity > (int) $stock->reserved];
                }
            } else {
                $items[] = [$product, $reviews[$slug], (int) $stock->quantity > (int) $stock->reserved];
            }
        }
        if ($this->catalogue->sourceUnavailable() || $this->catalogue->translationUnavailable()) {
            throw new \RuntimeException('Catalogue unavailable.');
        }

        return $items;
    }

    /** One read-only query shared by the export and private audit; no order expiry or writes. */
    public function inventoryRows(): Collection
    {
        return app(OrderStore::class)->connection()->table('commerce_inventory')
            ->select(['slug', 'reference', 'sale_enabled', 'approved_price_cents', 'validated_at', 'quantity', 'reserved'])
            ->get()->keyBy('slug');
    }

    public function inventoryProblems(array $product, ?object $stock): array
    {
        if ($stock === null) {
            return ['inventory_missing'];
        }
        $problems = [];
        if (! ($stock->sale_enabled ?? false)) {
            $problems[] = 'inventory_sale_disabled';
        }
        if (($stock->validated_at ?? null) === null) {
            $problems[] = 'inventory_not_validated';
        }
        if (($stock->reference ?? null) !== ($product['reference'] ?? null)) {
            $problems[] = 'inventory_reference_mismatch';
        }
        if ((int) ($stock->approved_price_cents ?? 0) !== ($product['price_cents'] ?? null)) {
            $problems[] = 'inventory_price_mismatch';
        }
        if ((int) ($stock->quantity ?? 0) < 0 || (int) ($stock->reserved ?? 0) < 0
            || (int) ($stock->quantity ?? 0) < (int) ($stock->reserved ?? 0)) {
            $problems[] = 'inventory_reservations_invalid';
        }

        return $problems;
    }

    public function xml(array $items, string $locale): string
    {
        $xml = new XMLWriter;
        $xml->openMemory();
        $xml->startDocument('1.0', 'UTF-8');
        $xml->startElement('rss');
        $xml->writeAttribute('version', '2.0');
        $xml->writeAttribute('xmlns:g', 'http://base.google.com/ns/1.0');
        $xml->startElement('channel');
        $xml->writeElement('title', 'HOME CONFORT');
        $xml->writeElement('link', $this->seo->url('home', compact('locale')));
        $xml->writeElement('description', 'HOME CONFORT — '.strtoupper($locale));
        foreach ($items as [$product, $review, $inStock]) {
            $xml->startElement('item');
            // Internal feed ID, stable across languages, never a fabricated GTIN/MPN.
            $this->attribute($xml, 'id', self::id($product));
            if (isset($product['variant_id'])) {
                $this->attribute($xml, 'item_group_id', self::id(['slug' => $product['slug']]));
                foreach ($product['variant_attributes'] as $attribute => $value) {
                    $this->attribute($xml, $attribute, $value);
                }
            }
            foreach (['title' => 'name', 'description' => 'description'] as $field => $key) {
                if ($review[$field.'_source'] === 'trained_algorithmic_media') {
                    $xml->startElement('g:structured_'.$field);
                    $this->attribute($xml, 'digital_source_type', 'trained_algorithmic_media');
                    $this->attribute($xml, 'content', $product[$key]);
                    $xml->endElement();
                } else {
                    $this->attribute($xml, $field, $product[$key]);
                }
            }
            $this->attribute($xml, 'link', $this->seo->url('product', ['locale' => $locale, 'slug' => $product['slug']] + (isset($product['variant_id']) ? ['variant' => $product['variant_id']] : [])));
            $reviewedImages = $this->reviewedImages($product, $review);
            if (array_key_exists('approved_google_image_urls', $review) && $reviewedImages === []) {
                throw new \RuntimeException('Invalid reviewed product images.');
            }
            foreach ($reviewedImages as $image) {
                if ((is_int($image['width'] ?? null) && $image['width'] < 500)
                    || (is_int($image['height'] ?? null) && $image['height'] < 500)) {
                    throw new \RuntimeException('Product image below preparation target.');
                }
            }
            foreach ($reviewedImages as $index => $image) {
                if ($index > 10) {
                    break;
                }
                $this->attribute($xml, $index === 0 ? 'image_link' : 'additional_image_link', $this->seo->origin().$image['local_url']);
            }
            $this->attribute($xml, 'price', number_format($product['price_cents'] / 100, 2, '.', '').' EUR');
            if ($pack = self::salesPack($product)) {
                if (($review['multipack'] ?? null) !== $pack['multipack'] || ($review['identifier_scope'] ?? null) !== 'unit_bag') {
                    throw new \RuntimeException('Unreviewed retailer multipack.');
                }
                foreach ($pack as $attribute => $value) {
                    $this->attribute($xml, $attribute, (string) $value);
                }
            } elseif (isset($product['sales_pack']) || isset($review['multipack'])) {
                throw new \RuntimeException('Invalid retailer multipack.');
            }
            $this->attribute($xml, 'availability', $inStock ? 'in_stock' : 'out_of_stock');
            $this->attribute($xml, 'condition', self::condition($product));
            if ($type = self::productType($product, $locale)) {
                $this->attribute($xml, 'product_type', $type);
            }
            if ($category = self::googleCategory($review['google_product_category'] ?? null)) {
                $this->attribute($xml, 'google_product_category', $category);
            }
            if ($code = self::eprelCode($review['eprel_code'] ?? null)) {
                $xml->startElement('g:certification');
                $this->attribute($xml, 'certification_authority', 'EC');
                $this->attribute($xml, 'certification_name', 'EPREL');
                $this->attribute($xml, 'certification_code', $code);
                $xml->endElement();
            }
            if (! empty($product['brand'])) {
                $this->attribute($xml, 'brand', $product['brand']);
            }
            foreach (['gtin', 'mpn'] as $identifier) {
                if (isset($review[$identifier])) {
                    $this->attribute($xml, $identifier, $review[$identifier]);
                }
            }
            if (($review['identifier_exists'] ?? null) === false) {
                $this->attribute($xml, 'identifier_exists', 'no');
            }
            $xml->endElement();
        }
        $xml->endElement();
        $xml->endElement();
        $xml->endDocument();

        return $xml->outputMemory();
    }

    private function attribute(XMLWriter $xml, string $name, string $value): void
    {
        $xml->writeElement('g:'.$name, $value);
    }
}
