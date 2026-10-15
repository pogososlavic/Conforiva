<?php

namespace Tests\Unit;

use App\Services\CatalogueProductCorrections;
use PHPUnit\Framework\TestCase;

class CatalogueProductCorrectionsTest extends TestCase
{
    private function source(): array
    {
        return [
            'slug' => 'froid-kgn36nwea-bosch-combines-congelateur-en-bas',
            'reference' => 'KGN36NWEA',
            'name' => 'Réfrigérateur congélateur bas BOSCH 302L No Frost Blanc KGN36NWEA',
            'specifications' => [
                ['label' => 'Volume utile total (en litres)', 'value' => '302'],
                ['label' => 'Capacité congélateur (en L)', 'value' => '89'],
                ['label' => 'Autre valeur', 'value' => '302'],
            ],
        ];
    }

    public function test_verified_capacity_is_projected_in_all_languages_without_changing_other_fields_or_source(): void
    {
        $source = $this->source();
        foreach ([
            'fr' => $source['name'],
            'en' => 'Fridge-freezer with bottom freezer BOSCH 302L No Frost White KGN36NWEA',
            'de' => 'Kühl-Gefrierkombination mit Gefrierteil unten BOSCH 302L No Frost Weiß KGN36NWEA',
        ] as $locale => $name) {
            $product = ['name' => $name, 'price_cents' => 44730, 'images' => ['unchanged'], 'description_hidden' => true,
                'specifications' => [['label' => 'Localized total', 'value' => '302'], ['label' => 'Localized freezer', 'value' => '89'], ['label' => 'Other value', 'value' => '302']]];
            $expected = $product;
            $expected['name'] = str_replace('302L', '305L', $name);
            $expected['specifications'][0]['value'] = '305';
            $this->assertSame($expected, CatalogueProductCorrections::apply($source, $product, $locale));
            $this->assertSame($this->source(), $source);
            $index = $source;
            unset($index['specifications']);
            $this->assertSame(['name' => $expected['name']], CatalogueProductCorrections::apply($index, ['name' => $name], $locale));
        }
    }

    public function test_other_models_changed_sources_or_changed_translations_are_not_overridden(): void
    {
        $source = $this->source();
        $product = ['name' => $source['name'], 'specifications' => $source['specifications']];
        foreach (['slug', 'reference', 'name'] as $field) {
            $changed = $source;
            $changed[$field] .= '-changed';
            $this->assertSame($product, CatalogueProductCorrections::apply($changed, $product, 'fr'));
        }
        $changed = $product;
        $changed['name'] .= ' changed';
        $this->assertSame($changed, CatalogueProductCorrections::apply($source, $changed, 'fr'));
        $this->assertSame($product, CatalogueProductCorrections::apply($source, $product, 'es'));
        $source['specifications'][0]['value'] = '306';
        $this->assertSame($product, CatalogueProductCorrections::apply($source, $product, 'fr'));
        $source['specifications'] = [];
        $this->assertSame($product, CatalogueProductCorrections::apply($source, $product, 'fr'));
    }

    private function dryerSources(): array
    {
        $models = [
            ['RH0X71WH', 'LG Sèche linge 10 kg | Molette écran LCD RH0X71WH', '10'],
            ['RH0X76WH', 'LG Sèche linge 10 kg | Molette écran LCD RH0X76WH', '10'],
            ['RH8N14SL', 'LG Sèche linge | 8 kg | Pompe à chaleur RH8N14SL', '8'],
            ['RH8N15WH', 'LG Sèche linge | 8 kg | Pompe à chaleur RH8N15WH', '8'],
            ['RH8N15WHB', 'LG Sèche linge | 8 kg | Pompe à chaleur RH8N15WHB', '8'],
            ['RH8P12BS', 'LG Sèche-linge | 18 kg | Dual Dry™ RH8P12BS', '18'],
            ['RH8P12WH', 'LG Sèche-linge | 18kg | Compresseur DUAL Inverter™ RH8P12WH', '18'],
            ['RH9B15WH', 'LG Sèche linge | 9 kg | Pompe à chaleur RH9B15WH', '9'],
            ['RH9V50WH', 'LG Sèche-linge 9 kg | Pompe à chaleur | Condenseur autonettoyant | Moteur et compresseur DUAL Inverter garantis 10 ans RH9V50WH', '9'],
            ['RH9V51WH', 'LG Sèche-linge 9kg | A++ | Condenseur Autonettoyant | Compresseur DUAL Inverter et moteur Inverter garanti 10 ans RH9V51WH', '9'],
            ['RH9X56WH', 'LG Sèche linge 9 kg | Pompe à chaleur DUAL Inverter™ RH9X56WH', '9'],
            ['RH9X58WH', 'LG Sèche linge 9 kg | Pompe à chaleur DUAL Inverter™ RH9X58WH', '9'],
            ['RH9X71WH', 'LG Sèche linge 9 kg | Molette écran LCD.. RH9X71WH', '9'],
            ['RH9X76BM', 'LG Sèche linge 9 kg | Molette écran LCD RH9X76BM', '9'],
            ['RH9X76WH', 'LG Sèche linge 9 kg | Molette écran LCD RH9X76WH', '9'],
        ];

        return array_map(static fn (array $model): array => [
            'slug' => 'lg-'.strtolower($model[0]).($model[0] === 'RH9X56WH' ? '-lave-linge' : '-seche-linge'),
            'reference' => $model[0],
            'name' => $model[1],
            'description' => 'Lave-linge LG de '.$model[2].' kg.',
            'category' => $model[0] === 'RH9X56WH' ? ['slug' => 'lavage-lave-linge', 'name' => 'Lave-linge'] : ['slug' => 'lavage-seche-linge', 'name' => 'Sèche-linge'],
            'specifications' => [['label' => 'Capacité, Capacité de séchage maximale (kg)', 'value' => $model[2]]],
            'price_cents' => 65123,
            'images' => ['same-image'],
            'stock' => 12,
        ], $models);
    }

