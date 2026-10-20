"""Audit local public pages and their media; never submit forms or fetch remote hosts."""
import argparse
from concurrent.futures import ThreadPoolExecutor
from datetime import datetime, timezone
import hashlib
from html.parser import HTMLParser
from io import BytesIO
import json
from pathlib import Path
import re
from urllib.error import HTTPError, URLError
from urllib.parse import unquote, urljoin, urlsplit
from urllib.request import HTTPRedirectHandler, Request, build_opener
import xml.etree.ElementTree as ET

from PIL import Image

ROOT = Path(__file__).resolve().parents[1]
OUT = ROOT / 'output/qa-site-media-20261019'


class MediaParser(HTMLParser):
    def __init__(self):
        super().__init__()
        self.assets = set()

    def handle_starttag(self, tag, attrs):
        attrs = dict(attrs)
        if tag in ('img', 'source', 'script', 'video'):
            self.assets.update(attrs[k] for k in ('src', 'poster') if attrs.get(k))
        if tag == 'link' and set(attrs.get('rel', '').split()) & {'icon', 'apple-touch-icon', 'stylesheet', 'preload', 'modulepreload'}:
            if attrs.get('href'):
                self.assets.add(attrs['href'])
        if tag == 'meta' and attrs.get('property') == 'og:image' and attrs.get('content'):
            self.assets.add(attrs['content'])
        for key in ('srcset', 'data-gallery-srcset'):
            self.assets.update(p.strip().split()[0] for p in attrs.get(key, '').split(',') if p.strip())
        for key in ('data-gallery-src',):
            if attrs.get(key):
                self.assets.add(attrs[key])
        if tag == 'a' and re.search(r'\.(?:svg|png|webp|jpe?g|pdf)(?:[?#]|$)', attrs.get('href', ''), re.I):
            self.assets.add(attrs['href'])


class LocalRedirect(HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, headers, newurl):
        before, after = urlsplit(req.full_url), urlsplit(newurl)
        if (before.scheme, before.netloc) != (after.scheme, after.netloc):
            raise URLError('Redirect outside the local origin refused')
        return super().redirect_request(req, fp, code, msg, headers, newurl)


def fetch(url):
    try:
        with build_opener(LocalRedirect).open(Request(url, headers={'User-Agent': 'HOMYA-Local-Media-Audit/1.0'}), timeout=20) as response:
            return response.status, response.headers.get('Content-Type', ''), response.read(), response.geturl()
    except HTTPError as error:
        return error.code, error.headers.get('Content-Type', ''), error.read(), error.geturl()
    except (URLError, TimeoutError, OSError) as error:
        return 0, str(error), b'', url


def inspect(data, extension):
    if extension in {'.jpg', '.jpeg', '.png', '.webp', '.gif', '.ico'}:
        with Image.open(BytesIO(data)) as image:
            dimensions = list(image.size)
            image.verify()
        return {'dimensions': dimensions}
    if extension == '.svg':
        assert ET.fromstring(data).tag == '{http://www.w3.org/2000/svg}svg'
    if extension == '.woff2':
        assert data[:4] == b'wOF2'
    if extension == '.pdf':
        assert data.startswith(b'%PDF-')
    return {}


