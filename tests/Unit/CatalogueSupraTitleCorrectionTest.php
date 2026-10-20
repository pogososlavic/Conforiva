<?php

namespace Tests\Unit;

use App\Services\CatalogueContent;
use App\Services\CatalogueProductCorrections;
use PHPUnit\Framework\TestCase;

class CatalogueSupraTitleCorrectionTest extends TestCase
{
    private function source(): array
    {
        return [
            'slug' => 'poele-a-bois-etanche-en-fonte-supra-oscar-4-8-kw', 'reference' => '44294',
            'name' => 'Poêle à bois étanche en fonte – SUPRA Oscar 4.8 kW',
            'description' => '', 'specifications' => [], 'universe' => 'heating',
            'has_variants' => false, 'variants' => [], 'issues' => ['missing_specifications'],
            'price_cents' => 57449, 'currency' => 'EUR', 'brand' => 'SUPRA',
        ];
    }

    public function test_exact_model_title_and_empty_description_fallback_remove_the_material_claim_in_all_languages(): void
    {
        $source = $this->source();
        foreach ([
            'fr' => ['Poêle à bois étanche en fonte – SUPRA Oscar 4.8 kW', 'Poêle à bois étanche – SUPRA Oscar 4.8 kW'],
            'en' => ['Room-sealed wood-burning stove in cast iron – SUPRA Oscar 4.8 kW', 'Room-sealed wood-burning stove – SUPRA Oscar 4.8 kW'],
            'de' => ['Raumluftunabhängiger Holzofen In Gusseisen – SUPRA Oscar 4.8 kW', 'Raumluftunabhängiger Holzofen – SUPRA Oscar 4.8 kW'],
        ] as $locale => [$name, $expected]) {
            $original = $source;
            $product = $source;
            $product['name'] = $name;
            $product = CatalogueProductCorrections::apply($source, $product, $locale);
            $this->assertSame($expected, $product['name']);
            $this->assertSame('trained_algorithmic_media', $product['title_source']);
            $projected = CatalogueContent::apply($source, $product, $locale);
            $this->assertStringStartsWith($expected.'. ', $projected['description']);
            $this->assertStringContainsString('44294', $projected['description']);
            $this->assertCount(4, $projected['specifications']);
            $this->assertNotContains('missing_specifications', $projected['issues']);
            $this->assertSame($source['price_cents'], $projected['price_cents']);
            $this->assertSame($original, $source);
            unset($source['description'], $source['specifications']);
            $this->assertSame($expected, CatalogueProductCorrections::apply($source, ['name' => $name], $locale)['name']);
            $source = $original;
        }
    }

    public function test_other_models_references_edited_titles_and_variants_are_not_overridden(): void
    {
        $source = $this->source();
        foreach ([['slug' => 'other-model'], ['reference' => '44295'], ['name' => 'Reviewed new source title'],
            ['has_variants' => true], ['variants' => [['title' => 'OSCAR variant']]]] as $change) {
            $changed = array_replace($source, $change);
            $this->assertSame($changed, CatalogueProductCorrections::apply($changed, $changed, 'fr'));
        }
        $product = array_replace($source, ['name' => 'Titre édité par le marchand']);
        $this->assertSame($product, CatalogueProductCorrections::apply($source, $product, 'fr'));
        $this->assertSame($source, CatalogueProductCorrections::apply($source, $source, 'es'));
    }
}
