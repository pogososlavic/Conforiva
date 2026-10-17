<?php

namespace App\Services;

final class ProductSafetyInformation
{
    public function forProduct(array $product): ?array
    {
        $entry = config('product-safety.'.($product['slug'] ?? ''));
        if (! is_array($entry) || ($entry['reference'] ?? null) !== ($product['reference'] ?? null)
            || ($product['has_variants'] ?? false) || isset($product['variant_id'])) {
            return null;
        }

        return $entry;
    }
}
