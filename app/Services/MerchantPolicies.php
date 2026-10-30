<?php

namespace App\Services;

final class MerchantPolicies
{
    /** Only supplement an existing offer; this service never makes one purchasable. */
    public function forOffer(string $returnUrl): array
    {
        $countries = $this->countries();
        if ($countries === null) {
            return [];
        }

        $policies = [];
        $handling = $this->days(config('merchant-policies.shipping.handling_days'));
        $transit = $this->days(config('merchant-policies.shipping.transit_days'));
        // The checkout currently records zero shipping. Refuse to describe an
        // unimplemented paid rate or an unverified dispatch location.
        if (config('merchant-policies.shipping.verified') === true
            && config('shipping.dispatch.verified') === true
            && config('shipping.dispatch.address_structured.country') === 'FR'
            && config('commerce.currency') === 'EUR' && config('commerce.shipping_cents') === 0
            && $handling !== null && $transit !== null) {
            $policies['shippingDetails'] = array_map(fn ($country) => [
                '@type' => 'OfferShippingDetails',
                'shippingDestination' => ['@type' => 'DefinedRegion', 'addressCountry' => $country],
                'shippingRate' => ['@type' => 'MonetaryAmount', 'value' => '0.00', 'currency' => 'EUR'],
                'deliveryTime' => ['@type' => 'ShippingDeliveryTime',
                    'handlingTime' => $handling, 'transitTime' => $transit],
            ], $countries);
        }

        if ($policy = $this->returnPolicy($returnUrl)) {
            $policies['hasMerchantReturnPolicy'] = ['@id' => $policy['@id']];
        }

        return $policies;
    }

    /** Google's organization-level Option B links to the complete published policy. */
    public function returnPolicy(string $returnUrl): ?array
    {
        $days = config('merchant-policies.returns.days');
        if ($this->countries() !== null
            && config('merchant-policies.returns.verified') === true
            && config('shipping.returns.verified') === true
            && config('shipping.returns.address_structured.country') === 'FR'
            && config('merchant-policies.returns.free') === true
            && is_int($days) && $days > 0
            && filter_var($returnUrl, FILTER_VALIDATE_URL) && parse_url($returnUrl, PHP_URL_SCHEME) === 'https') {
            return [
                '@type' => 'MerchantReturnPolicy', '@id' => $returnUrl.'#return-policy',
                'merchantReturnLink' => $returnUrl,
            ];
        }

        return null;
    }

    private function countries(): ?array
    {
        $countries = config('commerce.countries');
        if (! is_array($countries) || $countries === [] || ! array_is_list($countries)
            || count(array_unique($countries, SORT_REGULAR)) !== count($countries)
            || array_filter($countries, fn ($country) => ! is_string($country)
                || ! in_array($country, ['FR', 'BE', 'DE'], true))) {
            return null;
        }

        return $countries;
    }

    private function days(mixed $range): ?array
    {
        if (! is_array($range) || ! is_int($range['min'] ?? null) || ! is_int($range['max'] ?? null)
            || $range['min'] < 0 || $range['max'] < $range['min']) {
            return null;
        }

        return ['@type' => 'QuantitativeValue', 'minValue' => $range['min'],
            'maxValue' => $range['max'], 'unitCode' => 'DAY'];
    }
}
