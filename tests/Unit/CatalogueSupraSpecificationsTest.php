<?php

namespace Tests\Unit;

use App\Services\CatalogueContent;
use PHPUnit\Framework\TestCase;

class CatalogueSupraSpecificationsTest extends TestCase
{
    private function source(): array
    {
        return [
            'slug' => 'poele-a-bois-etanche-en-fonte-supra-oscar-4-8-kw', 'reference' => '44294',
            'name' => 'Poêle à bois étanche en fonte – SUPRA Oscar 4.8 kW',
            'has_variants' => false, 'variants' => [], 'description' => 'Existing description.',
            'specifications' => [], 'issues' => ['missing_specifications', 'unrelated_review'],
        ];
    }

    public function test_manufacturer_net_and_gross_weights_dimensions_and_material_are_distinct_and_localized(): void
    {
        $source = $this->source();
        $original = $source;
        foreach (['fr' => ['Matériau principal', 'Acier', 'Poids net', 'Poids brut', '44,7 × 63,6 × 36,9 cm'],
            'en' => ['Main material', 'Steel', 'Net weight', 'Gross weight', '44.7 × 63.6 × 36.9 cm'],
            'de' => ['Hauptmaterial', 'Stahl', 'Nettogewicht', 'Bruttogewicht', '44,7 × 63,6 × 36,9 cm']] as $locale => $expected) {
            $product = CatalogueContent::apply($source, $source, $locale);
            $this->assertCount(4, $product['specifications']);
            $this->assertSame(['label' => $expected[0], 'value' => $expected[1]], $product['specifications'][0]);
            $this->assertSame(['label' => $expected[2], 'value' => '75 kg'], $product['specifications'][1]);
            $this->assertSame(['label' => $expected[3], 'value' => '85 kg'], $product['specifications'][2]);
            $this->assertSame($expected[4], $product['specifications'][3]['value']);
            $this->assertNotContains('missing_specifications', $product['issues']);
            $this->assertContains('unrelated_review', $product['issues']);
            $this->assertSame($source['description'], $product['description']);
            $this->assertSame($original, $source);
        }
    }

    public function test_other_models_changed_sources_variants_and_existing_specifications_are_preserved(): void
    {
        $source = $this->source();
        foreach ([['slug' => 'other-model'], ['reference' => '44295'], ['name' => 'Edited source title'],
            ['has_variants' => true], ['variants' => [['title' => 'Other model']]]] as $change) {
            $changed = array_replace($source, $change);
            $product = CatalogueContent::apply($changed, $changed, 'fr');
            $this->assertSame([], $product['specifications']);
            $this->assertContains('missing_specifications', $product['issues']);
        }
        $source['specifications'] = [['label' => 'Reviewed value', 'value' => 'Do not overwrite']];
        $product = CatalogueContent::apply($source, $source, 'fr');
        $this->assertSame($source['specifications'], $product['specifications']);
        $this->assertContains('missing_specifications', $product['issues']);
        $source = $this->source();
        $this->assertSame([], CatalogueContent::apply($source, $source, 'es')['specifications']);
        $compact = $source;
        unset($compact['description'], $compact['specifications']);
        $this->assertArrayNotHasKey('specifications', CatalogueContent::apply($compact, $compact, 'fr'));
    }
}
