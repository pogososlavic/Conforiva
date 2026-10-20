<?php

namespace App\Services;

/** Public editorial projection. Never changes the immutable supplier archive. */
final class CatalogueContent
{
    private static ?array $rules = null;

    public static function apply(array $source, array $product, string $locale): array
    {
        self::$rules ??= json_decode(file_get_contents(__DIR__.'/../../lang/catalogue-content.json'), true, flags: JSON_THROW_ON_ERROR);
        $rule = self::$rules[$source['slug']] ?? null;
        if ($rule && hash_equals($rule['source_name_sha256'], hash('sha256', $source['name']))) {
            $product['name'] = $rule['names'][$locale];
            $product['title_source'] = 'trained_algorithmic_media';
            if ($rule['requires_review'] ?? false) {
                $product['issues'][] = 'title_content_requires_review';
            }
        }
        if (! array_key_exists('description', $product)) {
            return $product;
        }
        if (trim($product['description']) === '') {
            // Only already displayed facts; no invented benefits, certification or specifications.
            $product['description'] = $product['name'].'. '.match ($locale) {
                'en' => 'Catalogue reference: ', 'de' => 'Katalogreferenz: ', default => 'Référence catalogue : ',
            }.$product['reference'].'.';
            $product['description_source'] = 'trained_algorithmic_media';
            $product['description_hidden'] = false;
            $product['issues'] = array_values(array_diff($product['issues'], ['description_requires_review', 'unresolved_description']));
        }
        // Exact duplicates are ingestion artefacts. Different values remain unresolved.
        $groups = [];
        foreach ($source['specifications'] ?? [] as $index => $spec) {
            $groups[trim($spec['label'])][$index] = trim($spec['value']);
        }
        $specifications = [];
        $conflicts = [];
        foreach ($groups as $sourceLabel => $values) {
            $first = array_key_first($values);
            $spec = $product['specifications'][$first];
            if (count(array_unique($values)) > 1 && self::isReviewedCookingList($source, $sourceLabel, $values)) {
                // Recover a cumulative list already explicit in these exact source titles.
                // This structural correction is not a manufacturer verification.
                $spec['value'] = implode(' / ', array_map(
                    static fn ($index) => $product['specifications'][$index]['value'],
                    array_keys($values)));
            } elseif (count(array_unique($values)) > 1) {
                $conflicts[] = ['label' => $spec['label'], 'values' => array_values(array_unique($values))];
                // Do not publicly present two incompatible values as established facts.
                $spec['value'] = match ($locale) {
                    'en' => 'Awaiting verification', 'de' => 'Wird geprüft', default => 'En cours de vérification',
                };
            }
            $specifications[] = $spec;
        }
        $product['specifications'] = $specifications;
        $product['specification_reviews'] = $conflicts;
        if (! $conflicts) {
            $product['issues'] = array_values(array_diff($product['issues'], ['conflicting_specification']));
        } elseif (! in_array('conflicting_specification', $product['issues'], true)) {
            $product['issues'][] = 'conflicting_specification';
        }
        // Detect an impossible interval; never guess a missing decimal point or nominal power.
        $powers = [];
        foreach ($source['specifications'] ?? [] as $index => $spec) {
            if (preg_match('/\APuissance (?:(?:nominale|thermique) )?(min|max) \(kW\)\z/u', $spec['label'], $match)
                && preg_match('/\A\d+(?:[.,]\d+)?\z/', trim($spec['value']))) {
                $powers[$match[1]] = (float) str_replace(',', '.', $spec['value']);
            }
        }
        if (isset($powers['min'], $powers['max']) && $powers['min'] > $powers['max']) {
            $product['issues'][] = 'inconsistent_power_interval';
            foreach ($source['specifications'] as $index => $spec) {
                if (preg_match('/\APuissance (?:(?:nominale|thermique) )?(?:min|max) \(kW\)\z/u', $spec['label'])) {
                    $label = $product['specifications'][array_search(trim($spec['label']), array_keys($groups), true)]['label'];
                    $product['specification_reviews'][] = ['label' => $label, 'values' => [$spec['value']], 'reason' => 'minimum_exceeds_maximum'];
                    foreach ($product['specifications'] as &$presented) {
                        if ($presented['label'] === $label) {
                            $presented['value'] = match ($locale) {
                                'en' => 'Awaiting verification', 'de' => 'Wird geprüft', default => 'En cours de vérification',
                            };
                        }
                    }
                    unset($presented);
                }
            }
        }
        $product['issues'] = array_values(array_unique($product['issues']));

        return self::completeVerifiedSupraOscarSpecifications($source, $product, $locale);
    }

