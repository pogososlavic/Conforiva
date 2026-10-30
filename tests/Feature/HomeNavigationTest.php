<?php

namespace Tests\Feature;

use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class HomeNavigationTest extends TestCase
{
    private string $fixture;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        DB::shouldReceive('connection')->never();
        DB::shouldReceive('connectUsing')->never();
        Http::preventStrayRequests();
        $this->fixture = sys_get_temp_dir().'/home-navigation-'.bin2hex(random_bytes(8));
        File::makeDirectory($this->fixture.'/products', 0700, true);
        File::makeDirectory($this->fixture.'/localizations', 0700, true);

        // The navigation suite must not depend on a workstation's real catalogue.
        $selections = [
            'heating' => ['heating', 'fixture-heating', 'Chauffage synthétique', 'Synthetic heating', 'Synthetische Heizung'],
            'furniture' => ['furniture', 'fixture-furniture', 'Meuble synthétique', 'Synthetic furniture', 'Synthetisches Möbel'],
            'cooling' => ['appliances', 'fixture-cooling', 'Réfrigérateur synthétique', 'Synthetic refrigerator', 'Synthetischer Kühlschrank'],
            'washing' => ['appliances', 'lavage-lave-linge', 'Lave-linge synthétique', 'Synthetic washing machine', 'Synthetische Waschmaschine'],
            'coffee' => ['appliances', 'cafe-baristina', 'Cafetière synthétique', 'Synthetic coffee machine', 'Synthetische Kaffeemaschine'],
            'firewood' => ['firewood', 'fixture-firewood', 'Bois synthétique', 'Synthetic firewood', 'Synthetisches Brennholz'],
            'freezer' => ['appliances', 'fixture-freezer', 'Congélateur synthétique', 'Synthetic freezer', 'Synthetischer Gefrierschrank'],
        ];
        $products = [];
        $translations = ['en' => ['Description synthétique' => 'Synthetic description'],
            'de' => ['Description synthétique' => 'Synthetische Beschreibung']];
        foreach ($selections as $key => [$universe, $category, $french, $english, $german]) {
            $product = ['slug' => 'home-fixture-'.$key, 'name' => $french, 'brand' => 'Fixture',
                'reference' => 'FIXTURE-'.strtoupper($key), 'universe' => $universe,
                'category' => ['slug' => $category, 'name' => $french], 'condition' => 'new',
                'price_cents' => 12345, 'currency' => 'EUR', 'has_variants' => false,
                'issues' => [], 'image' => null, 'images' => [], 'variants' => [],
                'description' => 'Description synthétique', 'specifications' => []];
            $products[] = $product;
            $translations['en'][$french] = $english;
            $translations['de'][$french] = $german;
            File::put($this->fixture.'/products/'.$product['slug'].'.json', json_encode($product));
        }
        File::put($this->fixture.'/index.json', json_encode(['meta' => ['complete' => true], 'products' => $products]));
        foreach ($translations as $locale => $strings) {
            File::put($this->fixture.'/localizations/'.$locale.'.json', json_encode(['version' => 1, 'strings' => $strings]));
        }
        config(['catalogue.source' => 'files', 'catalogue.archive_path' => $this->fixture.'/index.json',
            'catalogue.products_path' => $this->fixture.'/products', 'catalogue.localizations_path' => $this->fixture.'/localizations',
            'catalogue.media_overrides_path' => $this->fixture.'/absent-overrides.json',
            'home.freezer_product_slug' => 'home-fixture-freezer',
            'home.featured' => ['home-fixture-cooling', 'home-fixture-freezer'], 'home.discovery' => ['home-fixture-heating'],
            'home.departments' => array_map(fn ($department) => array_replace($department,
                ['product_slug' => 'home-fixture-'.$department['key']]), config('home.departments')),
            'dashboard.enabled' => false, 'commerce.enabled' => false, 'commerce.inventory_enabled' => false,
            'storefront_protection.mode' => 'off']);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->fixture);
        parent::tearDown();
    }

    public function test_home_navigation_has_real_destinations_in_every_language_without_database(): void
    {
        // Contact destinations are synthetic and inspected without following them.
        config(['contact.phone' => '+33123456789', 'contact.email' => 'support@example.test']);
        DB::shouldReceive('connection')->never();

        foreach (['fr', 'en', 'de'] as $locale) {
            $response = $this->get('/'.$locale)->assertOk()->assertViewHas('catalogueReady', true);
            $this->assertSame('home-fixture-freezer', $response->viewData('freezerProduct')['slug']);
            $document = new DOMDocument;
            @$document->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
            $xpath = new DOMXPath($document);

            $this->assertSame(1, $xpath->query('//main')->length);
            $this->assertSame(1, $xpath->query('//h1')->length);
            $this->assertSame('noindex, nofollow', $xpath->query('//meta[@name="robots"]')->item(0)->getAttribute('content'));
            $this->assertSame(5, $xpath->query('//li[starts-with(@id, "universe-")]')->length);

            foreach ($xpath->query('//a[@href]') as $link) {
                $url = parse_url($link->getAttribute('href'));
                $this->assertIsArray($url);
                if (($url['scheme'] ?? null) === 'mailto') {
                    $this->assertSame(['scheme' => 'mailto', 'path' => 'support@example.test'], $url);

                    continue;
                }
                if (($url['host'] ?? null) === 'wa.me') {
                    $this->assertSame('https://wa.me/33123456789', $link->getAttribute('href'));
                    $this->assertSame('_blank', $link->getAttribute('target'));
                    $this->assertSame('noopener noreferrer', $link->getAttribute('rel'));
                    $this->assertSame('no-referrer', $link->getAttribute('referrerpolicy'));

                    continue;
                }
                if (in_array($url['host'] ?? null, ['commons.wikimedia.org', 'creativecommons.org'], true)) {
                    $this->assertContains($link->getAttribute('href'), [
                        'https://commons.wikimedia.org/wiki/File:Living_room_(Unsplash).jpg',
                        'https://commons.wikimedia.org/wiki/File:Blue_white_kitchen_interior_(Unsplash).jpg',
                        'https://commons.wikimedia.org/wiki/File:2016-366-319_On_Fire_(25358789789).jpg',
                        'https://commons.wikimedia.org/wiki/File:Cozy_interior_with_a_sofa_(Unsplash).jpg',
                        'https://creativecommons.org/publicdomain/zero/1.0/',
                    ]);
                    $this->assertSame(1, $xpath->query('ancestor::details[contains(@class,"home-photo-credits")]', $link)->length);

                    continue;
                }
                if (isset($url['scheme'])) {
                    $this->assertContains($url['scheme'], ['http', 'https']);
                }
                $this->assertSame(parse_url(config('app.url'), PHP_URL_HOST), $url['host'] ?? parse_url(config('app.url'), PHP_URL_HOST));
                if (isset($url['fragment'])) {
                    $destination = $xpath;
                    if (isset($url['path']) && $url['path'] !== '/'.$locale) {
                        $target = $this->get($url['path'].(isset($url['query']) ? '?'.$url['query'] : ''))->assertOk();
                        $targetDocument = new DOMDocument;
                        @$targetDocument->loadHTML('<?xml encoding="UTF-8">'.$target->getContent());
                        $destination = new DOMXPath($targetDocument);
                    }
                    $this->assertSame(1, $destination->query('//*[@id="'.$url['fragment'].'"]')->length, 'Missing navigation destination');
                }
                if (isset($url['path']) && preg_match('~^/(media/energy/[a-z0-9-]+\.pdf|legal/guarantees/eu-legal-guarantee-(fr|en|de)\.svg)$~', $url['path'])) {
                    $this->assertFileExists(public_path(ltrim($url['path'], '/')));

                    continue;
                }
                if (isset($url['path'])) {
                    $this->assertMatchesRegularExpression('~^/(fr|en|de)(/catalogue|/promotions|/retours/declaration|/produit/[a-z0-9-]+|/(a-propos|provenance|conseiller|contact|livraison|retractation|paiement|suivi|panier|avis|mentions-legales|cgv|confidentialite|cookies))?$~', $url['path']);
                }
            }
            $this->assertGreaterThan(0, $xpath->query('//a[@href="mailto:support@example.test"]')->length);
            $this->assertGreaterThan(0, $xpath->query('//a[@href="https://wa.me/33123456789"]')->length);

            $languageLinks = $xpath->query('//nav[contains(@class, "languages")]/a');
            $this->assertSame(3, $languageLinks->length);
            $this->assertSame(1, $xpath->query('//nav[contains(@class, "languages")]/a[@aria-current="page"]')->length);
            foreach ($languageLinks as $link) {
                $this->assertNotEmpty($link->getAttribute('aria-label'));
            }
        }
    }

    public function test_unpublished_catalogue_is_explicit_and_cannot_submit_a_search(): void
    {
        config(['catalogue.archive_path' => sys_get_temp_dir().'/homya-nonexistent-'.uniqid().'.json']);
        foreach (['fr', 'en', 'de'] as $locale) {
            $response = $this->get('/'.$locale);
            $response->assertSee(__('home.status_detail', [], $locale));
            $document = new DOMDocument;
            @$document->loadHTML($response->getContent());
            $xpath = new DOMXPath($document);

            $this->assertSame(1, $xpath->query('//input[@type="search"][@disabled][@aria-describedby="search-help"]')->length);
            $this->assertSame(1, $xpath->query('//label[@for="catalogue-search"]')->length);
            $this->assertSame(0, $xpath->query('//form//button[@type="submit"]')->length);
            $this->assertSame(0, $xpath->query('//*[@itemscope or @itemprop="price"]')->length);
        }
    }

    public function test_cooling_department_link_and_catalogue_search_are_localized_without_losing_context(): void
    {
        DB::shouldReceive('connection')->never();
        foreach (['fr' => 'réfrigérateur', 'en' => 'Refrigerators', 'de' => 'Kühlschränke'] as $locale => $query) {
            $response = $this->get('/'.$locale)->assertOk();
            $document = new DOMDocument;
            @$document->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
            $xpath = new DOMXPath($document);
            $link = $xpath->query('//*[@id="universe-appliances"]//a')->item(0)->getAttribute('href');
            $url = parse_url($link);
            parse_str($url['query'], $filters);
            $this->assertSame('/'.$locale.'/catalogue', $url['path']);
            $this->assertSame(['universe' => 'appliances', 'q' => $query], $filters);
            $catalogue = $this->get($url['path'].'?'.$url['query'])->assertOk();
            $this->assertSame(['home-fixture-cooling'], $catalogue->viewData('products')->getCollection()->pluck('slug')->all());
            $document = new DOMDocument;
            @$document->loadHTML('<?xml encoding="UTF-8">'.$catalogue->getContent());
            $xpath = new DOMXPath($document);
            $this->assertSame($query, $xpath->query('//input[@id="filter-query"]')->item(0)->getAttribute('value'));
            ksort($filters);
            foreach ($xpath->query('//a[@data-language-link]') as $languageLink) {
                parse_str(parse_url($languageLink->getAttribute('href'), PHP_URL_QUERY), $context);
                ksort($context);
                $this->assertSame($filters, $context);
            }
        }
    }
}
