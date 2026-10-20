<?php

namespace App\Services;

final class CatalogueProductCorrections
{
    // DECISION-050: Bosch EU datasheet KGN36NWEA (06.12.2025), consulted 08.10.2026.
    // https://media3.bosch-home.com/Documents/eudatasheet/de-AT/KGN36NWEA.pdf
    // Public projection only: the immutable source archive remains unchanged.
    private const SOURCE_NAME = 'Réfrigérateur congélateur bas BOSCH 302L No Frost Blanc KGN36NWEA';

    private const NAMES = [
        'fr' => self::SOURCE_NAME,
        'en' => 'Fridge-freezer with bottom freezer BOSCH 302L No Frost White KGN36NWEA',
        'de' => 'Kühl-Gefrierkombination mit Gefrierteil unten BOSCH 302L No Frost Weiß KGN36NWEA',
    ];

    // Exact supplier records audited on 15.10.2026. Only their French public projection is corrected.
    private const LG_DRYERS = [
        'lg-rh0x71wh-seche-linge' => ['reference' => 'RH0X71WH', 'name' => 'LG Sèche linge 10 kg | Molette écran LCD RH0X71WH', 'capacity' => '10'],
        'lg-rh0x76wh-seche-linge' => ['reference' => 'RH0X76WH', 'name' => 'LG Sèche linge 10 kg | Molette écran LCD RH0X76WH', 'capacity' => '10'],
        'lg-rh8n14sl-seche-linge' => ['reference' => 'RH8N14SL', 'name' => 'LG Sèche linge | 8 kg | Pompe à chaleur RH8N14SL', 'capacity' => '8'],
        'lg-rh8n15wh-seche-linge' => ['reference' => 'RH8N15WH', 'name' => 'LG Sèche linge | 8 kg | Pompe à chaleur RH8N15WH', 'capacity' => '8'],
        'lg-rh8n15whb-seche-linge' => ['reference' => 'RH8N15WHB', 'name' => 'LG Sèche linge | 8 kg | Pompe à chaleur RH8N15WHB', 'capacity' => '8'],
        'lg-rh8p12bs-seche-linge' => ['reference' => 'RH8P12BS', 'name' => 'LG Sèche-linge | 18 kg | Dual Dry™ RH8P12BS', 'capacity' => '18'],
        'lg-rh8p12wh-seche-linge' => ['reference' => 'RH8P12WH', 'name' => 'LG Sèche-linge | 18kg | Compresseur DUAL Inverter™ RH8P12WH', 'capacity' => '18'],
        'lg-rh9b15wh-seche-linge' => ['reference' => 'RH9B15WH', 'name' => 'LG Sèche linge | 9 kg | Pompe à chaleur RH9B15WH', 'capacity' => '9'],
        'lg-rh9v50wh-seche-linge' => ['reference' => 'RH9V50WH', 'name' => 'LG Sèche-linge 9 kg | Pompe à chaleur | Condenseur autonettoyant | Moteur et compresseur DUAL Inverter garantis 10 ans RH9V50WH', 'capacity' => '9'],
        'lg-rh9v51wh-seche-linge' => ['reference' => 'RH9V51WH', 'name' => 'LG Sèche-linge 9kg | A++ | Condenseur Autonettoyant | Compresseur DUAL Inverter et moteur Inverter garanti 10 ans RH9V51WH', 'capacity' => '9'],
        'lg-rh9x56wh-lave-linge' => ['reference' => 'RH9X56WH', 'name' => 'LG Sèche linge 9 kg | Pompe à chaleur DUAL Inverter™ RH9X56WH', 'capacity' => '9'],
        'lg-rh9x58wh-seche-linge' => ['reference' => 'RH9X58WH', 'name' => 'LG Sèche linge 9 kg | Pompe à chaleur DUAL Inverter™ RH9X58WH', 'capacity' => '9'],
        'lg-rh9x71wh-seche-linge' => ['reference' => 'RH9X71WH', 'name' => 'LG Sèche linge 9 kg | Molette écran LCD.. RH9X71WH', 'capacity' => '9'],
        'lg-rh9x76bm-seche-linge' => ['reference' => 'RH9X76BM', 'name' => 'LG Sèche linge 9 kg | Molette écran LCD RH9X76BM', 'capacity' => '9'],
        'lg-rh9x76wh-seche-linge' => ['reference' => 'RH9X76WH', 'name' => 'LG Sèche linge 9 kg | Molette écran LCD RH9X76WH', 'capacity' => '9'],
    ];

