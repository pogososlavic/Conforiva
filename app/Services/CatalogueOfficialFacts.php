<?php

namespace App\Services;

/** Reviewed manufacturer facts, guarded against every change to the source archive. */
final class CatalogueOfficialFacts
{
    private static ?array $rules = null;

    public static function sourceHash(array $source): string
    {
        return hash('sha256', json_encode($source, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }

    private static function rules(): array
    {
        self::$rules ??= json_decode(file_get_contents(__DIR__.'/../../resources/data/official-product-facts.json'), true, flags: JSON_THROW_ON_ERROR)['products'];

        return self::$rules;
    }

    /** Compact cards need the full guarded archive only for reviewed brand/title changes. */
    public static function requiresCompleteSource(array $source): bool
    {
        $rule = self::rules()[$source['slug'] ?? ''] ?? [];

        return ! array_key_exists('description', $source) && (isset($rule['brand']) || isset($rule['names']));
    }

    public static function apply(array $source, array $product, string $locale): array
    {
        if (! in_array($locale, ['fr', 'en', 'de'], true)
            || ! array_key_exists('description', $source)
            || ($source['has_variants'] ?? null) !== false || ($source['variants'] ?? []) !== []) {
            return $product;
        }
        $rule = self::rules()[$source['slug'] ?? ''] ?? null;
        if (! is_array($rule) || ($rule['reference'] ?? null) !== ($source['reference'] ?? null)
            || ! hash_equals($rule['source_sha256'], self::sourceHash($source))) {
            return $product;
        }
        if (isset($rule['brand'])) {
            $product['brand'] = $rule['brand'];
        }
        if (isset($rule['names'][$locale])) {
            $product['name'] = $rule['names'][$locale];
            $product['title_source'] = 'trained_algorithmic_media';
        }
        if (isset($rule['categories'][$locale])) {
            $product['category'] = $rule['categories'][$locale];
        }
        if (isset($rule['sales_pack'])) {
            $product['sales_pack'] = $rule['sales_pack'];
        }
        if (! isset($product['specifications'])) {
            return $product;
        }
        $completeEmpty = ($rule['specification_mode'] ?? null) === 'complete_empty';
        if ($completeEmpty && (($source['specifications'] ?? null) !== [] || $product['specifications'] !== [])) {
            return $product;
        }
        if (isset($rule['descriptions'][$locale])) {
            $product['description'] = $rule['descriptions'][$locale];
            $product['description_source'] = 'trained_algorithmic_media';
            $product['description_hidden'] = false;
        }
        $facts = $rule['specifications'][$locale] ?? [];
        if ($facts !== []) {
            $remove = $rule['remove_labels'][$locale] ?? [];
            $product['specifications'] = array_values(array_filter($product['specifications'],
                static fn (array $spec): bool => ! in_array($spec['label'], $remove, true)));
            foreach ($facts as $fact) {
                if (! in_array($fact, $product['specifications'], true)) {
                    $product['specifications'][] = $fact;
                }
            }
            $previousReviews = $product['specification_reviews'] ?? [];
            $product['specification_reviews'] = array_values(array_filter($previousReviews,
                static fn (array $review): bool => ! in_array($review['label'], $remove, true)));
            if ($completeEmpty) {
                $product['issues'] = array_values(array_diff($product['issues'], ['missing_specifications']));
            }
            if ($previousReviews !== [] && $product['specification_reviews'] === []) {
                $product['issues'] = array_values(array_diff($product['issues'], ['conflicting_specification']));
            }
        }
        // Partial component facts never lift the unresolved offer/packaging review.
        foreach ($rule['additional_issues'] ?? [] as $issue) {
            if (! in_array($issue, $product['issues'], true)) {
                $product['issues'][] = $issue;
            }
        }
        $manufacturer = $rule['manufacturer'] ?? [];
        $mpn = $manufacturer['mpn'] ?? null;
        if (MerchantProducts::validGtin($manufacturer['gtin'] ?? null)
            || (is_string($mpn) && trim($mpn) !== '' && mb_strlen($mpn) <= 70 && ! empty($product['brand']))) {
            // This verifies identifiers only. Merchant publication still needs the complete review.
            $product['manufacturer'] = ['verified' => true] + $rule['manufacturer'];
        }

        return $product;
    }
}
