"""Read-only local audit of recovered catalogue photos; no network or SQL."""
from collections import Counter
from datetime import datetime, timezone
import hashlib
import json
from pathlib import Path

from PIL import Image

ROOT = Path(__file__).resolve().parents[1]
RUN = ROOT / 'storage/app/private/media-recovery-20261019'


def main():
    inventory = json.loads((RUN / 'cdn/inventory.json').read_text())
    results = {}
    content = {}

    def inspect(url, expected):
        if url in results:
            return results[url]
        path = ROOT / 'public' / url.lstrip('/')
        result = {'local_url': url, 'status': 'missing'}
        if path.is_file() and not path.is_symlink():
            stat = path.stat()
            identity = (stat.st_dev, stat.st_ino, stat.st_size, stat.st_mtime_ns)
            if identity not in content:
                facts = {'bytes': stat.st_size, 'sha256': hashlib.sha256(path.read_bytes()).hexdigest()}
                try:
                    with Image.open(path) as image:
                        facts.update(width=image.width, height=image.height, format=image.format)
                        image.verify()
                    facts['readable'] = True
                except Exception:
                    facts['readable'] = False
                content[identity] = facts
            result.update(content[identity])
            result['status'] = 'verified' if (result['bytes'] == expected['bytes'] and result['sha256'] == expected['sha256'] and result['readable']) else 'different_or_invalid'
            result['display_eligible'] = (result['status'] == 'verified' and result['width'] >= 500 and result['height'] >= 500
                                          and result['width'] * result['height'] <= 64_000_000 and result['bytes'] <= 16_000_000)
        results[url] = result
        return result

    for entry in inventory['images']:
        inspect(entry['local_url'], entry)
    cdn_results = [results[e['local_url']] for e in inventory['images']]
    catalogue = ROOT / 'storage/app/catalogue'
    overrides = json.loads((catalogue / 'media-overrides.json').read_text())['products']
    override_results = {}
    for slug, entry in overrides.items():
        override_results[slug] = inspect(entry['image']['local_url'], entry['image'])
    products_without_display_photo = []
    index = json.loads((catalogue / 'index.json').read_text())
    for entry in index['products']:
        slug = entry['slug']
        override = override_results.get(slug, {})
        if override.get('display_eligible'):
            continue
        detail = json.loads((catalogue / 'products' / (slug + '.json')).read_text())
        images = ([entry['image']] if entry.get('image') else []) + detail['images']
        if not any(results.get(image['local_url'], {}).get('display_eligible') for image in images):
            products_without_display_photo.append(slug)
    changed = [path for path, digest in inventory['catalogue_sha256'].items() if hashlib.sha256((ROOT / path).read_bytes()).hexdigest() != digest]
    summary = {'verified_at_utc': datetime.now(timezone.utc).isoformat(), 'expected_cdn_images': len(cdn_results),
               'cdn_statuses': dict(Counter(r['status'] for r in cdn_results)),
               'cdn_verified_bytes': sum(r.get('bytes', 0) for r in cdn_results if r['status'] == 'verified'),
               'manufacturer_products': len(overrides), 'manufacturer_statuses': dict(Counter(r['status'] for r in override_results.values())),
               'products': len(index['products']), 'products_with_display_photo': len(index['products']) - len(products_without_display_photo),
               'products_without_display_photo': products_without_display_photo, 'catalogue_documents_changed': changed,
               'database_written': False, 'media_transformed_by_this_audit': False}
    summary['cdn_integrity_complete'] = summary['cdn_statuses'].get('verified', 0) == len(cdn_results)
    summary['manufacturer_recovery_complete'] = all(r['status'] == 'verified' for r in override_results.values())
    output = RUN / 'verification.json'
    output.write_text(json.dumps({'summary': summary, 'cdn_unavailable': [r for r in cdn_results if r['status'] != 'verified'],
                                 'manufacturer_unavailable': {slug: r for slug, r in override_results.items() if r['status'] != 'verified'}}, ensure_ascii=False, indent=2))
    output.chmod(0o600)
    console = {key: value for key, value in summary.items() if key != 'products_without_display_photo'}
    console['products_without_display_photo_count'] = len(products_without_display_photo)
    print(json.dumps(console, ensure_ascii=False))
    if changed or not summary['cdn_integrity_complete']:
        raise SystemExit(1)


if __name__ == '__main__':
    main()
