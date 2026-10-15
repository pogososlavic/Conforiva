<?php

namespace Tests\Feature;

use App\Http\Middleware\StorefrontScrapeGuard;
use App\Services\GoogleCrawlerRanges;
use App\Services\StorefrontRequestLimiter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Tests\Support\CatalogueCapsuleFixture;
use Tests\TestCase;

class StorefrontProtectionTest extends TestCase
{
    private CatalogueCapsuleFixture $files;

    protected function setUp(): void
    {
        parent::setUp();
        DB::shouldReceive('connection')->never();
        DB::shouldReceive('connectUsing')->never();
        Http::preventStrayRequests();
        Log::spy();
        $this->files = new CatalogueCapsuleFixture;
        $this->app->useStoragePath($this->files->root.'/storage');
        $this->files->write('storage/framework/cache/.fixture', 'isolated');
        config([
            'app.key' => 'base64:'.base64_encode(str_repeat('t', 32)),
            'homya.locales' => ['fr', 'en', 'de'],
            'storefront_protection.mode' => 'observe',
            'storefront_protection.client_ip_verified' => true,
            'storefront_protection.cache_store' => 'storefront_protection',
            'storefront_protection.requests_per_minute' => 2,
            'storefront_protection.requests_per_hour' => 4,
            'storefront_protection.crawler_ranges_path' => storage_path('app/private/security/google-crawler-ranges.json'),
            'cache.stores.storefront_protection' => [
                'driver' => 'file',
                'path' => storage_path('framework/cache/storefront-protection/data'),
                'lock_path' => storage_path('framework/cache/storefront-protection/locks'),
            ],
        ]);
        Cache::purge('storefront_protection');
        $this->ranges();
    }

    protected function tearDown(): void
    {
        Cache::purge('storefront_protection');
        $this->files->destroy();
        parent::tearDown();
    }

    private function ranges(?int $fetchedAt = null): void
    {
        $sources = [];
        foreach (GoogleCrawlerRanges::SOURCES as $source) {
            $sources[$source] = ['creation_time' => '2026-01-01T00:00:00Z',
                'prefixes' => ['66.249.64.0/19', '2001:4860:4801::/48']];
        }
        $this->files->writeJson('storage/app/private/security/google-crawler-ranges.json', [
            'schema' => 1, 'fetched_at' => $fetchedAt ?? time(), 'sources' => $sources,
        ]);
    }

    private function visit(string $path = '/fr/catalogue', string $ip = '192.0.2.100', string $method = 'GET', string $ua = 'Mozilla/5.0'): Response
    {
        $request = Request::create($path, $method, [], [], [], ['REMOTE_ADDR' => $ip, 'HTTP_USER_AGENT' => $ua]);

        return (new StorefrontScrapeGuard(new GoogleCrawlerRanges, new StorefrontRequestLimiter))
            ->handle($request, fn () => new Response('sentinel', 200));
    }

    public function test_observation_counts_shared_paths_but_never_blocks(): void
    {
        foreach (['/fr/catalogue?a=1', '/en', '/de/produit/fixture', '/fr/promotions', '/fr/catalogue?a=2'] as $path) {
            $this->assertSame(200, $this->visit($path)->getStatusCode());
        }
        Log::shouldHaveReceived('notice')->once()->with('HOMYA_STOREFRONT_PROTECTION_SIGNAL', \Mockery::on(
            fn (array $context) => $context['reason'] === 'request_limit_reached' && $context['mode'] === 'observe'
        ));
        $this->assertStringNotContainsString('192.0.2.100', file_get_contents(storage_path('framework/cache/storefront-protection-signals.json')));
    }

    public function test_enforcement_rejects_fake_google_ua_and_retains_required_headers(): void
    {
        config(['storefront_protection.mode' => 'enforce']);
        $this->assertSame(200, $this->visit(ua: 'Googlebot')->getStatusCode());
        $this->assertSame(200, $this->visit('/fr', ua: 'Googlebot')->getStatusCode());
        $response = $this->visit('/fr/produit/fixture?x=1', ua: 'Googlebot');
        $this->assertSame(429, $response->getStatusCode());
        $this->assertGreaterThan(0, (int) $response->headers->get('Retry-After'));
        $this->assertLessThanOrEqual(60, (int) $response->headers->get('Retry-After'));
        $this->assertSame('no-store, private', $response->headers->get('Cache-Control'));
        $this->assertSame('noindex, nofollow', $response->headers->get('X-Robots-Tag'));
        $this->assertSame('', $this->visit(method: 'HEAD')->getContent());
    }

