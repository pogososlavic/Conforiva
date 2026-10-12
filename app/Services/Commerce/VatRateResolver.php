<?php

namespace App\Services\Commerce;

/** Resolves the VAT rate captured in an order item snapshot for destination taxation. */
final class VatRateResolver
{
    /** @return array{vat_class: string, vat_rate_basis: int} */
    public function forProduct(array $product, string $country): array
    {
        $standard = config('commerce.destination_vat_rates.standard.'.$country);
        if ($standard === null) {
            throw new \InvalidArgumentException('Unsupported VAT destination.');
        }

        $slug = strtolower((string) ($product['slug'] ?? ''));
        $category = (string) ($product['category']['slug'] ?? '');
        $isWoodHeating = ($product['universe'] ?? null) === 'firewood'
            && $category === 'chauffage-granules-combustibles'
            && preg_match('/(?:bois|buches|bûches|granules-de-bois|granules-bois|pellets-de-bois|wood-pellets)/u', $slug) === 1;

        return [
            'vat_class' => $isWoodHeating ? 'wood_heating' : 'standard',
            'vat_rate_basis' => $isWoodHeating ? (int) config('commerce.destination_vat_rates.wood_heating.'.$country, $standard) : (int) $standard,
        ];
    }
}
