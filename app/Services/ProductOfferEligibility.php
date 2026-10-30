<?php

namespace App\Services;

use App\Services\Commerce\CheckoutReadiness;

/** Shared, non-sensitive checks for a simple product's actionable offer. */
final class ProductOfferEligibility
{
    public function checks(array $product, bool $indexable, array $images): array
    {
        $readiness = app(CheckoutReadiness::class);

        return [
            'public_indexable' => $indexable,
            'checkout_payment_ready' => $readiness->bankReady() || $readiness->cardReady(),
            'simple_product' => ($product['has_variants'] ?? true) === false,
            'price_valid' => is_int($product['price_cents'] ?? null)
                && $product['price_cents'] > 0 && $product['price_cents'] <= config('commerce.max_total_cents'),
            'currency_eur' => ($product['currency'] ?? null) === 'EUR',
            'images_present' => $images !== [],
        ];
    }

    public function eligible(array $product, bool $indexable, array $images): bool
    {
        return ! in_array(false, $this->checks($product, $indexable, $images), true);
    }
}
