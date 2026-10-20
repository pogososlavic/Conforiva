"""Read-only audit of the reviewed supplement, including original archive integrity."""
from collections import Counter
from datetime import datetime, timezone
import hashlib
import json
from pathlib import Path

from PIL import Image
from audit_site_media import MediaParser, fetch, variants

ROOT = Path(__file__).resolve().parents[1]
RUN = ROOT / 'storage/app/private/product-photo-alternatives-20261019'


def digest(path):
    return hashlib.sha256(path.read_bytes()).hexdigest()


def main():
    receipt = json.loads((RUN / 'installation.json').read_text())
    overlay_path = ROOT / 'storage/app/catalogue/media-overrides.json'
    overlay = json.loads(overlay_path.read_text())
    before = json.loads((RUN / 'media-overrides.before.json').read_text())
    responsive_path = ROOT / 'public/media/catalogue-display/manifest.json'
    responsive = json.loads(responsive_path.read_text())
    responsive_before = json.loads((RUN / 'responsive-manifest.before.json').read_text())
    assert digest(overlay_path) == receipt['after_overlay_sha256']
    assert digest(RUN / 'media-overrides.before.json') == receipt['before_overlay_sha256']
    assert digest(responsive_path) == receipt['after_responsive_sha256']
    assert all(responsive['images'][k] == v for k, v in responsive_before['images'].items())
    changed = [s for s in before['products'] if before['products'][s] != overlay['products'][s]]
    assert set(changed) == {p['slug'] for p in receipt['products']}
    assert len(changed) == 21
    assert all(before['products'][s] == overlay['products'][s] for s in receipt['unmodified_override_products'])
    pages, assets = [], set()
    for product in receipt['products']:
        entry = overlay['products'][product['slug']]
        path = ROOT / 'public' / entry['image']['local_url'].lstrip('/')
        assert digest(path) == entry['image']['sha256']
        with Image.open(path) as image:
            image.verify()
            assert list(image.size) == [entry['image']['width'], entry['image']['height']]
            assert min(image.size) >= 500
        assert digest(ROOT / 'storage/app/catalogue' / entry['original_file']) == entry['original_sha256']
        assets.add(entry['image']['local_url'])
        assets.update(v['url'] for v in responsive['images'][entry['image']['local_url']]['variants'])
        for locale in ('fr', 'en', 'de'):
            route = f'/{locale}/produit/{product["slug"]}'
            status, content_type, data, final = fetch('http://127.0.0.1:8000' + route)
            assert status == 200 and 'text/html' in content_type, (route, status)
            html = data.decode('utf-8')
            assert entry['image']['local_url'] in html, route
            parser = MediaParser()
            parser.feed(html)
            assert entry['image']['local_url'] in parser.assets, route
            pages.append({'route': route, 'status': status, 'reviewed_image_present': True})
    http_assets = []
    for url in sorted(assets):
        status, content_type, data, final = fetch('http://127.0.0.1:8000' + url)
        assert status == 200 and content_type.startswith('image/'), (url, status)
        assert hashlib.sha256(data).hexdigest() == digest(ROOT / 'public' / url.lstrip('/'))
        http_assets.append({'url': url, 'status': status})
    responsive_audit = variants()
    assert not responsive_audit['issues']
    inventory = json.loads((ROOT / 'storage/app/private/media-recovery-20261019/cdn/inventory.json').read_text())
    # The reviewed overlay is the only authorized archive document delta.
    archive_changes = [p for p, h in inventory['catalogue_sha256'].items() if digest(ROOT / p) != h]
    assert archive_changes == ['storage/app/catalogue/media-overrides.json']
    source_images, seen = {}, {}
    for entry in inventory['images']:
        path = ROOT / 'public' / entry['local_url'].lstrip('/')
        stat = path.stat()
        identity = (stat.st_dev, stat.st_ino)
        if identity not in seen:
            assert digest(path) == entry['sha256'] and stat.st_size == entry['bytes']
            with Image.open(path) as image:
                image.verify()
                seen[identity] = min(image.size) >= 500
        source_images[entry['local_url']] = seen[identity]
    index = json.loads((ROOT / 'storage/app/catalogue/index.json').read_text())
    missing = []
    for product in index['products']:
        override = overlay['products'].get(product['slug'])
        if override and override['reference'] == product['reference'] and override['visual_review'] == 'verified':
            path = ROOT / 'public' / override['image']['local_url'].lstrip('/')
            assert digest(path) == override['image']['sha256']
            continue
        detail = json.loads((ROOT / 'storage/app/catalogue/products' / (product['slug'] + '.json')).read_text())
        images = ([product['image']] if product.get('image') else []) + detail['images']
        if not any(source_images.get(e['local_url']) for e in images):
            missing.append(product['slug'])
    assert not missing
    report = {'verified_at_utc': datetime.now(timezone.utc).isoformat(), 'installed_exact_models': len(changed),
              'products': len(index['products']), 'products_with_display_photo': len(index['products']) - len(missing),
              'products_without_display_photo': missing, 'original_cdn_images_intact': len(source_images),
              'unmodified_previous_overrides': len(receipt['unmodified_override_products']),
              'only_archive_document_changed': archive_changes, 'responsive': responsive_audit,
              'pages': pages, 'assets': http_assets, 'sql_written': False}
    (RUN / 'verification.json').write_text(json.dumps(report, ensure_ascii=False, indent=2) + '\n')
    print(json.dumps({k: v if k not in {'pages', 'assets'} else len(v) for k, v in report.items()}, ensure_ascii=False))


if __name__ == '__main__':
    main()
