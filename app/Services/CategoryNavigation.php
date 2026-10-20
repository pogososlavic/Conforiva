<?php

namespace App\Services;

use Illuminate\Support\Collection;

class CategoryNavigation
{
    /** All published appliance brands, using the exact catalogue filter values. */
    public function applianceBrands(?Collection $products = null): array
    {
        return ($products ?? app(Catalogue::class)->products())
            ->where('universe', 'appliances')
            ->pluck('brand')
            ->filter(fn ($brand) => is_string($brand) && trim($brand) !== '')
            ->unique()
            ->sort(SORT_NATURAL | SORT_FLAG_CASE)
            ->values()->all();
    }

    /** Published catalogue entries are the source of truth for localized subcategories. */
    public function groups(?Collection $products = null): array
    {
        return ($products ?? app(Catalogue::class)->products())
            ->groupBy('universe')
            ->map(fn ($products) => $products
                ->pluck('category')
                ->filter(fn ($category) => is_array($category) && ! empty($category['slug']) && ! empty($category['name']))
                ->unique('slug')
                ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
                ->values()->all())
            ->all();
    }
}
