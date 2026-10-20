<?php

namespace App\Services;

/** Verified, model-specific documents. Never infer a label from a category or a variant parent. */
final class ProductEnergyLabels
{
    public function forProduct(array $product, string $locale): ?array
    {
        $entry = $this->entry($product, $locale);
        if ($entry === null || array_key_exists('components', $entry)) {
            return null;
        }

        return $this->document($entry, $locale);
    }

    /** One appliance can have separate, model-specific washing and drying labels. */
    public function forProductLabels(array $product, string $locale): array
    {
        return $this->labels($product, $locale, true);
    }

    /** Original documents remain accessible with their actual sheet languages. */
    public function documentsForProduct(array $product, string $locale): array
    {
        return $this->labels($product, $locale, false);
    }

    private function labels(array $product, string $locale, bool $requireSheet): array
    {
        $entry = $this->entry($product, $locale);
        if ($entry === null) {
            return [];
        }
        if (! array_key_exists('components', $entry)) {
            $label = $this->document($entry, $locale, $requireSheet);

            return $label === null ? [] : [$label];
        }
        $components = $entry['components'];
        if (! is_array($components) || ! array_is_list($components) || count($components) !== 2) {
            return [];
        }
        $labels = [];
        $scopes = [];
        foreach ($components as $component) {
            if (! is_array($component) || ! in_array($component['scope'] ?? null, ['washing', 'drying'], true)
                || in_array($component['scope'], $scopes, true)) {
                return [];
            }
            $component['reference'] ??= $entry['reference'];
            $label = $this->document($component, $locale, $requireSheet);
            if ($label === null) {
                // Never show one module as the rating of the complete appliance.
                return [];
            }
            $scopes[] = $component['scope'];
            $labels[] = $label;
        }

        return $labels;
    }

    private function entry(array $product, string $locale): ?array
    {
        $slug = $product['slug'] ?? null;
        $reference = $product['reference'] ?? null;
        if (! is_string($slug) || ! preg_match('/\A[a-z0-9-]+\z/D', $slug)
            || ! is_string($reference) || $reference === '' || ($product['has_variants'] ?? false)
            || isset($product['variant_id']) || ! in_array($locale, ['fr', 'en', 'de'], true)) {
            return null;
        }
        $entry = config('product-energy.'.$slug);

        return is_array($entry) && $reference === ($entry['reference'] ?? null) ? $entry : null;
    }

    private function document(array $entry, string $locale, bool $requireSheet = true): ?array
    {
        if ($requireSheet && ($entry['price_display'] ?? true) === false) {
            return null;
        }
        if (! is_string($entry['reference'] ?? null) || $entry['reference'] === '') {
            return null;
        }
        // Bounds belong to this product's verified label, not a universal A–G scale.
        $classes = ['A+++', 'A++', 'A+', 'A', 'B', 'C', 'D', 'E', 'F', 'G'];
        $bounds = preg_split('/\s*[–—-]\s*/u', $entry['scale'] ?? '');
        $best = array_search($bounds[0] ?? null, $classes, true);
        $worst = array_search($bounds[1] ?? null, $classes, true);
        $rank = array_search($entry['class'] ?? null, $classes, true);
        if (count($bounds) !== 2 || $best === false || $worst === false || $rank === false
            || $best > $worst || $rank < $best || $rank > $worst
            || ! preg_match('/\A#[a-f0-9]{6}\z/iD', $entry['color'] ?? '')) {
            return null;
        }
        $sheet = $entry['sheets'][$locale] ?? null;
        foreach ([
            [$entry['label'] ?? null, 'pdf|jpg|png'],
            [$entry['image'] ?? null, 'png|jpg'],
        ] as [$path, $extensions]) {
            if (! $this->isLocalDocument($path, $extensions)) {
                return null;
            }
        }

        if ($requireSheet && ! $this->isLocalDocument($sheet, 'pdf')) {
            return null;
        }
        $labelPage = array_key_exists('label_page', $entry) ? $entry['label_page'] : 1;
        if (! is_int($labelPage) || $labelPage < 1 || $labelPage > 1000
            || ($labelPage > 1 && ! str_ends_with($entry['label'], '.pdf'))) {
            return null;
        }
        // The importer validates this page against the original PDF's actual page count.
        $labelUrl = $entry['label'].($labelPage > 1 ? '#page='.$labelPage : '');
        $sheets = [];
        $sheetUrls = [];
        $sheetPages = array_key_exists('sheet_pages', $entry) ? $entry['sheet_pages'] : [];
        if (! is_array($sheetPages) || array_diff(array_keys($sheetPages), ['fr', 'en', 'de'])) {
            return null;
        }
        foreach ($sheetPages as $sheetLocale => $page) {
            if (! is_int($page) || $page < 1 || $page > 1000
                || ! $this->isLocalDocument($entry['sheets'][$sheetLocale] ?? null, 'pdf')) {
                return null;
            }
        }
        foreach (['fr', 'en', 'de'] as $sheetLocale) {
            $candidate = $entry['sheets'][$sheetLocale] ?? null;
            if ($this->isLocalDocument($candidate, 'pdf')) {
                $sheets[$sheetLocale] = $candidate;
                $page = $sheetPages[$sheetLocale] ?? 1;
                $sheetUrls[$sheetLocale] = $candidate.($page > 1 ? '#page='.$page : '');
            }
        }

        return array_replace($entry, ['label_url' => $labelUrl, 'sheets' => $sheets, 'sheet' => $sheets[$locale] ?? null,
            'sheet_urls' => $sheetUrls, 'sheet_url' => $sheetUrls[$locale] ?? null,
            'scale_max' => $bounds[0], 'scale_min' => $bounds[1]]);
    }

    private function isLocalDocument(mixed $path, string $extensions): bool
    {
        if (! is_string($path) || ! preg_match('~\A/media/energy/[a-z0-9-]+\.(?:'.$extensions.')\z~D', $path)) {
            return false;
        }
        $file = public_path(ltrim($path, '/'));
        $directory = public_path('media/energy');

        return is_file($file) && ! is_link($file)
            && ! is_link($directory) && ! is_link(public_path('media'))
            && realpath(dirname($file)) === realpath($directory);
    }
}
