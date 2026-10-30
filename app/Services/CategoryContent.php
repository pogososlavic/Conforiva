<?php

namespace App\Services;

use Illuminate\Support\Collection;

final class CategoryContent
{
    public function enrich(array $intro, array $filters, Collection $all, string $locale): array
    {
        $category = $filters['category'] ?? null;
        $selected = $category ? $all->where('category.slug', $category) : $all;
        $universe = $intro['universe'] ?? null;
        if (! $category && $universe) {
            $selected = $all->where('universe', $universe);
        }
        if ($selected->isEmpty()) {
            return $intro;
        }
        $universe ??= $category ? $selected->first()['universe'] ?? null : null;
        $profile = $this->profile($category, $universe);
        $copy = __('category-content.profiles.'.$profile, [], $locale);
        if (! is_array($copy)) {
            return $intro;
        }
        if ($category) {
            $name = $selected->first()['category']['name'];
            $intro['title'] = str_starts_with($category, 'froid-')
                ? __('category-content.refrigerators', ['category' => $name], $locale) : $name;
            $intro['description'] = $intro['title'].' : '.$copy[1];
        } else {
            $intro['title'] = $copy[0];
            $intro['description'] = $copy[1];
        }
        // Buying advice belongs to the first unfiltered listing, not every search
        // result, price facet or paginated continuation.
        $facets = array_diff_key($filters, array_flip(['category', 'universe', 'page', 'sort']));
        if ($facets || (int) ($filters['page'] ?? 1) > 1) {
            return $intro;
        }
        $intro['guide_heading'] = __('category-content.heading', ['category' => $intro['title']], $locale);
        $intro['guide_points'] = array_slice($copy, 2);
        $intro['related_heading'] = __('category-content.related', [], $locale);
        $intro['related_categories'] = [];
        if ($category && $universe) {
            $intro['related_categories'] = $all->where('universe', $universe)
                ->filter(fn (array $product) => ! empty($product['category']['slug']) && $product['category']['slug'] !== $category)
                ->groupBy('category.slug')
                ->map(fn (Collection $items) => $items->first()['category'])
                ->sortBy(function (array $entry) use ($category, $universe, $profile) {
                    $sameFamily = explode('-', $entry['slug'])[0] === explode('-', $category)[0];

                    return ($sameFamily ? '0' : '1').($this->profile($entry['slug'], $universe) === $profile ? '0' : '1').$entry['name'];
                })
                ->take(6)->map(fn (array $entry) => $entry + ['url' => route('catalogue', ['locale' => $locale, 'category' => $entry['slug']])])->values()->all();
        }

        return $intro;
    }

    private function profile(?string $category, ?string $universe): string
    {
        return match (true) {
            $category === 'chauffage-poeles-a-granules-hydro' => 'hydro',
            $category === 'chauffage-chaudieres' => 'boilers',
            $category === 'chauffage-foyers-inserts' => 'inserts',
            in_array($category, ['chauffage-pieces-detachees', 'chauffage-outils-entretien'], true) => 'parts',
            $category !== null && str_starts_with($category, 'chauffage-poeles-') => 'stoves',
            $category === 'mobilier-fours-a-pizza-barbecues' => 'outdoor_cooking',
            $category === 'mobilier-cheminees-au-bioethanol' => 'bioethanol',
            in_array($category, ['mobilier-tables-extensibles', 'mobilier-consoles-extensibles'], true) => 'extending',
            $category === 'cafe-baristina' => 'coffee',
            $category === 'lavage-lave-linge-sechant' => 'washer_dryer',
            $category === 'lavage-lave-linge' => 'washing',
            $category === 'lavage-seche-linge' => 'drying',
            $category === 'chauffage-conduits-de-fumee' => 'flues',
            $category !== null && str_starts_with($category, 'froid-') => 'cooling',
            in_array($universe, ['firewood', 'heating', 'appliances', 'furniture', 'televisions'], true) => $universe,
            default => 'all',
        };
    }
}
