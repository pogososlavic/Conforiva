<?php

namespace App\Services;

final class ProductContent
{
    /** Use the same public description and characteristics as the visible page. */
    public function metaDescription(array $product, ?string $description, array $specifications = []): string
    {
        $name = $this->plain($product['name'] ?? '');
        $description = $this->plain($description ?? '');
        if ($description !== '' && $description !== $name) {
            return Seo::text(mb_stripos($description, $name) !== false ? $description : $description.'. '.$name);
        }

        $parts = [$name];
        $category = $this->plain($product['category']['name'] ?? '');
        if ($category !== '' && mb_stripos($name, $category) === false) {
            $parts[] = $category;
        }
        $reference = $this->plain($product['reference'] ?? '');
        if ($reference !== '' && mb_stripos($name, $reference) === false) {
            $parts[] = __('catalogue.reference').' '.$reference;
        }
        foreach (array_slice($specifications, 0, 2) as $specification) {
            $label = $this->plain($specification['label'] ?? '');
            $value = $this->plain($specification['value'] ?? '');
            if ($label !== '' && $value !== '') {
                $parts[] = $label.' : '.$value;
            }
        }

        return Seo::text(implode('. ', array_filter($parts)));
    }

    public function guide(array $product): array
    {
        $universe = $product['universe'] ?? null;
        $key = is_string($universe) && in_array($universe, ['firewood', 'heating', 'appliances', 'furniture', 'televisions'], true)
            ? $universe : 'other';

        // Category purpose takes precedence over the broad navigation department.
        $adviceKey = match ($product['category']['slug'] ?? null) {
            'mobilier-cheminees-au-bioethanol' => 'bioethanol',
            'mobilier-fours-a-pizza-barbecues' => 'outdoor_cooking',
            'chauffage-pieces-detachees' => 'spare_parts',
            'chauffage-outils-entretien' => 'maintenance',
            default => $key,
        };

        return ['universe' => $key === 'other' ? null : $key, 'advice' => __('product-content.guides.'.$adviceKey)];
    }

    private function plain(string $value): string
    {
        return trim(preg_replace('/\s+/u', ' ', strip_tags($value)));
    }
}
