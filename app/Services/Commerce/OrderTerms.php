<?php

namespace App\Services\Commerce;

use App\Services\ContactChannels;
use App\Services\LegalInformation;

final class OrderTerms
{
    public function adapt(string $page, array $content): array
    {
        $updates = __('returns.policy.'.$page);
        if (is_array($updates)) {
            foreach ($content['sections'] ?? [] as $index => $section) {
                if (isset($updates[$section['id']])) {
                    $content['sections'][$index] = array_replace($section, $updates[$section['id']]);
                }
            }
        }
        if ($page === 'cgv') {
            foreach ($content['sections'] ?? [] as $index => $section) {
                if ($section['id'] === 'perimetre' && app(CheckoutReadiness::class)->ready()) {
                    $content['sections'][$index]['paragraphs'][1] = __('compliance.scope_current');
                }
                if ($section['id'] === 'commande') {
                    $content['sections'][$index]['paragraphs'][1] .= ' '.__('commerce.payment_commitment');
                    $content['sections'][$index]['paragraphs'][5] = __('commerce.order_document_notice');
                }
            }
        }
        if ($page === 'cgv') {
            foreach ($content['sections'] as &$section) {
                if ($section['id'] === 'produits') {
                    $section['paragraphs'][2] = __('takeback.installation');
                }
                if ($section['id'] === 'livraison') {
                    $old = [
                        'fr' => 'L’installation, la mise en service et la reprise d’un ancien appareil ne sont pas présumées incluses.',
                        'en' => 'Installation, commissioning and collection of an old appliance are not assumed to be included.',
                        'de' => 'Installation, Inbetriebnahme und Altgeräterücknahme sind nicht automatisch enthalten.',
                    ];
                    $section['paragraphs'][2] = str_replace($old[app()->getLocale()] ?? '', __('takeback.installation'), $section['paragraphs'][2]);
                }
            }
            unset($section);
            $content['sections'][] = ['id' => 'reprise', 'title' => __('takeback.section'),
                'paragraphs' => [__('takeback.policy'), __('takeback.preparation'), __('takeback.other_products')]];
        }
        $legal = app(LegalInformation::class);
        $addresses = [
            ':dispatch_address' => $legal->dispatchAddress() ?? __('legal.dispatch_destination_pending'),
            ':return_address' => $legal->returnAddress() ?? __('legal.return_destination_pending'),
        ];
        // Apply after policy overrides so every displayed translation uses the same source.
        array_walk_recursive($content, function (&$value) use ($addresses): void {
            if (is_string($value)) {
                $value = strtr($value, $addresses);
            }
        });
        $readiness = app(CheckoutReadiness::class);
        if (! $readiness->deferredReady() || $readiness->cardReady() || $readiness->bankReady()) {
            return $content;
        }
        foreach ($content['sections'] ?? [] as $index => $section) {
            if ($page === 'cgv' && $section['id'] === 'perimetre') {
                $content['sections'][$index]['paragraphs'][1] = __('compliance.scope_current');
            }
            if ($page === 'confidentialite' && $section['id'] === 'donnees') {
                $content['sections'][$index]['paragraphs'][] = __('commerce.deferred_data_notice');
            }
            if ($page === 'retractation' && $section['id'] === 'preparation') {
                $content['sections'][$index]['paragraphs'][] = __('commerce.deferred_withdrawal_notice');
            }
        }

        return $content;
    }

    public function hash(): string
    {
        $terms = __('legal');
        foreach ($terms['entries'] as $page => $content) {
            $terms['entries'][$page] = $this->adapt($page, $content);
        }

        $terms['seller'] = config('legal.company');
        $terms['hosting'] = config('legal.hosting');
        $terms['returns_address'] = app(LegalInformation::class)->returnAddress();
        $terms['dispatch_address'] = app(LegalInformation::class)->dispatchAddress();
        $terms['operational_addresses'] = config('shipping');
        $terms['mediator'] = app(LegalInformation::class)->mediator();
        $terms['contact'] = [
            'phone' => app(ContactChannels::class)->phone(),
            'email' => app(ContactChannels::class)->email(),
        ];

        return hash('sha256', json_encode($terms, JSON_THROW_ON_ERROR));
    }
}
