<?php

namespace App\Services;

use Illuminate\Support\Collection;

final class CatalogueNavigation
{
    public function related(Collection $products, array $product, int $limit = 4): Collection
    {
        $category = $product['category']['slug'] ?? null;
        if (! $category || $limit < 1) {
            return collect();
        }

        $siblings = $products->filter(fn (array $entry) => ($entry['category']['slug'] ?? null) === $category)
            ->sortBy('slug')->values();
        $position = $siblings->search(fn (array $entry) => $entry['slug'] === $product['slug']);
        if ($position !== false) {
            // A stable ring distributes links across the category instead of
            // always promoting the same first four alphabetical products.
            $siblings = $siblings->slice($position + 1)->concat($siblings->take($position));
        }

        return $siblings->reject(fn (array $entry) => $entry['slug'] === $product['slug'])
            ->unique('slug')->take($limit)->values();
    }
}
