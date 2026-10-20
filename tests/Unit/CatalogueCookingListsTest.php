<?php

namespace Tests\Unit;

use App\Services\CatalogueContent;
use PHPUnit\Framework\TestCase;

class CatalogueCookingListsTest extends TestCase
{
    private function source(string $variant = 'plus'): array
    {
        return [
            'slug' => $variant === 'plus' ? 'chauffage-olympia-conf-poeles-a-bois' : 'chauffage-olympia-pierreollaire-poeles-a-bois',
            'reference' => $variant === 'plus' ? 'olympia_conf' : 'Olympia-PierreOllaire',
            'name' => $variant === 'plus' ? 'Poêle à bois avec four et plaque de cuisson Olympia Plus · 13 Kw' : 'Poêle à bois avec four et plaque de cuisson Olympia Pierre Ollaire · 12,2 Kw',
            'universe' => 'heating', 'has_variants' => false, 'variants' => [],
            'issues' => ['conflicting_specification'], 'description' => 'Existing source description.',
            'specifications' => [
                ['label' => 'Option de cuisson', 'value' => 'Avec four'],
                ['label' => 'Option de cuisson', 'value' => 'Avec plaque de cuisson'],
            ],
        ];
    }

    private function project(array $source, string $locale = 'fr'): array
    {
        $product = $source;
        if ($locale !== 'fr') {
            $product['specifications'] = $locale === 'en'
                ? [['label' => 'Cooking option', 'value' => 'With oven'], ['label' => 'Cooking option', 'value' => 'With cooking plate']]
                : [['label' => 'Kochoption', 'value' => 'Mit Backofen'], ['label' => 'Kochoption', 'value' => 'Mit Kochplatte']];
        }

        return CatalogueContent::apply($source, $product, $locale);
    }

    public function test_both_exact_sources_keep_both_existing_facilities_in_each_language_without_mutating_the_archive(): void
    {
        foreach (['plus', 'stone'] as $model) {
            $source = $this->source($model);
            $original = $source;
            foreach (['fr' => ['Option de cuisson', 'Avec four / Avec plaque de cuisson'],
                'en' => ['Cooking option', 'With oven / With cooking plate'],
                'de' => ['Kochoption', 'Mit Backofen / Mit Kochplatte']] as $locale => [$label, $value]) {
                $product = $this->project($source, $locale);
                $this->assertSame([['label' => $label, 'value' => $value]], $product['specifications']);
                $this->assertSame([], $product['specification_reviews']);
                $this->assertNotContains('conflicting_specification', $product['issues']);
                $this->assertSame($original, $source);
            }
        }
    }

    public function test_other_models_changed_titles_references_variants_and_unknown_choices_stay_unresolved(): void
    {
        $source = $this->source();
        $changes = [
            ['slug' => 'another-olympia'], ['reference' => 'other-reference'], ['name' => $source['name'].' updated'],
            ['universe' => 'appliances'], ['has_variants' => true], ['variants' => [['title' => 'Other choice']]],
            ['specifications' => [['label' => 'Option de cuisson', 'value' => 'Avec four'], ['label' => 'Option de cuisson', 'value' => 'Sans four']]],
            ['specifications' => [['label' => 'Poids', 'value' => '56 kg'], ['label' => 'Poids', 'value' => '58 kg']]],
            ['specifications' => [['label' => "Zone d'installation", 'value' => 'Zone 1'], ['label' => "Zone d'installation", 'value' => 'Zone 2']]],
        ];
        foreach ($changes as $change) {
            $product = $this->project(array_replace($source, $change));
            $this->assertContains('conflicting_specification', $product['issues']);
            $this->assertSame('En cours de vérification', $product['specifications'][0]['value']);
            $this->assertCount(1, $product['specification_reviews']);
        }
    }

    public function test_resolving_cooking_does_not_clear_a_different_conflict_or_missing_details(): void
    {
        $source = $this->source();
        $source['issues'][] = 'missing_specifications';
        $source['specifications'][] = ['label' => 'Poids', 'value' => '56 kg'];
        $source['specifications'][] = ['label' => 'Poids', 'value' => '58 kg'];
        $product = $this->project($source);
        $this->assertSame('Avec four / Avec plaque de cuisson', $product['specifications'][0]['value']);
        $this->assertSame('En cours de vérification', $product['specifications'][1]['value']);
        $this->assertSame([['label' => 'Poids', 'values' => ['56 kg', '58 kg']]], $product['specification_reviews']);
        $this->assertContains('conflicting_specification', $product['issues']);
        $this->assertContains('missing_specifications', $product['issues']);
    }
}
