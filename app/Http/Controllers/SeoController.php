<?php

namespace App\Http\Controllers;

use App\Services\Catalogue;
use App\Services\Seo;
use Illuminate\Http\Request;
use XMLWriter;

final class SeoController extends Controller
{
    public function robots(Request $request, Seo $seo)
    {
        $body = $seo->onPublicOrigin($request)
            ? "User-agent: *\nAllow: /\n\nSitemap: ".$seo->origin()."/sitemap.xml\n"
            : "User-agent: *\nDisallow: /\n";

        // Private URLs are protected by access control and noindex headers. Crawling
        // remains possible so Google can observe those headers, not a robots block.
        return response($body)->header('Content-Type', 'text/plain; charset=UTF-8')->header('Cache-Control', 'no-cache');
    }

    public function index(Request $request, Catalogue $catalogue, Seo $seo)
    {
        abort_unless($seo->onConfiguredPublicOrigin($request), 404);
        $xml = $this->xml('sitemapindex');
        foreach (config('homya.locales') as $locale) {
            app()->setLocale($locale);
            $products = $catalogue->products();
            abort_if($catalogue->sourceUnavailable() || $catalogue->translationUnavailable() || $products->isEmpty(), 503);
            $xml->startElement('sitemap');
            $xml->writeElement('loc', $seo->url('seo.pages', ['locale' => $locale]));
            $xml->endElement();
            for ($chunk = 1; $chunk <= (int) ceil($products->count() / config('seo.sitemap_chunk_size')); $chunk++) {
                $xml->startElement('sitemap');
                $xml->writeElement('loc', $seo->url('seo.products', compact('locale', 'chunk')));
                $xml->endElement();
            }
        }

        return $this->response($xml);
    }

    public function pages(Request $request, Catalogue $catalogue, Seo $seo, string $locale)
    {
        abort_unless($seo->onConfiguredPublicOrigin($request), 404);
        app()->setLocale($locale);
        $products = $catalogue->products();
        abort_if($catalogue->sourceUnavailable() || $catalogue->translationUnavailable() || $products->isEmpty(), 503);
        $urls = [$seo->url('home', compact('locale'))];
        $urls = array_merge($urls, $this->listingUrls($seo, 'catalogue', compact('locale'), $products->count()));
        $promotions = $products->filter(fn ($product) => $product['promotion'] !== null);
        if ($promotions->isNotEmpty()) {
            $urls = array_merge($urls, $this->listingUrls($seo, 'promotions', compact('locale'), $promotions->count()));
        }
        foreach ($products->filter(fn ($product) => ! empty($product['universe']))->groupBy('universe') as $universe => $items) {
            $urls = array_merge($urls, $this->listingUrls($seo, 'catalogue', compact('locale', 'universe'), $items->count()));
        }
        foreach ($products->filter(fn ($product) => ! empty($product['category']['slug']))->groupBy('category.slug') as $category => $items) {
            $urls = array_merge($urls, $this->listingUrls($seo, 'catalogue', compact('locale', 'category'), $items->count()));
        }
        // Use the same eligibility as each page's canonical and robots metadata.
        foreach (InformationController::PAGES as $page) {
            if ($seo->informationIndexable($page)) {
                $urls[] = $seo->url('information', compact('locale', 'page'));
            }
        }

        return $this->urls($urls);
    }

    private function listingUrls(Seo $seo, string $route, array $parameters, int $count): array
    {
        $urls = [];
        $lastPage = (int) ceil($count / max(1, (int) config('homya.catalogue_page_size', 24)));
        for ($page = 1; $page <= $lastPage; $page++) {
            // Page one has no parameter; subsequent pages have their own canonical.
            $urls[] = $seo->url($route, $parameters + ($page > 1 ? ['page' => $page] : []));
        }

        return $urls;
    }

    public function products(Request $request, Catalogue $catalogue, Seo $seo, string $locale, string $chunk)
    {
        abort_unless($seo->onConfiguredPublicOrigin($request), 404);
        app()->setLocale($locale);
        $products = $catalogue->products();
        abort_if($catalogue->sourceUnavailable() || $catalogue->translationUnavailable() || $products->isEmpty(), 503);
        $items = $products->forPage((int) $chunk, config('seo.sitemap_chunk_size'));
        abort_if($items->isEmpty(), 404);

        $images = [];
        $urls = [];
        foreach ($items as $product) {
            $url = $seo->url('product', ['locale' => $locale, 'slug' => $product['slug']]);
            $urls[] = $url;
            // The catalogue projection already validates the displayed primary
            // image. Do not advertise unreviewed source galleries or remote URLs.
            $path = $product['image']['local_url'] ?? null;
            if (is_string($path) && str_starts_with($path, '/media/') && ! str_contains($path, '..')) {
                $images[$url] = $seo->origin().$path;
            }
        }

        return $this->urls($urls, $images);
    }

    private function xml(string $root): XMLWriter
    {
        $xml = new XMLWriter;
        $xml->openMemory();
        $xml->startDocument('1.0', 'UTF-8');
        $xml->startElementNS(null, $root, 'http://www.sitemaps.org/schemas/sitemap/0.9');

        return $xml;
    }

    private function urls(array $urls, array $images = [])
    {
        $xml = $this->xml('urlset');
        foreach (array_unique($urls) as $url) {
            $xml->startElement('url');
            $xml->writeElement('loc', $url);
            if (isset($images[$url])) {
                $xml->startElementNS('image', 'image', 'http://www.google.com/schemas/sitemap-image/1.1');
                $xml->writeElement('image:loc', $images[$url]);
                $xml->endElement();
            }
            $xml->endElement();
        }

        return $this->response($xml);
    }

    private function response(XMLWriter $xml)
    {
        $xml->endElement();
        $xml->endDocument();

        return response($xml->outputMemory())->header('Content-Type', 'application/xml; charset=UTF-8')->header('Cache-Control', 'no-cache');
    }
}
