"""Read-only HTTP and responsive verification of the two quality upgrades."""
from datetime import datetime, timezone
import hashlib
import json
from pathlib import Path
from audit_site_media import MediaParser, fetch, variants

ROOT = Path(__file__).resolve().parents[1]
RUN = ROOT / 'storage/app/private/photo-quality-upgrade-20261019'


def main():
    receipt = json.loads((RUN / 'installation.json').read_text())
    overlay_path = ROOT / 'storage/app/catalogue/media-overrides.json'
    manifest_path = ROOT / 'public/media/catalogue-display/manifest.json'
    digest = lambda path: hashlib.sha256(path.read_bytes()).hexdigest()
    assert digest(overlay_path) == receipt['after_overlay_sha256']
    assert digest(manifest_path) == receipt['after_responsive_sha256']
    manifest = json.loads(manifest_path.read_text())['images']
    pages, urls = [], set()
    for p in receipt['products']:
        url = p['image']['local_url']
        urls.add(url)
        urls.update(v['url'] for v in manifest[url]['variants'])
        for locale in ('fr','en','de'):
            route = f'/{locale}/produit/{p["slug"]}'
            status, content_type, body, final = fetch('http://127.0.0.1:8000'+route)
            assert status == 200 and 'text/html' in content_type, (route,status)
            parser = MediaParser()
            parser.feed(body.decode('utf-8'))
            assert url in parser.assets
            pages.append({'route':route,'status':status,'new_photo_present':True})
    assets = []
    for url in sorted(urls):
        status, content_type, body, final = fetch('http://127.0.0.1:8000'+url)
        assert status == 200 and content_type.startswith('image/')
        assert hashlib.sha256(body).hexdigest() == digest(ROOT/'public'/url.lstrip('/'))
        assets.append({'url':url,'status':status,'bytes':len(body)})
    responsive = variants()
    assert not responsive['issues']
    report = {'verified_at_utc':datetime.now(timezone.utc).isoformat(),'pages':pages,'assets':assets,'responsive':responsive,'sql_written':False}
    (RUN/'http-verification.json').write_text(json.dumps(report,indent=2)+'\n')
    print(json.dumps({'pages':len(pages),'assets':len(assets),'responsive':responsive,'sql_written':False}))


if __name__ == '__main__':
    main()
