<?php

namespace App\Services;

/** Model-specific characteristics; recovered catalogue data never creates an official label. */
final class ProductEnergyCharacteristics
{
    private ?array $products = null;

    public function forProduct(array $product, string $locale): ?array
    {
        $slug = $product['slug'] ?? null;
        $reference = $product['reference'] ?? null;
        if (! is_string($slug) || ! preg_match('/\A[a-z0-9-]+\z/D', $slug)
            || ! is_string($reference) || $reference === '' || ($product['has_variants'] ?? false)
            || isset($product['variant_id']) || ! in_array($locale, ['fr', 'en', 'de'], true)) {
            return null;
        }
        $entry = $this->products()[$slug] ?? null;
        if (! is_array($entry) || ($entry['reference'] ?? null) !== $reference
            || ! in_array($entry['status'] ?? null, ['archived', 'verified', 'mixed'], true)) {
            return null;
        }
        $metrics = [];
        foreach (is_array($entry['metrics'] ?? null) ? $entry['metrics'] : [] as $metric) {
            if (! is_array($metric)) {
                continue;
            }
            $key = $metric['key'] ?? '';
            $label = $this->localized($metric['label'] ?? null, $locale);
            if ($label === null && is_string($key) && preg_match('/\A[a-z0-9_-]+\z/D', $key)
                && trans()->has('energy.metrics.'.$key, $locale, false)) {
                $label = trans('energy.metrics.'.$key, [], $locale);
            }
            $value = $this->localized($metric['value'] ?? null, $locale, true);
            if ($label === null || $value === null || $label === '' || $value === '') {
                continue;
            }
            $context = is_string($metric['context'] ?? null) ? $metric['context'] : null;
            $metrics[] = [
                'label' => $label, 'value' => $value,
                'unit' => is_string($metric['unit'] ?? null) ? $metric['unit'] : null,
                'context' => $context && trans()->has('energy.contexts.'.$context, $locale, false)
                    ? trans('energy.contexts.'.$context, [], $locale) : null,
                'verified' => ($metric['verified'] ?? false) === true && $this->sourceUrl($metric['source_url'] ?? null) !== null,
                'source_url' => $this->sourceUrl($metric['source_url'] ?? null),
            ];
        }
        if ($metrics === []) {
            return null;
        }
        $sources = [];
        foreach (is_array($entry['sources'] ?? null) ? $entry['sources'] : [] as $source) {
            if (! is_array($source) || ! ($url = $this->sourceUrl($source['url'] ?? null))) {
                continue;
            }
            $sources[] = ['url' => $url, 'label' => $this->localized($source['label'] ?? null, $locale)
                ?? (parse_url($url, PHP_URL_HOST) ?: trans('energy.source', [], $locale))];
        }

        $scale = $entry['scale'] ?? null;
        $classes = ['A+++', 'A++', 'A+', 'A', 'B', 'C', 'D', 'E', 'F', 'G'];
        if (! is_array($scale) || ! in_array($scale['min'] ?? null, $classes, true)
            || ! in_array($scale['max'] ?? null, $classes, true)
            || array_search($scale['max'], $classes, true) > array_search($scale['min'], $classes, true)) {
            $scale = null;
        }

        $sourceLabels = $entry['source_labels'][$locale] ?? [];
        $sourceLabels = is_array($sourceLabels) ? array_values(array_filter($sourceLabels,
            static fn ($label) => is_string($label) && trim($label) !== '')) : [];

        return ['reference' => $reference, 'status' => $entry['status'], 'metrics' => $metrics,
            'source_labels' => $sourceLabels, 'sources' => $sources, 'scale' => $scale ? $scale['max'].'–'.$scale['min'] : null];
    }