def variants():
    manifest = json.loads((ROOT / 'public/media/catalogue-display/manifest.json').read_text())
    seen, issues, references = {}, [], 0
    for source, entry in manifest['images'].items():
        source_path = ROOT / 'public' / source.lstrip('/')
        if not source_path.is_file() or hashlib.sha256(source_path.read_bytes()).hexdigest() != entry['sha256']:
            issues.append({'url': source, 'issue': 'source_missing_or_changed'})
        for variant in entry['variants']:
            references += 1
            url = variant['url']
            if url not in seen:
                path = ROOT / 'public' / url.lstrip('/')
                try:
                    data = path.read_bytes()
                    facts = inspect(data, '.webp')
                    seen[url] = {'bytes': len(data), 'sha256': hashlib.sha256(data).hexdigest(), **facts}
                except (OSError, ValueError, AssertionError):
                    seen[url] = {}
            facts = seen[url]
            if facts.get('sha256') != variant['sha256'] or facts.get('bytes') != variant['bytes'] or facts.get('dimensions') != [variant['width'], variant['height']]:
                issues.append({'url': url, 'issue': 'variant_missing_or_invalid'})
    return {'sources': len(manifest['images']), 'variant_references': references, 'unique_variants': len(seen), 'issues': issues}


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--base', default='http://127.0.0.1:8000')
    parser.add_argument('--offline', action='store_true')
    args = parser.parse_args()
    origin = urlsplit(args.base)
    if origin.hostname not in {'127.0.0.1', 'localhost'} or origin.scheme != 'http' or origin.username or origin.password:
        raise SystemExit('Only a local HTTP origin is permitted')
    OUT.mkdir(parents=True, exist_ok=True)
    report = {'date_utc': datetime.now(timezone.utc).isoformat(), 'responsive': variants(), 'pages': [], 'assets': [], 'external_references_not_fetched': []}
    print(json.dumps({'responsive': report['responsive']}), flush=True)
    if args.offline:
        (OUT / 'responsive-audit.json').write_text(json.dumps(report['responsive'], indent=2) + '\n')
        return
    information = ['a-propos', 'provenance', 'conseiller', 'contact', 'livraison', 'retractation', 'paiement', 'suivi', 'mentions-legales', 'cgv', 'confidentialite', 'cookies']
    slugs = ['chauffage-pack-eworta11-poeles-a-bois', 'mobilier-3292-tables-extensibles', 'froid-rb33b610esa-samsung-combines-congelateur-en-bas', 'lg-f14r15whs-lave-linge', 'philips-bar307-20-baristina', 'bronpi-nicol', 'lg-f14r33whs-lave-linge']
    index = json.loads((ROOT / 'storage/app/catalogue/index.json').read_text())
    for universe in ('firewood', 'televisions'):
        product = next((p for p in index['products'] if p['universe'] == universe and p.get('image')), None)
        if product:
            slugs.append(product['slug'])
    routes = []
    for locale in ('fr', 'en', 'de'):
        routes += [f'/{locale}' + ('/' + page if page else '') for page in ['', 'catalogue', 'promotions', 'panier', 'commande', 'retours/declaration', 'assistance', *information]]
        routes += [f'/{locale}/catalogue?universe={u}' for u in ('firewood', 'heating', 'appliances', 'furniture', 'televisions')]
        routes += [f'/{locale}/produit/{slug}' for slug in dict.fromkeys(slugs)]
    assets = {}

    def add_asset(reference, page):
        url = urljoin(args.base + page, reference)
        parts = urlsplit(url)
        if parts.scheme == 'data':
            return
        if parts.netloc != origin.netloc or parts.scheme != origin.scheme:
            if url not in report['external_references_not_fetched']:
                report['external_references_not_fetched'].append(url)
            return
        url = parts._replace(fragment='').geturl()
        assets.setdefault(url, set()).add(page)

    for position, route in enumerate(routes, 1):
        status, content_type, data, final = fetch(args.base + route)
        report['pages'].append({'path': route, 'status': status, 'final_path': urlsplit(final).path})
        if 'html' in content_type:
            html = MediaParser()
            html.feed(data.decode('utf-8'))
            for reference in html.assets:
                add_asset(reference, route)
        if position % 12 == 0:
            print(json.dumps({'pages_checked': position, 'pages_planned': len(routes), 'assets_found': len(assets)}), flush=True)

    def check_asset(item):
        url, pages = item
        status, content_type, data, _ = fetch(url)
        result = {'url': urlsplit(url).path, 'pages': sorted(pages), 'status': status, 'content_type': content_type, 'bytes': len(data), 'valid': False}
        if status == 200:
            try:
                result.update(inspect(data, Path(unquote(urlsplit(url).path)).suffix.lower()))
                result['valid'] = True
            except (OSError, ValueError, AssertionError, ET.ParseError):
                result['issue'] = 'unreadable_media'
        return result, data

    # Follow only declared CSS resources, never navigate arbitrary application links.
    checked = set()
    while pending := [(url, pages) for url, pages in assets.items() if url not in checked]:
        with ThreadPoolExecutor(max_workers=4) as pool:
            for (url, _), (result, data) in zip(pending, pool.map(check_asset, pending)):
                checked.add(url)
                report['assets'].append(result)
                if urlsplit(url).path.endswith('.css') and result['status'] == 200:
                    for reference in re.findall(r'url\([\s\"\']*([^\)\"\']+)', data.decode()):
                        add_asset(urljoin(url, reference.strip()), urlsplit(url).path)
        print(json.dumps({'assets_checked': len(checked), 'assets_found': len(assets)}), flush=True)
    report['summary'] = {'pages': len(report['pages']), 'page_statuses': {str(code): sum(p['status'] == code for p in report['pages']) for code in {p['status'] for p in report['pages']}}, 'assets': len(report['assets']), 'missing_or_invalid': [a for a in report['assets'] if not a['valid']], 'responsive_issues': len(report['responsive']['issues'])}
    (OUT / 'http-audit.json').write_text(json.dumps(report, ensure_ascii=False, indent=2) + '\n')
    print(json.dumps(report['summary'], ensure_ascii=False), flush=True)
    if report['summary']['missing_or_invalid'] or report['responsive']['issues']:
        raise SystemExit(1)


if __name__ == '__main__':
    main()