    // LG France capacity tables (15.10.2026) agree with the archived descriptions/specifications.
    // The original marketing titles contain different volumes; only the title is projected here.
    private const LG_FRIDGE_TITLES = [
        'lg-gbbsj20dsw-combine' => ['reference' => 'GBBSJ20DSW', 'name' => 'LG Réfrigérateur combiné | 333L | Door Cooling+™ GBBSJ20DSW', 'from' => '333L', 'to' => '375L', 'capacity' => '375'],
        'lg-gmf960py2e-multi-portes' => ['reference' => 'GMF960PY2E', 'name' => 'LG Réfrigérateurs Multi-portes | 646 L | Linear Cooling™ GMF960PY2E', 'from' => '646 L', 'to' => '645 L', 'capacity' => '645'],
        'lg-gml960evbe-multi-portes' => ['reference' => 'GML960EVBE', 'name' => 'LG Réfrigérateur Multi-portes | 637 L | Door Cooling™ I E GML960EVBE', 'from' => '637 L', 'to' => '641 L', 'capacity' => '641'],
        'lg-gml960mbbe-multi-portes' => ['reference' => 'GML960MBBE', 'name' => 'LG Réfrigérateurs Multi-portes | I 637 L | Door Cooling™ I E GML960MBBE', 'from' => '637 L', 'to' => '641 L', 'capacity' => '641'],
        'lg-gml960pybe-multi-portes' => ['reference' => 'GML960PYBE', 'name' => 'LG Réfrigérateur Multi-portes | 637L | Uvnano™ GML960PYBE', 'from' => '637L', 'to' => '641L', 'capacity' => '641'],
        'lg-gslc40epse-americain' => ['reference' => 'GSLC40EPSE', 'name' => 'LG Réfrigérateur américain | 635L | Smart Diagnosis™ GSLC40EPSE', 'from' => '635L', 'to' => '638L', 'capacity' => '638'],
    ];

    private const LG_WASHER_NAME = 'LG Lave-linge 10.5 KG - 68L | AI Direct Drive™ | Moteur Direct Drive™ garanti 10 ans F14V13WHS';

    public static function apply(array $source, array $product, string $locale): array
    {
        $product = self::correctSupraOscarMaterialClaim($source, $product, $locale);

        if ($locale === 'fr') {
            $product = self::correctFrenchLgProducts($source, $product);
            $product = self::correctFrenchLgFridgeTitles($source, $product);
        }

        return self::correctBoschCapacity($source, $product, $locale);
    }

    /** SUPRA OSCAR technical sheet: steel structure with cast-iron inner parts. */
    private static function correctSupraOscarMaterialClaim(array $source, array $product, string $locale): array
    {
        $names = [
            'fr' => 'Poêle à bois étanche en fonte – SUPRA Oscar 4.8 kW',
            'en' => 'Room-sealed wood-burning stove in cast iron – SUPRA Oscar 4.8 kW',
            'de' => 'Raumluftunabhängiger Holzofen In Gusseisen – SUPRA Oscar 4.8 kW',
        ];
        if (($source['slug'] ?? null) !== 'poele-a-bois-etanche-en-fonte-supra-oscar-4-8-kw'
            || ($source['reference'] ?? null) !== '44294'
            || ($source['name'] ?? null) !== $names['fr']
            || ($source['has_variants'] ?? null) !== false
            || ($source['variants'] ?? []) !== []
            || ! isset($names[$locale]) || ($product['name'] ?? null) !== $names[$locale]) {
            return $product;
        }
        $product['name'] = str_replace([' en fonte', ' in cast iron', ' In Gusseisen'], '', $product['name']);
        $product['title_source'] = 'trained_algorithmic_media';

        return $product;
    }

