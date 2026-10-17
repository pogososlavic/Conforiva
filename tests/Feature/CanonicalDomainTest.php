<?php

namespace Tests\Feature;

use App\Http\Middleware\ProductionGuard;
use App\Http\Middleware\SecurityHeaders;
use App\Services\ProductionSecurity;
use Illuminate\Http\Request;
use Mockery;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class CanonicalDomainTest extends TestCase
{
    private function visit(string $url, string $method = 'GET', array $failures = []): Response
    {
        $this->app['env'] = 'production';
        config(['app.debug' => false, 'admin.remote_enabled' => false]);
        $security = Mockery::mock(new ProductionSecurity);
        $security->shouldReceive('repairPrivateDirectoryPermissions')->once()->andReturn(true);
        $security->shouldReceive('failures')->once()->andReturn($failures);
        $this->app->instance(ProductionSecurity::class, $security);
        $request = Request::create($url, $method);

        return (new SecurityHeaders)->handle($request, fn ($request) => (new ProductionGuard)->handle($request, fn () => new Response('destination', 200)));
    }

    public function test_www_redirects_to_fixed_https_host_preserving_path_and_query(): void
    {
        foreach (['http', 'https'] as $scheme) {
            foreach (['GET', 'HEAD'] as $method) {
                $response = $this->visit($scheme.'://www.homya.eu/fr/catalogue?category=froid&next=https%3A%2F%2Fexample.org', $method);
                $this->assertSame(301, $response->getStatusCode());
                $this->assertSame('https://homya.eu/fr/catalogue?category=froid&next=https%3A%2F%2Fexample.org', $response->headers->get('Location'));
                $this->assertFalse($response->headers->has('X-Robots-Tag'));
            }
        }
    }

    public function test_canonical_host_has_no_redirect_loop(): void
    {
        $response = $this->visit('https://homya.eu/fr');
        $this->assertSame(200, $response->getStatusCode());
        $this->assertFalse($response->headers->has('Location'));
    }

    public function test_admin_writes_other_hosts_and_ports_remain_closed(): void
    {
        foreach ([['https://www.homya.eu/admin', 'GET', 404],
            ['https://www.homya.eu/fr/panier', 'POST', 400],
            ['https://www.homya.eu:8443/fr', 'GET', 400],
            ['https://www.homya.eu.attacker.example/fr', 'GET', 400]] as [$url, $method, $status]) {
            $response = $this->visit($url, $method);
            $this->assertSame($status, $response->getStatusCode());
            $this->assertFalse($response->headers->has('Location'));
        }
    }

    public function test_alias_redirect_does_not_bypass_production_security_failures(): void
    {
        $this->assertSame(503, $this->visit('https://www.homya.eu/fr', failures: ['session_secure'])->getStatusCode());
    }
}
