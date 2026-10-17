<?php

namespace Tests\Feature;

use App\Http\Controllers\InformationController;
use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class InformationPagesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Explicit unconfigured-contact fixture, independent of public HOMYA defaults.
        config(['contact.phone' => null, 'contact.email' => null]);
    }

    public function test_information_is_localized_without_fake_forms_or_source_identity(): void
    {
        DB::shouldReceive('connection')->never();
        foreach (['fr', 'en', 'de'] as $locale) {
            foreach (InformationController::PAGES as $page) {
                $response = $this->get('/'.$locale.'/'.$page)->assertOk()
                    ->assertSee(__('pages.entries.'.$page.'.title', [], $locale))
                    ->assertDontSee('damdam', false)->assertDontSee('pages.entries.', false);
                $document = new DOMDocument;
                @$document->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
                $xpath = new DOMXPath($document);
                $this->assertSame(1, $xpath->query('//h1')->length);
                $this->assertSame($locale, $document->documentElement->getAttribute('lang'));
                $this->assertSame($page === 'suivi' ? 1 : 0, $xpath->query('//form[not(@role="search")]')->length);
                foreach ($xpath->query('//nav[contains(@class,"languages")]/a') as $link) {
                    $this->assertStringEndsWith('/'.$page, $link->getAttribute('href'));
                }
            }
        }
    }

    public function test_about_page_exposes_company_identity_and_primary_navigation_in_all_languages(): void
    {
        DB::shouldReceive('connection')->never();
        foreach (['fr', 'en', 'de'] as $locale) {
            $response = $this->get('/'.$locale.'/a-propos')->assertOk();
            foreach (config('legal.company') as $value) {
                $response->assertSee($value);
            }
            $document = new DOMDocument;
            @$document->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
            $xpath = new DOMXPath($document);
            $links = $xpath->query('//nav[@id="primary-navigation"]//a[@aria-current="page"]');
            $this->assertSame(1, $links->length);
            $this->assertSame(__('pages.about_navigation', [], $locale), $links->item(0)->textContent);
            $this->assertStringEndsWith('/'.$locale.'/a-propos', $links->item(0)->getAttribute('href'));
            $this->assertGreaterThanOrEqual(4, $xpath->query('//main//article//h2')->length);
            foreach (['contact', 'mentions-legales', 'livraison', 'paiement', 'retractation', 'cgv'] as $page) {
                $this->assertGreaterThan(0, $xpath->query('//main//article//a[contains(@href,"/'.$locale.'/'.$page.'")]')->length);
            }
        }
    }

    public function test_information_remains_available_during_catalogue_outage_and_unknown_pages_are_rejected(): void
    {
        config(['catalogue.archive_path' => sys_get_temp_dir().'/homya-missing-'.uniqid().'.json']);
        foreach (['fr', 'en', 'de'] as $locale) {
            $this->get('/'.$locale.'/contact')->assertOk()->assertSee('disabled', false)
                ->assertDontSee(__('pages.pending', [], $locale));
            $this->get('/'.$locale.'/unknown-page')->assertNotFound();
        }
        $this->get('/es/contact')->assertNotFound();
    }
}
