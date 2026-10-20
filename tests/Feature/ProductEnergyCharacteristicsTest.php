<?php

namespace Tests\Feature;

use App\Services\ProductEnergyCharacteristics;
use App\Services\ProductEnergyLabels;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ProductEnergyCharacteristicsTest extends TestCase
{
    private string $manifest;

    protected function setUp(): void
    {
        parent::setUp();
        $this->manifest = tempnam(sys_get_temp_dir(), 'energy-characteristics-');
        config(['product-energy-characteristics.path' => $this->manifest]);
    }

    protected function tearDown(): void
    {
        File::delete($this->manifest);
        parent::tearDown();
    }

    private function fixture(array $overrides = []): array
    {
        $entry = array_replace([
            'reference' => 'EXACT-9', 'status' => 'archived', 'category' => 'washer-dryer',
            'metrics' => [
                ['key' => 'energy_class', 'label' => 'Classe énergétique', 'value' => 'D', 'context' => 'wash-dry'],
                ['key' => 'energy_class', 'label' => 'Classe énergétique', 'value' => 'A', 'context' => 'washing'],
                ['key' => 'energy_consumption', 'value' => '260', 'unit' => 'kWh/100 cycles', 'context' => 'wash-dry'],
            ],
            'sources' => [['url' => 'https://manufacturer.example/model-exact-9', 'label' => ['fr' => 'Fabricant', 'en' => 'Manufacturer', 'de' => 'Hersteller']]],
        ], $overrides);
        file_put_contents($this->manifest, json_encode(['version' => 1, 'products' => ['exact-model' => $entry]]));

        return ['slug' => 'exact-model', 'reference' => 'EXACT-9', 'has_variants' => false];
    }

    public function test_exact_model_guard_and_no_inheritance_or_unverified_badge(): void
    {
        $product = $this->fixture();
        $service = new ProductEnergyCharacteristics;
        $profile = $service->forProduct($product, 'fr');
        $this->assertCount(3, $profile['metrics']);
        $this->assertSame('D', $profile['metrics'][0]['value']);
        $this->assertSame('lavage et séchage', $profile['metrics'][0]['context']);
        $this->assertSame('lavage seul', $profile['metrics'][1]['context']);
        $this->assertFalse($profile['metrics'][0]['verified']);
        foreach ([['reference' => 'OTHER'], ['has_variants' => true], ['variant_id' => 'v1'], ['slug' => '../exact-model']] as $change) {
            $this->assertNull($service->forProduct(array_replace($product, $change), 'fr'));
        }
        $this->assertNull($service->forProduct($product, 'es'));
        $this->assertNull((new ProductEnergyLabels)->forProduct($product, 'fr'));
        $html = view('components.product-energy-characteristics', ['profile' => $profile])->render();
        $this->assertStringContainsString('260 kWh/100 cycles', $html);
        $this->assertStringNotContainsString('energy-arrow', $html);
        $this->assertStringNotContainsString('Confirmé par le fabricant', $html);
        $this->assertStringContainsString('à confirmer auprès du fabricant', $html);
    }

    public function test_localization_and_legacy_dryer_scale_are_preserved_without_rescaling(): void
    {
        $product = $this->fixture(['category' => 'tumble-dryer', 'scale' => ['max' => 'A+++', 'min' => 'D'], 'metrics' => [
            ['key' => 'energy_class', 'value' => 'A+++', 'context' => 'legacy-dryer-before-2025'],
            ['label' => ['fr' => 'Consommation', 'en' => 'Consumption', 'de' => 'Verbrauch'], 'value' => ['fr' => 'ancienne fiche', 'en' => 'old sheet', 'de' => 'altes Datenblatt']],
            ['label' => 'Une donnée non traduite', 'value' => 'Texte uniquement français'],
        ]]);
        foreach (['en' => ['old label, before July 2025', 'old sheet'], 'de' => ['altes Label, vor Juli 2025', 'altes Datenblatt']] as $locale => [$context, $value]) {
            $profile = (new ProductEnergyCharacteristics)->forProduct($product, $locale);
            $this->assertCount(2, $profile['metrics']);
            $this->assertSame('A+++–D', $profile['scale']);
            $this->assertSame('A+++', $profile['metrics'][0]['value']);
            $this->assertSame($context, $profile['metrics'][0]['context']);
            $this->assertSame($value, $profile['metrics'][1]['value']);
        }
    }

    public function test_mixed_sources_verify_individual_values_and_escape_unsafe_source_content(): void
    {
        $product = $this->fixture(['status' => 'mixed', 'metrics' => [
            ['key' => 'energy_class', 'value' => 'D', 'verified' => true, 'source_url' => 'https://manufacturer.example/exact-9'],
            ['key' => 'power', 'value' => '2.1', 'verified' => true, 'unit' => '<script>bad()</script>', 'source_url' => 'javascript:alert(1)'],
        ], 'sources' => [['url' => 'http://unsafe.example'], ['url' => 'https://username:password@example.com'], ['url' => 'https://manufacturer.example/exact-9', 'label' => '<script>bad()</script>']]]);
        $profile = (new ProductEnergyCharacteristics)->forProduct($product, 'fr');
        $this->assertCount(1, $profile['sources']);
        $this->assertTrue($profile['metrics'][0]['verified']);
        $this->assertFalse($profile['metrics'][1]['verified']);
        $this->assertNull($profile['metrics'][1]['source_url']);
        $html = view('components.product-energy-characteristics', ['profile' => $profile])->render();
        $this->assertStringContainsString('Confirmé par le fabricant', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringNotContainsString('javascript:', $html);
    }

    public function test_missing_corrupt_or_mismatched_manifest_never_invents_energy_information(): void
    {
        $product = $this->fixture();
        file_put_contents($this->manifest, '{bad json');
        $this->assertNull((new ProductEnergyCharacteristics)->forProduct($product, 'fr'));
        file_put_contents($this->manifest, json_encode(['version' => 2, 'products' => []]));
        $this->assertNull((new ProductEnergyCharacteristics)->forProduct($product, 'fr'));
        File::delete($this->manifest);
        $this->assertNull((new ProductEnergyCharacteristics)->forProduct($product, 'fr'));
    }

    public function test_display_modes_keep_distinct_contexts_in_every_language(): void
    {
        $product = $this->fixture(['category' => 'electronic-display', 'metrics' => [
            ['key' => 'energy_class', 'value' => 'F', 'context' => 'sdr'],
            ['key' => 'energy_class', 'value' => 'G', 'context' => 'hdr'],
        ]]);
        foreach (['fr', 'en', 'de'] as $locale) {
            $profile = (new ProductEnergyCharacteristics)->forProduct($product, $locale);
            $this->assertSame('SDR', $profile['metrics'][0]['context']);
            $this->assertSame('HDR', $profile['metrics'][1]['context']);
        }
    }

    public function test_verified_document_arrow_uses_the_model_specific_scale(): void
    {
        $slug = 'lg-f14r15whs-lave-linge';
        config(['product-energy.'.$slug.'.scale' => 'A++–G', 'product-energy.'.$slug.'.class' => 'A+']);
        $product = ['slug' => $slug, 'reference' => 'F14R15WHS'];
        $energy = (new ProductEnergyLabels)->forProduct($product, 'fr');
        $this->assertSame('A++', $energy['scale_max']);
        $this->assertSame('G', $energy['scale_min']);
        $html = view('components.product-energy', ['product' => $product, 'locale' => 'fr'])->render();
        $this->assertStringContainsString('>A++</text>', $html);
        $this->assertStringContainsString('>A+</text>', $html);
        config(['product-energy.'.$slug.'.class' => 'A+++']);
        $this->assertNull((new ProductEnergyLabels)->forProduct($product, 'fr'));
    }

    public function test_only_explicit_energy_rows_move_to_a_valid_localized_profile(): void
    {
        $product = $this->fixture(['source_labels' => [
            'fr' => ['Classe énergétique', 'Consommation annuelle', '', null, ['invalid']],
            'en' => ['Energy class', 'Annual consumption'],
        ]]);
        $service = new ProductEnergyCharacteristics;
        $specifications = [
            ['label' => 'Classe énergétique', 'value' => 'A+++'],
            ['label' => 'Largeur', 'value' => '60 cm'],
            ['label' => 'Consommation annuelle', 'value' => '220 kWh'],
            ['label' => 'Classe climatique', 'value' => 'SN-T'],
        ];
        $original = $specifications;
        $profile = $service->forProduct($product, 'fr');
        $this->assertSame(['Classe énergétique', 'Consommation annuelle'], $profile['source_labels']);
        $this->assertSame([$specifications[1], $specifications[3]], $service->remainingSpecifications($specifications, $profile));
        $this->assertSame($original, $specifications);
        $this->assertSame(['Energy class', 'Annual consumption'], $service->forProduct($product, 'en')['source_labels']);
        $this->assertSame($specifications, $service->remainingSpecifications($specifications, $service->forProduct($product, 'en')));
    }

    public function test_description_class_changes_only_with_one_verified_exact_model_rating(): void
    {
        $product = $this->fixture(['metrics' => [
            ['key' => 'energy_class', 'value' => 'E', 'verified' => true, 'source_url' => 'https://manufacturer.example/exact-9'],
        ]]);
        $service = new ProductEnergyCharacteristics;
        foreach (['fr' => ['Réfrigérateur, classe D, No Frost.', 'Réfrigérateur, classe E, No Frost.'],
            'en' => ['Refrigerator, energy class D.', 'Refrigerator, energy class E.'],
            'de' => ['Kühlschrank, Energieeffizienzklasse D.', 'Kühlschrank, Energieeffizienzklasse E.']] as $locale => [$before, $after]) {
            $this->assertSame($after, $service->descriptionForProduct($product + ['description' => $before], $locale));
            $this->assertSame($before, $service->descriptionForProduct(array_replace($product, ['reference' => 'OTHER', 'description' => $before]), $locale));
        }
        $this->fixture(); // Two programme classes: never replace an ambiguous generic class claim.
        $this->assertSame('Appareil classe D.', (new ProductEnergyCharacteristics)->descriptionForProduct($product + ['description' => 'Appareil classe D.'], 'fr'));
        $this->fixture(['metrics' => [['key' => 'energy_class', 'value' => 'A']]]);
        $this->assertSame('Appareil classe D.', (new ProductEnergyCharacteristics)->descriptionForProduct($product + ['description' => 'Appareil classe D.'], 'fr'));
    }

    public function test_noise_and_climate_classes_never_inherit_the_energy_class(): void
    {
        $product = $this->fixture(['metrics' => [
            ['key' => 'energy_class', 'value' => 'C', 'verified' => true, 'source_url' => 'https://manufacturer.example/exact-9'],
        ]]);
        $service = new ProductEnergyCharacteristics;
        foreach ([
            'fr' => ['Classe énergétique D. Classe de bruit B.', 'Classe énergétique C. Classe de bruit B.'],
            'en' => ['Energy class D. Noise emission class B. Noise class A. Sound emission class B. Acoustic noise emission class A. Climate class D. Appliance class D.',
                'Energy class C. Noise emission class B. Noise class A. Sound emission class B. Acoustic noise emission class A. Climate class D. Appliance class C.'],
            'de' => ['Energieeffizienzklasse D. Geräuschemissionsklasse B.', 'Energieeffizienzklasse C. Geräuschemissionsklasse B.'],
        ] as $locale => [$before, $after]) {
            $this->assertSame($after, $service->descriptionForProduct($product + ['description' => $before], $locale));
        }
    }

    public function test_heater_description_efficiency_agrees_with_the_verified_fiche_only(): void
    {
        $product = $this->fixture(['metrics' => [
            ['key' => 'energy_class', 'value' => 'A+', 'verified' => true, 'source_url' => 'https://manufacturer.example/exact-9'],
            ['key' => 'useful_efficiency_nominal', 'value' => 86.4, 'verified' => true, 'source_url' => 'https://manufacturer.example/exact-9'],
        ]]) + ['universe' => 'heating'];
        $service = new ProductEnergyCharacteristics;
        foreach (['fr' => ['Poêle, rendement 80 %.', 'Poêle, rendement 86,4 %.'],
            'en' => ['Stove, efficiency 80%.', 'Stove, efficiency 86.4 %.'],
            'de' => ['Ofen, Wirkungsgrad 80 %.', 'Ofen, Wirkungsgrad 86,4 %.']] as $locale => [$before, $after]) {
            $this->assertSame($after, $service->descriptionForProduct($product + ['description' => $before], $locale));
            $this->assertSame($before, $service->descriptionForProduct(array_replace($product, ['reference' => 'OTHER', 'description' => $before]), $locale));
            $this->assertSame($before, $service->descriptionForProduct(array_replace($product, ['universe' => 'appliances', 'description' => $before]), $locale));
        }
        $this->fixture(['metrics' => [
            ['key' => 'energy_class', 'value' => 'A+', 'verified' => true, 'source_url' => 'https://manufacturer.example/exact-9'],
            ['key' => 'useful_efficiency_nominal', 'value' => 86.4, 'verified' => true, 'source_url' => 'javascript:bad()'],
        ]]);
        $this->assertSame('Rendement 80 %.', (new ProductEnergyCharacteristics)->descriptionForProduct($product + ['description' => 'Rendement 80 %.'], 'fr'));
    }

    public function test_unavailable_or_invalid_energy_profile_preserves_every_original_specification(): void
    {
        $product = $this->fixture(['source_labels' => ['fr' => ['Classe énergétique']]]);
        $service = new ProductEnergyCharacteristics;
        $specifications = [['label' => 'Classe énergétique', 'value' => 'A+'], ['label' => 'Largeur', 'value' => '60 cm']];
        $this->assertSame($specifications, $service->remainingSpecifications($specifications, null));
        $this->assertSame($specifications, $service->remainingSpecifications($specifications, $service->forProduct(array_replace($product, ['reference' => 'WRONG']), 'fr')));
        $this->assertSame($specifications, $service->remainingSpecifications($specifications, ['source_labels' => ['Classe énergétique']]));
        $this->assertSame($specifications, $service->remainingSpecifications($specifications, ['metrics' => [], 'source_labels' => ['Classe énergétique']]));
        $this->assertSame($specifications, $service->remainingSpecifications($specifications, ['metrics' => 'invalid', 'source_labels' => ['Classe énergétique']]));
        $this->fixture(['metrics' => [], 'source_labels' => ['fr' => ['Classe énergétique']]]);
        $this->assertSame($specifications, $service->remainingSpecifications($specifications, (new ProductEnergyCharacteristics)->forProduct($product, 'fr')));
    }
}
