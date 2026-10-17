<?php

namespace App\Services;

/** Verified, model-specific documents. Never infer a label from a category or a variant parent. */
final class ProductEnergyLabels
{
    public function forProduct(array $product, string $locale): ?array
    {
        $entry = config('product-energy.'.($product['slug'] ?? ''));
        if (! is_array($entry) || ($product['reference'] ?? null) !== ($entry['reference'] ?? null)
            || ($product['has_variants'] ?? false) || isset($product['variant_id'])
            || ! in_array($locale, ['fr', 'en', 'de'], true)) {
            return null;
        }
        $sheet = $entry['sheets'][$locale] ?? null;
        foreach ([$entry['label'] ?? null, $entry['image'] ?? null, $sheet] as $path) {
            if (! is_string($path) || ! preg_match('~\A/media/energy/[a-z0-9-]+\.(?:pdf|png)\z~D', $path)
                || ! is_file(public_path(ltrim($path, '/')))) {
                return null;
            }
        }

        return $entry + ['sheet' => $sheet];
    }
}