    public function test_verified_google_ipv4_and_ipv6_bypass_without_user_agent(): void
    {
        config(['storefront_protection.mode' => 'enforce']);
        foreach (['66.249.70.10', '2001:4860:4801::123'] as $ip) {
            for ($index = 0; $index < 6; $index++) {
                $this->assertSame(200, $this->visit(ip: $ip, ua: '')->getStatusCode());
            }
        }
        $this->assertDirectoryDoesNotExist(storage_path('framework/cache/storefront-protection/data'));
    }

    public function test_unverified_client_ip_degrades_enforcement_to_observation(): void
    {
        config(['storefront_protection.mode' => 'enforce', 'storefront_protection.client_ip_verified' => false]);
        for ($index = 0; $index < 5; $index++) {
            $this->assertSame(200, $this->visit()->getStatusCode());
        }
        Log::shouldHaveReceived('notice')->with('HOMYA_STOREFRONT_PROTECTION_SIGNAL', \Mockery::on(
            fn (array $context) => $context['reason'] === 'client_ip_not_verified' && $context['mode'] === 'observe'
        ))->once();
    }

    public function test_stale_or_missing_crawler_lists_degrade_safely(): void
    {
        config(['storefront_protection.mode' => 'enforce']);
        $this->ranges(time() - 49 * 3600);
        for ($index = 0; $index < 4; $index++) {
            $this->assertSame(200, $this->visit()->getStatusCode());
        }
        unlink((string) config('storefront_protection.crawler_ranges_path'));
        $this->assertSame(200, $this->visit()->getStatusCode());
    }

    public function test_unprotected_paths_methods_and_invalid_modes_do_not_consume_budget(): void
    {
        config(['storefront_protection.mode' => 'enforce']);
        foreach (['/robots.txt', '/sitemap.xml', '/google/merchant/fr.xml', '/fr/panier', '/admin', '/media/product.jpg', '/fr/cgv', '/fr/contact'] as $path) {
            $this->assertSame(200, $this->visit($path)->getStatusCode());
        }
        $this->assertSame(200, $this->visit(method: 'POST')->getStatusCode());
        $this->assertDirectoryDoesNotExist(storage_path('framework/cache/storefront-protection/data'));
        foreach (['off', 'unexpected'] as $mode) {
            config(['storefront_protection.mode' => $mode]);
            for ($index = 0; $index < 4; $index++) {
                $this->assertSame(200, $this->visit()->getStatusCode());
            }
        }
        $this->assertDirectoryDoesNotExist(storage_path('framework/cache/storefront-protection/data'));
    }

    public function test_hour_budget_applies_when_minute_budget_is_higher(): void
    {
        config(['storefront_protection.mode' => 'enforce', 'storefront_protection.requests_per_minute' => 20]);
        for ($index = 0; $index < 4; $index++) {
            $this->assertSame(200, $this->visit()->getStatusCode());
        }
        $response = $this->visit();
        $this->assertSame(429, $response->getStatusCode());
        $this->assertGreaterThan(3500, (int) $response->headers->get('Retry-After'));
    }

    public function test_cache_lock_contention_does_not_queue_or_block_the_visit(): void
    {
        config(['storefront_protection.mode' => 'enforce']);
        $identity = hash_hmac('sha256', 'storefront-v1|'.inet_pton('192.0.2.100'), str_repeat('t', 32));
        $lock = Cache::store('storefront_protection')->getStore()->lock('homya:storefront:lock:'.$identity, 10);
        $this->assertTrue($lock->get());
        try {
            $started = microtime(true);
            $this->assertSame(200, $this->visit()->getStatusCode());
            $this->assertLessThan(1, microtime(true) - $started);
        } finally {
            $lock->release();
        }
        Log::shouldHaveReceived('notice')->with('HOMYA_STOREFRONT_PROTECTION_SIGNAL', \Mockery::on(
            fn (array $context) => $context['reason'] === 'limiter_unavailable'
        ))->once();
    }

    public function test_bad_cache_configuration_and_spoofed_forwarded_ip_fail_open_without_bypass(): void
    {
        config(['storefront_protection.mode' => 'enforce']);
        $request = Request::create('/fr/catalogue', 'GET', [], [], [], [
            'REMOTE_ADDR' => '192.0.2.100', 'HTTP_X_FORWARDED_FOR' => '66.249.70.10', 'HTTP_USER_AGENT' => 'Googlebot',
        ]);
        $guard = new StorefrontScrapeGuard(new GoogleCrawlerRanges, new StorefrontRequestLimiter);
        for ($index = 0; $index < 3; $index++) {
            $response = $guard->handle($request, fn () => new Response('sentinel', 200));
        }
        $this->assertSame(429, $response->getStatusCode());
        config(['storefront_protection.cache_store' => 'array']);
        $this->assertSame(200, $this->visit()->getStatusCode());
    }

