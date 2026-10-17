<?php

namespace Tests\Unit;

use App\Services\CatalogueSafetyCorrections;
use PHPUnit\Framework\TestCase;

class CatalogueSafetyCorrectionsTest extends TestCase
{
    public function test_manufacturer_verified_water_capacity_replaces_the_contradiction_without_mutating_archive(): void
    {
        $source = ['slug' => 'chauffage-fantea32en-chaudieres', 'reference' => 'FANTEA32EN',
            'name' => 'Chaudière à Granulés Fantea',
            'description' => 'Poêle à bois ETNA de 32 kW, jusqu\'à 620 m³ de volume chauffé, rendement 90,65 %.',
            'specifications' => [['label' => 'Réservoir eau (L)', 'value' => 'Non'], ['label' => 'Capacité réservoir eau (L)', 'value' => '60']]];
        foreach (['fr' => ['Réservoir eau (L)', 'Capacité réservoir eau (L)'], 'en' => ['Water tank (L)', 'Water tank capacity (L)'], 'de' => ['Wassertank (L)', 'Wassertankkapazität (L)']] as $locale => $labels) {
            $product = ['description' => 'old prose', 'price_cents' => 183698, 'specifications' => [
                ['label' => $labels[0], 'value' => 'Non'], ['label' => $labels[1], 'value' => '60'], ['label' => 'Reference', 'value' => 'FANTEA32EN']]];
            $copy = $source;
            $result = CatalogueSafetyCorrections::apply($source, $product, $locale);
            $this->assertSame([['label' => $labels[1], 'value' => '60'], ['label' => 'Reference', 'value' => 'FANTEA32EN']], $result['specifications']);
            $this->assertArrayNotHasKey('technical_review_required', $result);
            $this->assertStringContainsString('FANTEA32EN', $result['description']);
            $this->assertSame(183698, $result['price_cents']);
            $this->assertSame($copy, $source);
            $this->assertSame($product, CatalogueSafetyCorrections::apply(array_replace($source, ['reference' => 'OTHER']), $product, $locale));
        }
    }
}
