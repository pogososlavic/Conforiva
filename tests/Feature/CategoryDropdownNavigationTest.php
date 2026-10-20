<?php

namespace Tests\Feature;

use App\Services\Catalogue;
use App\Services\CategoryNavigation;
use Tests\TestCase;

class CategoryDropdownNavigationTest extends TestCase
{
    public function test_supplied_projection_builds_navigation_without_reloading_the_catalogue(): void
    {
        $this->mock(Catalogue::class)->shouldNotReceive('products');
        $products = collect([
            ['universe' => 'appliances', 'brand' => 'LG', 'category' => ['slug' => 'washers', 'name' => 'Lave-linge']],
        ]);
        $navigation = app(CategoryNavigation::class);
        $this->assertSame(['LG'], $navigation->applianceBrands($products));
        $this->assertSame(['washers'], array_column($navigation->groups($products)['appliances'], 'slug'));
        $this->assertSame([], $navigation->groups(collect()));
        $this->assertSame([], $navigation->applianceBrands(collect()));
    }

    public function test_groups_are_deduplicated_sorted_and_separated_by_actual_universe(): void
    {
        $catalogue = $this->mock(Catalogue::class);
        $catalogue->shouldReceive('products')->once()->andReturn(collect([
            ['universe' => 'appliances', 'category' => ['slug' => 'washers', 'name' => 'Lave-linge']],
            ['universe' => 'appliances', 'category' => ['slug' => 'freezers', 'name' => 'Congélateurs']],
            ['universe' => 'appliances', 'category' => ['slug' => 'washers', 'name' => 'Lave-linge']],
            ['universe' => 'heating', 'category' => ['slug' => 'stoves', 'name' => 'Poêles']],
        ]));

        $groups = app(CategoryNavigation::class)->groups();

        $this->assertSame(['freezers', 'washers'], array_column($groups['appliances'], 'slug'));
        $this->assertSame(['stoves'], array_column($groups['heating'], 'slug'));
    }

    public function test_appliance_brands_include_all_published_brands_and_exclude_other_universes(): void
    {
        $this->mock(Catalogue::class)->shouldReceive('products')->once()->andReturn(collect([
            ['universe' => 'appliances', 'brand' => 'Samsung'],
            ['universe' => 'appliances', 'brand' => 'LG'],
            ['universe' => 'appliances', 'brand' => 'LG'],
            ['universe' => 'appliances', 'brand' => 'Bosch'],
            ['universe' => 'appliances', 'brand' => ''],
            ['universe' => 'heating', 'brand' => 'Other'],
        ]));
        $this->assertSame(['Bosch', 'LG', 'Samsung'], app(CategoryNavigation::class)->applianceBrands());
    }

    public function test_header_has_native_disclosures_and_filtered_parent_and_subcategory_links(): void
    {
        $navigation = $this->mock(CategoryNavigation::class);
        $navigation->shouldReceive('applianceBrands')->andReturn(['LG', 'Samsung']);
        $navigation->shouldReceive('groups')->andReturn([
            'appliances' => [['slug' => 'washers', 'name' => 'Lave-linge']],
        ]);

        $response = $this->get('/fr')->assertOk();
        $response->assertSee(route('catalogue', ['locale' => 'fr', 'universe' => 'appliances', 'brand' => 'LG']));
        $response->assertSee('name="storefront-categories"', false);
        $response->assertSee('class="category-dropdown-panel"', false);
        $response->assertSee(route('catalogue', ['locale' => 'fr', 'universe' => 'appliances', 'category' => 'washers']));
        $response->assertSee(route('catalogue', ['locale' => 'fr', 'universe' => 'appliances']));
    }
}