    public function test_crawler_ranges_reject_broad_future_malformed_and_unapproved_sources(): void
    {
        $this->assertTrue(GoogleCrawlerRanges::validPrefix('66.249.64.0/19'));
        $this->assertTrue(GoogleCrawlerRanges::validPrefix('2001:4860:4801::/48'));
        foreach (['0.0.0.0/0', '2001:4860::/16', '192.0.2.1/33', 'not-an-ip/32', '192.0.2.1'] as $prefix) {
            $this->assertFalse(GoogleCrawlerRanges::validPrefix($prefix));
        }
        $this->ranges(time() + 1000);
        $this->assertFalse((new GoogleCrawlerRanges)->ready());
        $data = json_decode(file_get_contents((string) config('storefront_protection.crawler_ranges_path')), true);
        $data['fetched_at'] = time();
        $data['sources']['https://untrusted.example/list.json'] = array_pop($data['sources']);
        $this->files->writeJson('storage/app/private/security/google-crawler-ranges.json', $data);
        $this->assertFalse((new GoogleCrawlerRanges)->ready());
    }

    public function test_prune_removes_only_old_hash_files_in_its_fixed_isolated_cache(): void
    {
        $old = 'storage/framework/cache/storefront-protection/data/aa/bb/'.str_repeat('a', 40);
        $fresh = 'storage/framework/cache/storefront-protection/locks/cc/dd/'.str_repeat('b', 40);
        $outside = 'storage/framework/cache/data/aa/bb/'.str_repeat('c', 40);
        $unknown = 'storage/framework/cache/storefront-protection/data/aa/bb/keep.txt';
        foreach ([$old, $fresh, $outside, $unknown] as $path) {
            $this->files->write($path, 'sentinel');
        }
        touch($this->files->root.'/'.$old, time() - 8000);
        touch($this->files->root.'/'.$outside, time() - 8000);
        touch($this->files->root.'/'.$unknown, time() - 8000);
        $link = $this->files->root.'/storage/framework/cache/storefront-protection/data/aa/bb/'.str_repeat('d', 40);
        symlink($this->files->root.'/'.$outside, $link);
        $this->artisan('security:prune-storefront-cache --dry-run')->assertSuccessful();
        $this->assertFileExists($this->files->root.'/'.$old);
        $this->artisan('security:prune-storefront-cache')->assertSuccessful();
        $this->assertFileDoesNotExist($this->files->root.'/'.$old);
        foreach ([$fresh, $outside, $unknown] as $path) {
            $this->assertFileExists($this->files->root.'/'.$path);
        }
        $this->assertTrue(is_link($link));
    }

    public function test_refresh_publishes_all_three_mocked_google_lists_with_private_permissions(): void
    {
        $upstream = ['creationTime' => '2026-01-01T00:00:00Z',
            'prefixes' => [['ipv4Prefix' => '66.249.64.0/19'], ['ipv6Prefix' => '2001:4860:4801::/48']]];
        Http::fake(array_fill_keys(GoogleCrawlerRanges::SOURCES, Http::response($upstream, 200)));
        $this->artisan('security:refresh-google-crawlers')->assertSuccessful();
        Http::assertSentCount(3);
        $path = (string) config('storefront_protection.crawler_ranges_path');
        $this->assertSame(0600, fileperms($path) & 0777);
        $this->assertSame(0700, fileperms(dirname($path)) & 0777);
        $this->assertTrue((new GoogleCrawlerRanges)->ready());
        $this->assertTrue((new GoogleCrawlerRanges)->contains('66.249.70.10'));
        $this->assertFalse((new GoogleCrawlerRanges)->contains('192.0.2.100'));
    }

    public function test_failed_refresh_preserves_previous_complete_file(): void
    {
        $path = (string) config('storefront_protection.crawler_ranges_path');
        $before = file_get_contents($path);
        $responses = [];
        foreach (GoogleCrawlerRanges::SOURCES as $index => $url) {
            $responses[$url] = Http::response(['creationTime' => '2026-01-01T00:00:00Z',
                'prefixes' => [['ipv4Prefix' => '66.249.64.0/19']]], $index === 2 ? 503 : 200);
        }
        Http::fake($responses);
        $this->artisan('security:refresh-google-crawlers')->assertFailed();
        $this->assertSame($before, file_get_contents($path));
        $this->assertTrue((new GoogleCrawlerRanges)->ready());
    }
}
