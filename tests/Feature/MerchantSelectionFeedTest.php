<?php

namespace Tests\Feature;

use App\Http\Middleware\ProductionGuard;
use App\Http\Middleware\StorefrontActivity;
use App\Services\Catalogue;
use App\Services\Commerce\CheckoutReadiness;
use App\Services\Commerce\InventoryAvailability;
use DOMDocument;
use DOMXPath;
use Illuminate\Database\Connection;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;

class MerchantSelectionFeedTest extends TestCase
{
    private const URL = 'https://home-confort.org/google/merchant/shopping150-fr.xml';

    private array $manifest;

    private array $products = [];

    private array $stock = [];

    private array $temporaryFiles = [];

    private bool $sourceUnavailable = false;

    private bool $translationUnavailable = false;

    private bool $checkoutReady = true;

    private int $inventoryReads = 0;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        $this->withoutMiddleware([ProductionGuard::class, StorefrontActivity::class]);
        $this->app['env'] = 'production';
        config(['app.url' => 'https://home-confort.org', 'app.debug' => false,
            'seo.public_url' => 'https://home-confort.org', 'seo.indexing_enabled' => true,
            'dashboard.enabled' => false, 'commerce.enabled' => false,
            'commerce.inventory_enabled' => true, 'commerce.legal_ready' => true,
            'merchant.enabled' => false, 'merchant-selection.enabled' => true,
            'commerce.connection' => 'commerce', 'storefront_protection.mode' => 'off',
            'database.connections.commerce' => ['driver' => 'mariadb', 'database' => 'merchant_feed_fixture',
                'username' => 'merchant_feed_fixture']]);
        // These public catalogue fixtures must be the exact recovered, sealed resources.
        foreach (['manifest' => 'json', 'template' => 'xml'] as $kind => $extension) {
            $path = resource_path('data/merchant-selection-150.'.$extension);
            $this->assertSame($path, config('merchant-selection.'.$kind.'_path'));
            $this->assertSame(config('merchant-selection.'.$kind.'_sha256'), hash_file('sha256', $path));
        }
        $this->manifest = json_decode(file_get_contents(resource_path('data/merchant-selection-150.json')), true, 32, JSON_THROW_ON_ERROR);
        $template = $this->xpath(file_get_contents(resource_path('data/merchant-selection-150.xml')));
        foreach ($this->manifest['products'] as $index => $approved) {
            $slug = $approved['slug'];
            $product = array_intersect_key($approved, array_flip(['slug', 'reference', 'name', 'description', 'brand', 'condition']));
            $price = $template->evaluate('string(/rss/channel/item['.($index + 1).']/g:price)');
            $product += ['price_cents' => (int) str_replace('.', '', substr($price, 0, -4)), 'currency' => 'EUR',
                'has_variants' => false, 'variants' => [], 'images' => [['local_url' => $approved['main_image_path']]],
                'image' => ['local_url' => $approved['main_image_path']]];
            if ($approved['sales_pack'] !== null) {
                $pack = $approved['sales_pack'];
                $product['sales_pack'] = ['type' => 'merchant_multipack', 'identifier_scope' => 'unit_bag',
                    'count' => $pack['multipack'], 'unit_weight_kg' => intdiv((int) substr($pack['unit_pricing_measure'], 0, -2), $pack['multipack']),
                    'owner_confirmed_on' => '2026-10-20'];
            }
            $this->products[$slug] = $product;
            $this->stock[$slug] = (object) ['slug' => $slug, 'reference' => $approved['reference'],
                'sale_enabled' => 1, 'approved_price_cents' => $product['price_cents'], 'validated_at' => '2026-10-22 12:00:00',
                'quantity' => 500, 'reserved' => 0];
        }
        $catalogue = Mockery::mock(Catalogue::class);
        $catalogue->shouldReceive('product')->with(Mockery::type('string'))->andReturnUsing(fn ($slug) => $this->products[$slug] ?? null);
        $catalogue->shouldReceive('products')->never();
        $catalogue->shouldReceive('sourceUnavailable')->andReturnUsing(fn () => $this->sourceUnavailable);
        $catalogue->shouldReceive('translationUnavailable')->andReturnUsing(fn () => $this->translationUnavailable);
        $this->app->instance(Catalogue::class, $catalogue);
        $this->app->bind(CheckoutReadiness::class, fn () => new class(fn () => $this->checkoutReady)
        {
            public function __construct(private \Closure $ready) {}

            public function bankReady(): bool
            {
                return ($this->ready)();
            }

            public function cardReady(): bool
            {
                return false;
            }
        });
        $this->app->bind(InventoryAvailability::class, fn () => throw new \RuntimeException('Inventory expiry/write service forbidden.'));
        $query = Mockery::mock(Builder::class);
        $query->shouldReceive('select')->with(['slug', 'reference', 'sale_enabled', 'approved_price_cents', 'validated_at', 'quantity', 'reserved'])->andReturnSelf();
        $query->shouldReceive('get')->andReturnUsing(function () {
            $this->inventoryReads++;

            return collect(array_values($this->stock));
        });
        // Only this SELECT builder exists: expiry, transactions, update/insert/delete
        // and all unrelated tables fail the successful-response tests.
        $connection = Mockery::mock(Connection::class);
        $connection->shouldReceive('table')->with('commerce_inventory')->andReturn($query);
        DB::shouldReceive('connection')->with('commerce')->andReturn($connection);
        DB::shouldReceive('connectUsing')->never();
    }

    protected function tearDown(): void
    {
        foreach ($this->temporaryFiles as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
        parent::tearDown();
    }

    private function xpath(string $xml): DOMXPath
    {
        $document = new DOMDocument;
        $this->assertTrue($document->loadXML($xml, LIBXML_NONET));
        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('g', 'http://base.google.com/ns/1.0');

        return $xpath;
    }

    private function temporary(string $contents): string
    {
        $path = tempnam(sys_get_temp_dir(), 'merchant-selection-');
        file_put_contents($path, $contents);
        $this->temporaryFiles[] = $path;

        return $path;
    }

    private function replaceManifest(array $manifest, bool $reseal = true): void
    {
        $contents = json_encode($manifest, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        config(['merchant-selection.manifest_path' => $this->temporary($contents)]);
        if ($reseal) {
            config(['merchant-selection.manifest_sha256' => hash('sha256', $contents)]);
        }
    }

    private function replaceTemplate(string $xml): void
    {
        config(['merchant-selection.template_path' => $this->temporary($xml),
            'merchant-selection.template_sha256' => hash('sha256', $xml)]);
        $this->manifest['template_sha256'] = hash('sha256', $xml);
        $this->replaceManifest($this->manifest);
    }

    private function assertClosed(): void
    {
        $this->get(self::URL)->assertStatus(503)->assertContent('Merchant feed unavailable.')
            ->assertHeader('Cache-Control', 'no-store, private')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    }

    public function test_only_live_price_and_availability_change_with_ids_certifications_and_other_fields_preserved(): void
    {
        $slug = $this->manifest['products'][0]['slug'];
        // This is the public projection after promotion/admin pricing, not an archive price.
        $this->products[$slug]['price_cents'] = 109999;
        $this->products[$slug]['promotion'] = ['base_price_cents' => 119970, 'price_cents' => 109999];
        $this->stock[$slug]->approved_price_cents = '109999';
        $this->stock[$slug]->quantity = '0';
        $this->stock[$slug]->reserved = '0';
        $extra = $this->products[$slug];
        $extra['slug'] = 'unreviewed-151st-offer';
        $this->products[$extra['slug']] = $extra;
        $before = file_get_contents(resource_path('data/merchant-selection-150.xml'));
        $response = $this->get(self::URL)->assertOk()->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
            ->assertHeader('Cache-Control', 'no-store, private')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $xpath = $this->xpath($response->getContent());
        $this->assertSame(150, $xpath->query('/rss/channel/item')->length);
        $this->assertSame('1099.99 EUR', $xpath->evaluate('string(/rss/channel/item[1]/g:price)'));
        $this->assertSame('out_of_stock', $xpath->evaluate('string(/rss/channel/item[1]/g:availability)'));
        $original = $this->xpath($before);
        foreach ([$xpath, $original] as $document) {
            foreach (iterator_to_array($document->query('//g:price | //g:availability')) as $node) {
                $node->parentNode->removeChild($node);
            }
        }
        $this->assertSame($original->document->C14N(), $xpath->document->C14N());
        $this->assertSame($before, file_get_contents(resource_path('data/merchant-selection-150.xml')));
        $this->assertSame(1, $this->inventoryReads);
        $this->assertFalse(config('merchant.enabled'));
        $this->get('https://home-confort.org/google/merchant/fr.xml')->assertNotFound();
        $this->assertSame(1, $this->inventoryReads);
    }

    public function test_next_fetch_reflects_updated_price_stock_and_reservations_without_new_review(): void
    {
        $slug = $this->manifest['products'][0]['slug'];
        $this->stock[$slug]->quantity = 10;
        $this->stock[$slug]->reserved = 10;
        $first = $this->xpath($this->get(self::URL)->assertOk()->getContent());
        $this->assertSame('out_of_stock', $first->evaluate('string(/rss/channel/item[1]/g:availability)'));
        $this->products[$slug]['price_cents'] = 89007;
        $this->stock[$slug]->approved_price_cents = 89007;
        $this->stock[$slug]->reserved = 9;
        $second = $this->xpath($this->get(self::URL)->assertOk()->getContent());
        $this->assertSame('890.07 EUR', $second->evaluate('string(/rss/channel/item[1]/g:price)'));
        $this->assertSame('in_stock', $second->evaluate('string(/rss/channel/item[1]/g:availability)'));
        $this->assertSame(2, $this->inventoryReads);
    }

    public function test_unavailable_catalogue_or_translation_is_never_replaced_by_snapshot_prices(): void
    {
        $this->sourceUnavailable = true;
        $this->assertClosed();
        $this->sourceUnavailable = false;
        $this->translationUnavailable = true;
        $this->assertClosed();
        $this->assertSame(0, $this->inventoryReads);
    }

    public function test_changed_identity_french_content_image_pack_or_variants_requires_review(): void
    {
        $slug = $this->manifest['products'][0]['slug'];
        $original = $this->products[$slug];
        $changes = [
            ['name' => $original['name'].' modifié'], ['description' => 'Description différente.'],
            ['reference' => 'OTHER-MODEL'], ['brand' => 'Other brand'], ['condition' => 'used'],
            ['has_variants' => true], ['variants' => [['title' => 'Other variant']]],
            ['images' => [['local_url' => '/media/catalogue/unreviewed.jpg']]],
            ['sales_pack' => ['type' => 'merchant_multipack', 'identifier_scope' => 'unit_bag',
                'count' => 2, 'unit_weight_kg' => 15, 'owner_confirmed_on' => '2026-10-22']],
        ];
        foreach ($changes as $change) {
            $this->products[$slug] = array_replace($original, $change);
            $this->assertClosed();
        }
    }

    public function test_reviewed_lg_photo_can_remain_second_in_public_gallery_without_changing_feed(): void
    {
        $slug = 'lg-gsxv80pzle-americain';
        $approvedImage = $this->products[$slug]['images'][0];
        $firstImage = ['local_url' => '/media/catalogue/939b1954a8741c59b29927ae.jpg'];
        $this->products[$slug]['images'] = [$firstImage, $approvedImage];
        $this->products[$slug]['image'] = $firstImage;
        $before = $this->xpath(file_get_contents(resource_path('data/merchant-selection-150.xml')));
        $after = $this->xpath($this->get(self::URL)->assertOk()->getContent());
        $this->assertSame($before->document->C14N(), $after->document->C14N());
        $index = array_search($slug, array_column($this->manifest['products'], 'slug'), true) + 1;
        $this->assertSame('https://home-confort.org'.$approvedImage['local_url'],
            $after->evaluate('string(/rss/channel/item['.$index.']/g:image_link)'));

        // Only gallery membership controls the reviewed feed photo. Changing the
        // independent storefront hero never introduces another image into the XML.
        $this->products[$slug]['image'] = ['local_url' => '/media/catalogue/another-hero.jpg'];
        $after = $this->xpath($this->get(self::URL)->assertOk()->getContent());
        $this->assertSame($before->document->C14N(), $after->document->C14N());
    }

    public function test_reviewed_photo_removed_from_gallery_is_rejected_even_when_hero_still_matches(): void
    {
        $slug = 'lg-gsxv80pzle-americain';
        $approvedImage = $this->products[$slug]['image'];
        foreach ([[['local_url' => '/media/catalogue/939b1954a8741c59b29927ae.jpg']], [], null,
            [['local_url' => $approvedImage['local_url'].'.bak']]] as $images) {
            $this->products[$slug]['images'] = $images;
            $this->products[$slug]['image'] = $approvedImage;
            $this->assertClosed();
        }
        unset($this->products[$slug]['images']);
        $this->assertClosed();
    }

    public function test_unpublished_or_missing_product_closes_entire_primary_feed(): void
    {
        $slug = $this->manifest['products'][149]['slug'];
        $this->products[$slug]['admin_published'] = false;
        $this->assertClosed();
        unset($this->products[$slug]);
        $this->assertClosed();
    }

    public function test_stock_must_be_validated_and_match_live_price_reference_and_reservations(): void
    {
        $slug = $this->manifest['products'][0]['slug'];
        $original = clone $this->stock[$slug];
        foreach ([['sale_enabled' => 0], ['sale_enabled' => 'yes'], ['validated_at' => null], ['validated_at' => ''],
            ['reference' => 'OTHER-MODEL'], ['approved_price_cents' => 1], ['quantity' => -1],
            ['reserved' => -1], ['quantity' => 1, 'reserved' => 2], ['quantity' => '1.5'],
            ['reserved' => false], ['approved_price_cents' => (float) $original->approved_price_cents]] as $change) {
            $this->stock[$slug] = (object) array_replace((array) $original, $change);
            $this->assertClosed();
        }
        unset($this->stock[$slug]);
        $this->assertClosed();
    }

    public function test_invalid_price_or_currency_is_never_exported(): void
    {
        $slug = $this->manifest['products'][0]['slug'];
        $original = $this->products[$slug];
        foreach ([['price_cents' => 0], ['price_cents' => -1], ['price_cents' => 1.23],
            ['price_cents' => config('commerce.max_total_cents') + 1], ['currency' => 'USD']] as $change) {
            $this->products[$slug] = array_replace($original, $change);
            $this->assertClosed();
        }
    }

    public function test_local_insecure_wrong_host_and_disabled_requests_are_not_found(): void
    {
        foreach (['http://127.0.0.1:8080', 'http://home-confort.org', 'https://other-store.com'] as $origin) {
            $this->get($origin.'/google/merchant/shopping150-fr.xml')->assertNotFound();
        }
        config(['merchant-selection.enabled' => false]);
        $this->get(self::URL)->assertNotFound();
        $this->assertSame(0, $this->inventoryReads);
    }

    public function test_other_commerce_prerequisites_still_close_feed(): void
    {
        foreach (['commerce.inventory_enabled', 'commerce.legal_ready'] as $key) {
            config([$key => false]);
            $this->assertClosed();
            config([$key => true]);
        }
        $this->checkoutReady = false;
        $this->assertClosed();
        $this->checkoutReady = true;
        config(['app.url' => 'https://other-store.com']);
        $this->assertClosed();
        $this->assertSame(0, $this->inventoryReads);
    }

    public function test_tampered_manifest_template_and_missing_files_fail_closed_before_stock_read(): void
    {
        $this->manifest['products'][0]['name'] .= ' Tampered';
        $this->replaceManifest($this->manifest, false);
        $this->assertClosed();
        config(['merchant-selection.manifest_path' => resource_path('data/merchant-selection-150.json'),
            'merchant-selection.template_path' => $this->temporary('<rss>tampered</rss>')]);
        $this->assertClosed();
        config(['merchant-selection.template_path' => '/nonexistent-private-selection-file.xml']);
        $this->assertClosed();
        $this->assertSame(0, $this->inventoryReads);
    }

    public function test_manifest_count_duplicates_and_invalid_json_fail_closed_even_with_configured_digest(): void
    {
        $original = $this->manifest;
        $this->manifest['products'][] = $original['products'][0];
        $this->replaceManifest($this->manifest);
        $this->assertClosed();
        $this->manifest = $original;
        $this->manifest['products'][1] = $this->manifest['products'][0];
        $this->replaceManifest($this->manifest);
        $this->assertClosed();
        config(['merchant-selection.manifest_path' => $this->temporary('{invalid json'),
            'merchant-selection.manifest_sha256' => hash('sha256', '{invalid json')]);
        $this->assertClosed();
        $this->assertSame(0, $this->inventoryReads);
    }

    public function test_malformed_xml_doctype_and_duplicate_xml_ids_are_rejected(): void
    {
        $template = file_get_contents(resource_path('data/merchant-selection-150.xml'));
        foreach (['<rss>', str_replace('<rss ', '<!DOCTYPE rss [<!ENTITY secret SYSTEM "file:///etc/passwd">]><rss ', $template),
            str_replace($this->manifest['products'][1]['id'], $this->manifest['products'][0]['id'], $template)] as $xml) {
            $this->replaceTemplate($xml);
            $this->assertClosed();
        }
        $this->assertSame(0, $this->inventoryReads);
    }

    public function test_oversized_sealed_file_is_rejected(): void
    {
        $this->replaceTemplate(str_repeat('x', 1_000_001));
        $this->assertClosed();
        $this->assertSame(0, $this->inventoryReads);
    }
}