    private static function correctFrenchLgProducts(array $source, array $product): array
    {
        $dryer = self::LG_DRYERS[$source['slug'] ?? ''] ?? null;
        if ($dryer !== null
            && ($source['reference'] ?? null) === $dryer['reference']
            && ($source['name'] ?? null) === $dryer['name']
            && ($product['name'] ?? null) === $dryer['name']) {
            $description = 'Lave-linge LG de '.$dryer['capacity'].' kg.';
            $capacities = array_filter($source['specifications'] ?? [], static fn (array $spec): bool => ($spec['label'] ?? null) === 'Capacité, Capacité de séchage maximale (kg)');
            $position = array_key_first($capacities);
            if (($source['description'] ?? null) === $description && ($product['description'] ?? null) === $description
                && count($capacities) === 1
                && ($capacities[$position]['value'] ?? null) === $dryer['capacity']
                && ($product['specifications'][$position]['value'] ?? null) === $dryer['capacity']) {
                $product['description'] = 'Sèche-linge LG de '.$dryer['capacity'].' kg.';
                $product['description_source'] = 'trained_algorithmic_media';
            }
            // The archive slug is retained to preserve existing links; the compact index has no description.
            $wrongCategory = ['slug' => 'lavage-lave-linge', 'name' => 'Lave-linge'];
            if ($dryer['reference'] === 'RH9X56WH'
                && ($source['category'] ?? null) === $wrongCategory
                && ($product['category'] ?? null) === $wrongCategory) {
                $product['category'] = ['slug' => 'lavage-seche-linge', 'name' => 'Sèche-linge'];
            }
        }

        // LG's product information sheet and EU energy label give 10.5 kg, unlike its first capacity table.
        // https://www.lg.com/fr/lavage/lave-linge/lg-f14v13whs-lave-linge-standards/
        if (($source['slug'] ?? null) !== 'lg-f14v13whs-lave-linge'
            || ($source['reference'] ?? null) !== 'F14V13WHS'
            || ($source['name'] ?? null) !== self::LG_WASHER_NAME
            || ($product['name'] ?? null) !== self::LG_WASHER_NAME
            || ($source['description'] ?? null) !== 'Lave-linge LG de 1 kg.'
            || ($product['description'] ?? null) !== 'Lave-linge LG de 1 kg.') {
            return $product;
        }
        $capacities = array_filter($source['specifications'] ?? [], static fn (array $spec): bool => ($spec['label'] ?? null) === 'Capacité, Capacité de lavage maximale (kg)');
        if (count($capacities) !== 1) {
            return $product;
        }
        $position = array_key_first($capacities);
        if (($capacities[$position]['value'] ?? null) !== '1' || ($product['specifications'][$position]['value'] ?? null) !== '1') {
            return $product;
        }
        $product['description'] = 'Lave-linge LG de 10,5 kg.';
        $product['description_source'] = 'trained_algorithmic_media';
        $product['specifications'][$position]['value'] = '10,5';

        return $product;
    }

    private static function correctFrenchLgFridgeTitles(array $source, array $product): array
    {
        $rule = self::LG_FRIDGE_TITLES[$source['slug'] ?? ''] ?? null;
        if ($rule === null
            || ($source['reference'] ?? null) !== $rule['reference']
            || ($source['name'] ?? null) !== $rule['name']
            || ($product['name'] ?? null) !== $rule['name']) {
            return $product;
        }
        // Compact index entries have no specifications. Details must still match the reviewed volume.
        if (array_key_exists('specifications', $source)) {
            $capacities = array_filter($source['specifications'], static fn (array $spec): bool => ($spec['label'] ?? null) === 'Capacité, Volume total (L)');
            if (count($capacities) !== 1) {
                return $product;
            }
            $position = array_key_first($capacities);
            if (($capacities[$position]['value'] ?? null) !== $rule['capacity']
                || ($product['specifications'][$position]['value'] ?? null) !== $rule['capacity']) {
                return $product;
            }
        }
        $product['name'] = str_replace($rule['from'], $rule['to'], $product['name']);
        $product['title_source'] = 'trained_algorithmic_media';

        return $product;
    }

    private static function correctBoschCapacity(array $source, array $product, string $locale): array
    {
        if (! isset(self::NAMES[$locale])
            || ($source['slug'] ?? null) !== 'froid-kgn36nwea-bosch-combines-congelateur-en-bas'
            || ($source['reference'] ?? null) !== 'KGN36NWEA'
            || ($source['name'] ?? null) !== self::SOURCE_NAME
            || ($product['name'] ?? null) !== (self::NAMES[$locale] ?? null)) {
            return $product;
        }

        $totalPosition = null;
        if (array_key_exists('specifications', $source)) {
            $totals = array_filter($source['specifications'], static fn (array $spec): bool => ($spec['label'] ?? null) === 'Volume utile total (en litres)');
            if (count($totals) !== 1) {
                return $product;
            }
            $position = array_key_first($totals);
            if ($totals[$position]['value'] !== '302' || ($product['specifications'][$position]['value'] ?? null) !== '302') {
                return $product;
            }
            $totalPosition = $position;
        }

        $product['name'] = str_replace('302L', '305L', $product['name']);
        if ($totalPosition !== null) {
            $product['specifications'][$totalPosition]['value'] = '305';
        }

        return $product;
    }
}
