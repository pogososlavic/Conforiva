<?php

namespace App\Services;

/** ETNA Fantea official specifications reviewed on 17 October 2026; archive stays immutable. */
final class CatalogueSafetyCorrections
{
    public static function apply(array $source, array $product, string $locale): array
    {
        if (($source['slug'] ?? null) !== 'chauffage-fantea32en-chaudieres'
            || ($source['reference'] ?? null) !== 'FANTEA32EN'
            || ($source['name'] ?? null) !== 'Chaudière à Granulés Fantea'
            || ! in_array($locale, ['fr', 'en', 'de'], true)) {
            return $product;
        }
        $descriptions = [
            'fr' => 'Chaudière à granulés ETNA Fantea de 32 kW, référence FANTEA32EN, avec un réservoir à granulés de 85 kg et une capacité en eau de 60 L. Faites confirmer les conditions de raccordement par un installateur qualifié.',
            'en' => 'ETNA Fantea 32 kW pellet boiler, reference FANTEA32EN, with an 85 kg pellet hopper and 60 L water capacity. Have the connection requirements confirmed by a qualified installer.',
            'de' => 'ETNA Fantea Pelletkessel mit 32 kW, Referenz FANTEA32EN, mit einem 85-kg-Pelletbehälter und 60 L Wasserkapazität. Lassen Sie die Anschlussbedingungen von einem qualifizierten Installateur bestätigen.',
        ];
        if (isset($descriptions[$locale]) && ($source['description'] ?? null) === 'Poêle à bois ETNA de 32 kW, jusqu\'à 620 m³ de volume chauffé, rendement 90,65 %.') {
            $product['description'] = $descriptions[$locale];
        }
        if (! isset($product['specifications'])) {
            return $product;
        }
        // Only reviewed fields of this exact model, matched by their localized labels.
        $labels = [
            'fr' => ['Réservoir eau (L)', 'Capacité réservoir eau (L)', 'Consommation de granulés (kg/h)', 'Rendement (%)'],
            'en' => ['Water tank (L)', 'Water tank capacity (L)', 'Pellet consumption (kg/h)', 'Efficiency (%)'],
            'de' => ['Wassertank (L)', 'Wassertankkapazität (L)', 'Pelletverbrauch (kg/h)', 'Wirkungsgrad (%)'],
        ][$locale] ?? [];
        $rules = array_combine($labels, ['remove', '60', $locale === 'en' ? '1.4' : '1,4', $locale === 'en' ? '90.6' : '90,6']) ?: [];
        $specifications = [];
        foreach ($product['specifications'] as $spec) {
            $value = $rules[$spec['label']] ?? null;
            if ($value === 'remove' && in_array(mb_strtoupper($spec['value']), ['NON', 'NO', 'NEIN'], true)) {
                continue;
            }
            if ($value !== null && $value !== 'remove') {
                $spec['value'] = $value;
            }
            $specifications[] = $spec;
        }
        $product['specifications'] = $specifications;

        return $product;
    }
}
