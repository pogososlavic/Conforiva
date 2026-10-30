<?php

namespace App\Http\Controllers;

use App\Services\Catalogue;
use App\Services\Commerce\CheckoutReadiness;
use App\Services\Commerce\OrderTerms;
use App\Services\ContactChannels;

class InformationController extends Controller
{
    public const PAGES = ['a-propos', 'provenance', 'conseiller', 'contact', 'livraison', 'retractation', 'paiement', 'suivi', 'mentions-legales', 'cgv', 'confidentialite', 'cookies'];

    public function __invoke(Catalogue $catalogue, ContactChannels $channels, string $locale, string $page)
    {
        app()->setLocale($locale);
        $languageLinks = [];
        foreach (config('homya.locales') as $language) {
            $languageLinks[$language] = route('information', ['locale' => $language, 'page' => $page]);
        }
        $content = __('pages.entries.'.$page);
        if ($page === 'contact' && ($channels->phone() !== null || $channels->email() !== null)) {
            $content['intro'] = __('contact.intro');
            if ($channels->phone() !== null) {
                $content['paragraphs'][] = __('contact.instructions');
            }
            $content['pending'] = false;
        }
        $readiness = app(CheckoutReadiness::class);
        $deferredOnly = $readiness->deferredReady() && ! $readiness->bankReady();
        if ($deferredOnly) {
            if ($page === 'paiement') {
                $content['paragraphs'] = [__('commerce.deferred_notice'), __('commerce.cart_reservation_notice'), __('commerce.deferred_contact')];
            }
            if ($page === 'suivi') {
                $content['intro'] = __('commerce.last_order');
                $content['paragraphs'] = [__('commerce.deferred_tracking_notice')];
            }
        }
        $content = app(OrderTerms::class)->adapt($page, $content);
        if ($page === 'paiement' && $readiness->bankReady()) {
            $content['paragraphs'] = [__('compliance.payment_current'), __('commerce.bank_deadline_notice')];
        }
        if (config('analytics.enabled') === true && in_array($page, ['cookies', 'confidentialite'], true)) {
            foreach ($content['sections'] as &$section) {
                if ($page === 'cookies' && $section['id'] === 'utilisation') {
                    $section['paragraphs'][1] = __('analytics.description');
                }
                if ($page === 'cookies' && $section['id'] === 'choix') {
                    $section['paragraphs'][0] = __('analytics.description');
                }
                if ($page === 'confidentialite' && $section['id'] === 'donnees') {
                    $section['paragraphs'][3] = trim(preg_replace('/(?:Aucun tag Google Analytics|No Google Analytics|In der untersuchten Version)[^.]*\./u', '', $section['paragraphs'][3])).' '.__('analytics.description');
                }
                if ($page === 'confidentialite' && $section['id'] === 'destinataires') {
                    $section['paragraphs'][1] = __('compliance.hosting');
                    unset($section['paragraphs'][3]);
                }
            }
            unset($section);
            $content['sections'][] = ['id' => 'google-analytics', 'title' => __('analytics.title'),
                'paragraphs' => [__('analytics.description'), __('compliance.analytics_recipient'), __('compliance.analytics_cookies'), __('compliance.analytics_retention')]];
        }
        if ($page === 'contact' && config('dashboard.enabled')) {
            $content['paragraphs'][1] = __('support.contact_notice');
        }
        $navigationProducts = $catalogue->products();
        $catalogueReady = $navigationProducts->isNotEmpty() && ! $catalogue->sourceUnavailable();
        if ($catalogue->sourceUnavailable() || $catalogue->translationUnavailable()) {
            $navigationProducts = collect();
        }

        return view('information', compact('locale', 'page', 'content', 'languageLinks', 'catalogueReady', 'deferredOnly', 'navigationProducts'));
    }
}
