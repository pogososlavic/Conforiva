<?php

namespace Tests\Unit;

use App\Services\CatalogueNavigation;
use PHPUnit\Framework\TestCase;

class CatalogueNavigationTest extends TestCase
{
    public function test_related_links_reach_every_sibling_without_self_links_or_other_categories(): void
    {
        $products = collect(range(1, 20))->map(fn ($number) => [
            'slug' => sprintf('product-%02d', $number),
            'category' => ['slug' => 'heating'],
        ]);
        $products->push(['slug' => 'other', 'category' => ['slug' => 'furniture']]);
        $navigation = new CatalogueNavigation;
        $incoming = [];
        foreach ($products->where('category.slug', 'heating') as $product) {
            $links = $navigation->related($products, $product);
            $this->assertCount(4, $links);
            $this->assertNotContains($product['slug'], $links->pluck('slug'));
            $this->assertNotContains('other', $links->pluck('slug'));
            $incoming = array_merge($incoming, $links->pluck('slug')->all());
        }
        $this->assertCount(20, array_unique($incoming));
        $this->assertSame(['product-01', 'product-02', 'product-03', 'product-04'],
            $navigation->related($products, $products[19])->pluck('slug')->all());
    }

    public function test_small_categories_have_no_duplicates_and_isolated_products_have_no_links(): void
    {
        $product = ['slug' => 'one', 'category' => ['slug' => 'small']];
        $other = ['slug' => 'two', 'category' => ['slug' => 'small']];
        $navigation = new CatalogueNavigation;
        $this->assertSame(['two'], $navigation->related(collect([$product, $other, $other]), $product)->pluck('slug')->all());
        $this->assertTrue($navigation->related(collect([$product]), $product)->isEmpty());
        $this->assertTrue($navigation->related(collect([$product]), ['slug' => 'missing'])->isEmpty());
    }
}
