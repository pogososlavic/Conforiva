<?php

namespace Tests\Feature;

use App\Services\CategoryContent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CategoryContentTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        DB::shouldReceive('connection')->never();
        DB::shouldReceive('connectUsing')->never();
        Http::preventStrayRequests();
    }

    private function products()
    {
        return collect([
            ['slug' => 'machine-a', 'universe' => 'appliances', 'category' => ['slug' => 'lavage-lave-linge', 'name' => 'Lave-linge']],
            ['slug' => 'machine-b', 'universe' => 'appliances', 'category' => ['slug' => 'lavage-lave-linge-sechant', 'name' => 'Lave-linge séchants']],
            ['slug' => 'dryer', 'universe' => 'appliances', 'category' => ['slug' => 'lavage-seche-linge', 'name' => 'Sèche-linge']],
            ['slug' => 'fridge', 'universe' => 'appliances', 'category' => ['slug' => 'froid-bosch-une-porte', 'name' => 'Bosch · Une porte']],
            ['slug' => 'stove', 'universe' => 'heating', 'category' => ['slug' => 'chauffage-poeles-a-bois', 'name' => 'Poêles à bois']],
        ]);
    }

    public function test_category_copy_is_specific_and_related_links_keep_only_the_category_context(): void
    {
        $service = app(CategoryContent::class);
        $intro = ['title' => 'Catalogue', 'description' => '', 'universe' => null];
        $washing = $service->enrich($intro, ['category' => 'lavage-lave-linge'], $this->products(), 'fr');
        $drying = $service->enrich($intro, ['category' => 'lavage-seche-linge'], $this->products(), 'fr');
        $this->assertNotSame($washing['description'], $drying['description']);
        $this->assertStringContainsString('programmes', $washing['description']);
        $this->assertStringContainsString('technologie', $drying['description']);
        $this->assertCount(3, $washing['guide_points']);
        $this->assertCount(3, $washing['related_categories']);
        $this->assertSame(['lavage-lave-linge-sechant', 'lavage-seche-linge'], array_column(array_slice($washing['related_categories'], 0, 2), 'slug'));
        foreach ($washing['related_categories'] as $category) {
            $this->assertNotSame('lavage-lave-linge', $category['slug']);
            $this->assertStringContainsString('/fr/catalogue?category=', $category['url']);
            $this->assertStringNotContainsString('universe=', $category['url']);
            $this->assertStringNotContainsString('chauffage-', $category['url']);
        }
    }

    public function test_advice_is_not_repeated_on_paginated_or_faceted_results(): void
    {
        $service = app(CategoryContent::class);
        foreach ([['page' => 2], ['q' => 'test'], ['brand' => 'Bosch'], ['min' => '10']] as $filters) {
            $result = $service->enrich(['universe' => null], $filters + ['category' => 'lavage-lave-linge'], $this->products(), 'fr');
            $this->assertArrayNotHasKey('guide_points', $result);
            $this->assertArrayHasKey('description', $result);
        }
    }

    public function test_editorial_profiles_are_complete_in_all_supported_languages(): void
    {
        foreach (['fr', 'en', 'de'] as $locale) {
            $profiles = __('category-content.profiles', [], $locale);
            $this->assertCount(20, $profiles);
            foreach ($profiles as $profile) {
                $this->assertCount(5, $profile);
                foreach ($profile as $text) {
                    $this->assertNotEmpty(trim($text));
                }
            }
            $result = app(CategoryContent::class)->enrich(['universe' => 'appliances'], ['category' => 'froid-bosch-une-porte'], $this->products(), $locale);
            $this->assertStringContainsString('Bosch', $result['title']);
            $this->assertStringContainsString(match ($locale) {
                'fr' => 'Appareils de froid', 'en' => 'Cooling appliances', 'de' => 'Kühlgeräte'
            }, $result['title']);
        }
    }

    public function test_specialist_categories_do_not_receive_unrelated_furniture_or_heater_advice(): void
    {
        $cases = [
            ['chauffage-pieces-detachees', 'heating', 'référence complète'],
            ['chauffage-poeles-a-granules-hydro', 'heating', 'compatibilité hydraulique'],
            ['mobilier-fours-a-pizza-barbecues', 'furniture', 'combustible'],
            ['mobilier-cheminees-au-bioethanol', 'furniture', 'ventilation'],
            ['mobilier-tables-extensibles', 'furniture', 'deux configurations'],
        ];
        foreach ($cases as [$slug, $universe, $expected]) {
            $products = collect([['slug' => 'example', 'category' => ['slug' => $slug, 'name' => 'Example'], 'universe' => $universe]]);
            $result = app(CategoryContent::class)->enrich(['universe' => null], ['category' => $slug], $products, 'fr');
            $this->assertStringContainsString($expected, implode(' ', $result['guide_points']));
        }
    }

    public function test_empty_selection_does_not_add_editorial_content(): void
    {
        $intro = ['title' => 'Catalogue', 'description' => 'Initial'];
        $this->assertSame($intro, app(CategoryContent::class)->enrich($intro, ['category' => 'missing'], $this->products(), 'fr'));
    }
}
