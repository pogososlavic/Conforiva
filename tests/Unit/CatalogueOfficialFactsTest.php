<?php

namespace Tests\Unit;

use App\Services\CatalogueOfficialFacts;
use App\Services\MerchantProducts;
use PHPUnit\Framework\TestCase;

class CatalogueOfficialFactsTest extends TestCase
{
    private function rules(): array
    {
        return array_filter(json_decode(file_get_contents(__DIR__.'/../../resources/data/official-product-facts.json'), true, flags: JSON_THROW_ON_ERROR)['products'],
            static fn (array $rule): bool => ! isset($rule['import_batch']));
    }

    private function source(string $slug): array
    {
        return json_decode(file_get_contents(__DIR__.'/../../storage/app/catalogue/products/'.$slug.'.json'), true, flags: JSON_THROW_ON_ERROR);
    }

    public function test_every_reviewed_profile_matches_its_full_archive_and_only_completes_verified_fields(): void
    {
        $identifiers = [];
        $completed = 0;
        foreach ($this->rules() as $slug => $rule) {
            $source = $this->source($slug);
            $this->assertSame($rule['source_sha256'], CatalogueOfficialFacts::sourceHash($source), $slug);
            $this->assertFalse($source['has_variants']);
            $this->assertSame([], $source['variants']);
            foreach (['fr', 'en', 'de'] as $locale) {
                $product = $source;
                $product['issues'] = array_merge($source['issues'], ['unrelated_review']);
                $product['specification_reviews'] = [];
                $original = $source;
                $result = CatalogueOfficialFacts::apply($source, $product, $locale);
                foreach ($rule['specifications'][$locale] as $fact) {
                    $this->assertContains($fact, $result['specifications'], $slug.' '.$locale);
                }
                $this->assertSame($rule['descriptions'][$locale] ?? $source['description'], $result['description']);
                $this->assertSame($source['price_cents'], $result['price_cents']);
                $this->assertSame($source['images'], $result['images']);
                $this->assertContains('unrelated_review', $result['issues']);
                $this->assertSame($original, $source);
                if ($rule['specification_mode'] === 'complete_empty') {
                    $this->assertSame([], $source['specifications']);
                    $this->assertSame($rule['specifications'][$locale], $result['specifications']);
                    $this->assertNotContains('missing_specifications', $result['issues']);
                    $this->assertArrayNotHasKey('manufacturer', $result);
                } else {
                    $this->assertTrue($result['manufacturer']['verified']);
                    $this->assertSame($rule['manufacturer']['gtin'] ?? null, $result['manufacturer']['gtin'] ?? null);
                    $this->assertSame($rule['manufacturer']['mpn'] ?? null, $result['manufacturer']['mpn'] ?? null);
                    $this->assertSame($product['issues'], $result['issues']);
                }
            }
            if (isset($rule['manufacturer'])) {
                $gtin = $rule['manufacturer']['gtin'] ?? null;
                if ($gtin !== null) {
                    $this->assertTrue(MerchantProducts::validGtin($gtin));
                    $this->assertNotContains($gtin, $identifiers);
                    $identifiers[] = $gtin;
                } else {
                    $this->assertStringStartsWith('BAR', $rule['manufacturer']['mpn']);
                    $this->assertMatchesRegularExpression('/\ABAR\d{3}\/\d{2}\z/', $rule['manufacturer']['mpn']);
                }
                $this->assertStringStartsWith('https://', $rule['manufacturer']['source_url']);
            } else {
                $completed++;
            }
        }
        $this->assertCount(182, $identifiers);
        $this->assertSame(9, $completed);
    }

    public function test_any_archive_change_or_variant_invalidates_the_verified_facts(): void
    {
        foreach (['lg-gtbv20pygkd-deux-portes', 'cuisiniere-a-bois-avec-four-bronpi-monza-14-kw'] as $slug) {
            $source = $this->source($slug);
            foreach ([['reference' => 'OTHER'], ['name' => 'Edited title'], ['price_cents' => $source['price_cents'] + 1],
                ['source_url' => 'https://example.com/other'], ['images' => []], ['has_variants' => true],
                ['variants' => [['sku' => 'OTHER']]], ['specifications' => [['label' => 'New field', 'value' => 'Unknown']]]] as $change) {
                $changed = array_replace($source, $change);
                $this->assertSame($changed, CatalogueOfficialFacts::apply($changed, $changed, 'fr'));
            }
            $this->assertSame($source, CatalogueOfficialFacts::apply($source, $source, 'es'));
            $compact = $source;
            unset($compact['description'], $compact['specifications']);
            $this->assertSame($compact, CatalogueOfficialFacts::apply($compact, $compact, 'fr'));
        }
    }

    public function test_only_reviewed_identifier_labels_are_replaced_and_other_conflicts_stay_open(): void
    {
        $slug = 'lg-rh9v50wh-seche-linge';
        $source = $this->source($slug);
        $rule = $this->rules()[$slug];
        foreach (['fr', 'en', 'de'] as $locale) {
            $product = $source;
            $product['specifications'] = [
                ['label' => $rule['remove_labels'][$locale][0], 'value' => '880609138660'],
                ['label' => 'Other field', 'value' => 'Awaiting verification'],
            ];
            $product['specification_reviews'] = [
                ['label' => 'Other field', 'values' => ['1', '2']],
            ];
            $product['issues'] = ['conflicting_specification', 'unrelated_review'];
            $result = CatalogueOfficialFacts::apply($source, $product, $locale);
            $this->assertContains($product['specifications'][1], $result['specifications']);
            $this->assertNotContains($product['specifications'][0], $result['specifications']);
            $this->assertSame($product['specification_reviews'], $result['specification_reviews']);
            $this->assertSame($product['issues'], $result['issues']);
        }
        $heat = $this->source('cuisiniere-a-bois-avec-four-bronpi-monza-14-kw');
        $presented = $heat;
        $presented['specifications'] = [['label' => 'Existing edited fact', 'value' => 'Keep']];
        $this->assertSame($presented, CatalogueOfficialFacts::apply($heat, $presented, 'fr'));
    }
}