    /** Update an explicit class claim only when one verified whole-appliance class exists. */
    public function descriptionForProduct(array $product, string $locale): ?string
    {
        $description = $product['description'] ?? null;
        if (! is_string($description) || $this->forProduct($product, $locale) === null) {
            return is_string($description) ? $description : null;
        }
        $entry = $this->products()[$product['slug']];
        $classes = array_values(array_filter($entry['metrics'], static fn ($metric) => is_array($metric) && ($metric['key'] ?? null) === 'energy_class'));
        if (count($classes) !== 1 || ($classes[0]['verified'] ?? false) !== true
            || $this->sourceUrl($classes[0]['source_url'] ?? null) === null
            || ! is_string($classes[0]['value'] ?? null)
            || ! preg_match('/\A[A-G]\+{0,3}\z/D', $classes[0]['value'])) {
            return $description;
        }
        $prefix = match ($locale) {
            'fr' => 'classe(?:\s+énergétique)?\s+',
            'en' => '(?:energy\s+)?class\s+',
            'de' => '(?:Energieeffizienzklasse|Klasse)\s*',
        };

        // Noise and climate classes must never inherit the appliance's energy class.
        $protectedClasses = $locale === 'en'
            ? '\b(?:(?:noise|sound|acoustic)(?:\s+(?:noise|emission))*|climate)\s+class\s+[A-G]\+{0,3}(?![A-Za-z+])(*SKIP)(*F)|'
            : '';
        $description = preg_replace_callback('/'.$protectedClasses.'(\b'.$prefix.')[A-G]\+{0,3}(?![A-Za-z+])/ui',
            static fn ($match) => $match[1].$classes[0]['value'], $description) ?? $description;
        if (($product['universe'] ?? null) !== 'heating') {
            return $description;
        }
        $efficiencies = array_values(array_filter($entry['metrics'], static fn ($metric) => is_array($metric)
            && ($metric['key'] ?? null) === 'useful_efficiency_nominal' && ($metric['verified'] ?? false) === true));
        if (count($efficiencies) !== 1 || ! is_numeric($efficiencies[0]['value'] ?? null)
            || (float) $efficiencies[0]['value'] <= 0 || (float) $efficiencies[0]['value'] > 100
            || $this->sourceUrl($efficiencies[0]['source_url'] ?? null) === null) {
            return $description;
        }
        $prefix = match ($locale) {
            'fr' => 'rendement(?:\s+de)?\s+',
            'en' => 'efficiency(?:\s+of)?\s+',
            'de' => 'Wirkungsgrad(?:\s+von)?\s+',
        };
        $value = (string) $efficiencies[0]['value'];
        if ($locale !== 'en') {
            $value = str_replace('.', ',', $value);
        }

        return preg_replace_callback('/(\b'.$prefix.')[0-9]+(?:[.,][0-9]+)?\s*%/ui',
            static fn ($match) => $match[1].$value.' %', $description) ?? $description;
    }

    /** Move only explicitly mapped energy rows to the valid dedicated profile. */
    public function remainingSpecifications(array $specifications, ?array $profile): array
    {
        if ($profile === null || ! is_array($profile['metrics'] ?? null) || $profile['metrics'] === []
            || ! is_array($profile['source_labels'] ?? null)) {
            return $specifications;
        }
        $labels = array_filter($profile['source_labels'], static fn ($label) => is_string($label) && trim($label) !== '');
        if ($labels === []) {
            return $specifications;
        }

        return array_values(array_filter($specifications,
            static fn ($specification) => ! is_array($specification)
                || ! in_array($specification['label'] ?? null, $labels, true)));
    }

    private function products(): array
    {
        if ($this->products !== null) {
            return $this->products;
        }
        $this->products = [];
        $path = config('product-energy-characteristics.path', resource_path('data/product-energy-characteristics.json'));
        if (! is_string($path) || ! is_file($path) || is_link($path) || filesize($path) > 8 * 1024 * 1024) {
            return [];
        }
        $data = json_decode((string) file_get_contents($path), true);
        if (is_array($data) && ($data['version'] ?? null) === 1 && is_array($data['products'] ?? null)) {
            $this->products = $data['products'];
        }

        return $this->products;
    }

    private function localized(mixed $text, string $locale, bool $neutralValue = false): ?string
    {
        if (is_array($text)) {
            $text = $text[$locale] ?? null;
        } elseif ($locale !== 'fr' && ! is_numeric($text)
            && ! ($neutralValue && is_string($text) && preg_match('/\A(?:[A-G]\+{0,3}|[0-9., \x{00a0}\x{202f}%+–—-]+)\z/uD', $text))) {
            // Do not mix a French source label or descriptive value into another locale.
            return null;
        }

        return is_string($text) || is_int($text) || is_float($text) ? trim((string) $text) : null;
    }

    private function sourceUrl(mixed $url): ?string
    {
        if (! is_string($url) || ! filter_var($url, FILTER_VALIDATE_URL)
            || parse_url($url, PHP_URL_SCHEME) !== 'https' || parse_url($url, PHP_URL_USER) !== null
            || parse_url($url, PHP_URL_PASS) !== null) {
            return null;
        }

        return $url;
    }
}