    /** Exact OSCAR technical sheet, https://supra.fr/?jet_download=23545, reviewed 20.10.2026. */
    private static function completeVerifiedSupraOscarSpecifications(array $source, array $product, string $locale): array
    {
        if (($source['slug'] ?? null) !== 'poele-a-bois-etanche-en-fonte-supra-oscar-4-8-kw'
            || ($source['reference'] ?? null) !== '44294'
            || ($source['name'] ?? null) !== 'Poêle à bois étanche en fonte – SUPRA Oscar 4.8 kW'
            || ($source['has_variants'] ?? null) !== false || ($source['variants'] ?? []) !== []
            || ($source['specifications'] ?? null) !== [] || ($product['specifications'] ?? null) !== []
            || ! in_array($locale, ['fr', 'en', 'de'], true)) {
            return $product;
        }
        $product['specifications'] = match ($locale) {
            'fr' => [
                ['label' => 'Matériau principal', 'value' => 'Acier'],
                ['label' => 'Poids net', 'value' => '75 kg'],
                ['label' => 'Poids brut', 'value' => '85 kg'],
                ['label' => 'Dimensions (largeur × hauteur × profondeur)', 'value' => '44,7 × 63,6 × 36,9 cm'],
            ],
            'en' => [
                ['label' => 'Main material', 'value' => 'Steel'],
                ['label' => 'Net weight', 'value' => '75 kg'],
                ['label' => 'Gross weight', 'value' => '85 kg'],
                ['label' => 'Dimensions (width × height × depth)', 'value' => '44.7 × 63.6 × 36.9 cm'],
            ],
            'de' => [
                ['label' => 'Hauptmaterial', 'value' => 'Stahl'],
                ['label' => 'Nettogewicht', 'value' => '75 kg'],
                ['label' => 'Bruttogewicht', 'value' => '85 kg'],
                ['label' => 'Abmessungen (Breite × Höhe × Tiefe)', 'value' => '44,7 × 63,6 × 36,9 cm'],
            ],
        };
        $product['issues'] = array_values(array_diff($product['issues'], ['missing_specifications']));

        return $product;
    }

    /** Only the two reviewed Olympia sources explicitly state both cooking facilities. */
    private static function isReviewedCookingList(array $source, string $label, array $values): bool
    {
        $models = [
            'chauffage-olympia-conf-poeles-a-bois' => [
                'reference' => 'olympia_conf',
                'name' => 'Poêle à bois avec four et plaque de cuisson Olympia Plus · 13 Kw',
            ],
            'chauffage-olympia-pierreollaire-poeles-a-bois' => [
                'reference' => 'Olympia-PierreOllaire',
                'name' => 'Poêle à bois avec four et plaque de cuisson Olympia Pierre Ollaire · 12,2 Kw',
            ],
        ];
        $model = $models[$source['slug'] ?? ''] ?? null;
        if ($model === null || ($source['reference'] ?? null) !== $model['reference']
            || ($source['name'] ?? null) !== $model['name']
            || ($source['has_variants'] ?? null) !== false || ($source['variants'] ?? []) !== []
            || ($source['universe'] ?? null) !== 'heating' || $label !== 'Option de cuisson') {
            return false;
        }
        // No unknown/negative choice, repeated option or neighboring model is generalized.
        $values = array_values($values);
        sort($values);

        return $values === ['Avec four', 'Avec plaque de cuisson'];
    }
}