    public function test_verified_dryer_type_is_projected_for_the_fifteen_exact_french_sources(): void
    {
        foreach ($this->dryerSources() as $source) {
            $original = $source;
            $expected = $source;
            $expected['description'] = str_replace('Lave-linge', 'Sèche-linge', $source['description']);
            $expected['description_source'] = 'trained_algorithmic_media';
            if ($source['reference'] === 'RH9X56WH') {
                $expected['category'] = ['slug' => 'lavage-seche-linge', 'name' => 'Sèche-linge'];
            }
            $corrected = CatalogueProductCorrections::apply($source, $source, 'fr');
            $this->assertSame($expected, $corrected, $source['reference']);
            $this->assertSame($original, $source);
            $this->assertSame($corrected, CatalogueProductCorrections::apply($source, $corrected, 'fr'));
            foreach (['en', 'de'] as $locale) {
                $this->assertSame($source, CatalogueProductCorrections::apply($source, $source, $locale));
            }
        }
    }

    public function test_dryer_source_and_presented_text_guards_preserve_changes_and_other_models(): void
    {
        $source = $this->dryerSources()[0];
        foreach (['slug', 'reference', 'name', 'description'] as $field) {
            $changed = $source;
            $changed[$field] .= ' changed';
            $this->assertSame($changed, CatalogueProductCorrections::apply($changed, $changed, 'fr'));
        }
        $changed = $source;
        $changed['description'] = 'Description révisée par le marchand.';
        $this->assertSame($changed, CatalogueProductCorrections::apply($source, $changed, 'fr'));
        $changed = $source;
        $changed['specifications'][0]['value'] = '11';
        $this->assertSame($changed, CatalogueProductCorrections::apply($changed, $changed, 'fr'));
        $changed = $source;
        $changed['specifications'][] = $source['specifications'][0];
        $this->assertSame($changed, CatalogueProductCorrections::apply($changed, $changed, 'fr'));
    }

    public function test_verified_dryer_category_is_projected_on_compact_index_without_rewriting_its_url(): void
    {
        $source = $this->dryerSources()[10];
        unset($source['description'], $source['specifications']);
        $expected = $source;
        $expected['category'] = ['slug' => 'lavage-seche-linge', 'name' => 'Sèche-linge'];
        $this->assertSame($expected, CatalogueProductCorrections::apply($source, $source, 'fr'));
        $changed = $source;
        $changed['category'] = ['slug' => 'merchant-category', 'name' => 'Choix du marchand'];
        $this->assertSame($changed, CatalogueProductCorrections::apply($source, $changed, 'fr'));
    }

    public function test_verified_f14v13whs_wash_capacity_and_description_are_projected_without_changing_source(): void
    {
        $source = [
            'slug' => 'lg-f14v13whs-lave-linge', 'reference' => 'F14V13WHS',
            'name' => 'LG Lave-linge 10.5 KG - 68L | AI Direct Drive™ | Moteur Direct Drive™ garanti 10 ans F14V13WHS',
            'description' => 'Lave-linge LG de 1 kg.', 'price_cents' => 65123, 'images' => ['same-image'], 'stock' => 12,
            'specifications' => [['label' => 'Capacité, Capacité de lavage maximale (kg)', 'value' => '1'], ['label' => 'Other one', 'value' => '1']],
        ];
        $expected = $source;
        $expected['description'] = 'Lave-linge LG de 10,5 kg.';
        $expected['description_source'] = 'trained_algorithmic_media';
        $expected['specifications'][0]['value'] = '10,5';
        $original = $source;
        $this->assertSame($expected, CatalogueProductCorrections::apply($source, $source, 'fr'));
        $this->assertSame($original, $source);
        $this->assertSame($expected, CatalogueProductCorrections::apply($source, $expected, 'fr'));
        foreach (['en', 'de'] as $locale) {
            $this->assertSame($source, CatalogueProductCorrections::apply($source, $source, $locale));
        }
        foreach (['slug', 'reference', 'name', 'description'] as $field) {
            $changed = $source;
            $changed[$field] .= ' changed';
            $this->assertSame($changed, CatalogueProductCorrections::apply($changed, $changed, 'fr'));
        }
        $changed = $source;
        $changed['specifications'][0]['value'] = '11';
        $this->assertSame($changed, CatalogueProductCorrections::apply($changed, $changed, 'fr'));
        $changed = $source;
        $changed['specifications'][] = $source['specifications'][0];
        $this->assertSame($changed, CatalogueProductCorrections::apply($changed, $changed, 'fr'));
        $index = $source;
        unset($index['description'], $index['specifications']);
        $this->assertSame($index, CatalogueProductCorrections::apply($index, $index, 'fr'));
    }

    private function fridgeSources(): array
    {
        return [
            ['slug' => 'lg-gbbsj20dsw-combine', 'reference' => 'GBBSJ20DSW', 'name' => 'LG Réfrigérateur combiné | 333L | Door Cooling+™ GBBSJ20DSW', 'from' => '333L', 'to' => '375L', 'capacity' => '375'],
            ['slug' => 'lg-gmf960py2e-multi-portes', 'reference' => 'GMF960PY2E', 'name' => 'LG Réfrigérateurs Multi-portes | 646 L | Linear Cooling™ GMF960PY2E', 'from' => '646 L', 'to' => '645 L', 'capacity' => '645'],
            ['slug' => 'lg-gml960evbe-multi-portes', 'reference' => 'GML960EVBE', 'name' => 'LG Réfrigérateur Multi-portes | 637 L | Door Cooling™ I E GML960EVBE', 'from' => '637 L', 'to' => '641 L', 'capacity' => '641'],
            ['slug' => 'lg-gml960mbbe-multi-portes', 'reference' => 'GML960MBBE', 'name' => 'LG Réfrigérateurs Multi-portes | I 637 L | Door Cooling™ I E GML960MBBE', 'from' => '637 L', 'to' => '641 L', 'capacity' => '641'],
            ['slug' => 'lg-gml960pybe-multi-portes', 'reference' => 'GML960PYBE', 'name' => 'LG Réfrigérateur Multi-portes | 637L | Uvnano™ GML960PYBE', 'from' => '637L', 'to' => '641L', 'capacity' => '641'],
            ['slug' => 'lg-gslc40epse-americain', 'reference' => 'GSLC40EPSE', 'name' => 'LG Réfrigérateur américain | 635L | Smart Diagnosis™ GSLC40EPSE', 'from' => '635L', 'to' => '638L', 'capacity' => '638'],
        ];
    }

    public function test_verified_fridge_volumes_only_change_the_six_french_titles_and_their_provenance(): void
    {
        foreach ($this->fridgeSources() as $rule) {
            $source = array_intersect_key($rule, array_flip(['slug', 'reference', 'name'])) + [
                'description' => 'Réfrigérateur LG de '.$rule['capacity'].' litres.',
                'specifications' => [['label' => 'Capacité, Volume total (L)', 'value' => $rule['capacity']], ['label' => 'EAN', 'value' => '8806091802040']],
                'price_cents' => 65123, 'stock' => 12, 'images' => ['same-image'],
            ];
            $original = $source;
            $expected = $source;
            $expected['name'] = str_replace($rule['from'], $rule['to'], $source['name']);
            $expected['title_source'] = 'trained_algorithmic_media';
            $corrected = CatalogueProductCorrections::apply($source, $source, 'fr');
            $this->assertSame($expected, $corrected, $rule['reference']);
            $this->assertSame($original, $source);
            $this->assertSame($corrected, CatalogueProductCorrections::apply($source, $corrected, 'fr'));
            foreach (['en', 'de'] as $locale) {
                $this->assertSame($source, CatalogueProductCorrections::apply($source, $source, $locale));
            }
            unset($source['description'], $source['specifications'], $expected['description'], $expected['specifications']);
            $this->assertSame($expected, CatalogueProductCorrections::apply($source, $source, 'fr'));
        }
    }

    public function test_fridge_titles_keep_changed_sources_presented_values_and_incomplete_details(): void
    {
        foreach ($this->fridgeSources() as $rule) {
            $source = array_intersect_key($rule, array_flip(['slug', 'reference', 'name'])) + [
                'specifications' => [['label' => 'Capacité, Volume total (L)', 'value' => $rule['capacity']]],
            ];
            foreach (['slug', 'reference', 'name'] as $field) {
                $changed = $source;
                $changed[$field] .= ' changed';
                $this->assertSame($changed, CatalogueProductCorrections::apply($changed, $changed, 'fr'));
            }
            $changed = $source;
            $changed['name'] = 'Titre révisé par le marchand';
            $this->assertSame($changed, CatalogueProductCorrections::apply($source, $changed, 'fr'));
            $changed = $source;
            $changed['specifications'][0]['value'] = '600';
            $this->assertSame($changed, CatalogueProductCorrections::apply($changed, $changed, 'fr'));
            $this->assertSame($changed, CatalogueProductCorrections::apply($source, $changed, 'fr'));
            $changed = $source;
            $changed['specifications'] = [];
            $this->assertSame($changed, CatalogueProductCorrections::apply($changed, $changed, 'fr'));
            $changed = $source;
            $changed['specifications'][] = $source['specifications'][0];
            $this->assertSame($changed, CatalogueProductCorrections::apply($changed, $changed, 'fr'));
        }
    }
}
