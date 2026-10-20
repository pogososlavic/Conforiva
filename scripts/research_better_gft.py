"""Preserve explicitly published GFT61SWCSE sources, privately, without bypass."""
from datetime import datetime, timezone
import argparse
import hashlib
from io import BytesIO
import json
from pathlib import Path
from urllib.request import Request, urlopen
from PIL import Image

ROOT = Path(__file__).resolve().parents[1]
RUN = ROOT / 'storage/app/private/photo-quality-upgrade-20261019'
OUT = RUN / 'gft'
SOURCES = {
    'cedi-page.html': 'https://www.cedi-electromenager.com/produits/famille-congelateur1/91088-GFT61SWCSE/',
    'cedi-origin.jpg': 'https://data.cedi-electromenager.com/data/marques/57-TfCd0qb8R9eBB6Bw4mk4Muk4pBuqsuXcfGU2tkWQvV9AVRucC1/TfCd0qb8R9eBB6Bw4mk4Muk4pBuqsuXcfGU2tkWQvV9AVRucC1_origin.jpg',
    'ubaldi-page.html': 'https://medias-assets.ubaldi.com/electromenager/froid/congelateur/lg/congelateur-armoire-lg--gft61swcse--50973578.php',
    'ubaldi-published.jpg': 'https://medias-az-img.ubaldi.com/visuels/5375798/GFT61SWCSE-b0a787ca41-43-nw.jpg',
}


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument('--more', action='store_true')
    args = parser.parse_args()
    RUN.mkdir(mode=0o700, exist_ok=True)
    OUT.mkdir(mode=0o700, exist_ok=True)
    baseline = RUN / 'baseline.json'
    if not baseline.exists():
        docs = [ROOT / 'storage/app/catalogue/media-overrides.json', ROOT / 'public/media/catalogue-display/manifest.json']
        baseline.write_text(json.dumps({str(p.relative_to(ROOT)): hashlib.sha256(p.read_bytes()).hexdigest() for p in docs}, indent=2) + '\n')
    sources = SOURCES if not args.more else {
        'but-page.html': 'https://www.but.fr/produits/8806091276001/Congelateur-armoire-LG-GFT61SWCSE-324L-Blanc.html',
        'but-p-xl.jpg': 'https://www.but.fr/fstrz/r/s/media.but.fr/images_produits/p-xl/8806091276001_F.jpg',
        'u-techno-page.html': 'https://www.u-techno.fr/p/congelateur-armoire/gft61swcse',
        'cedi-gallery-script.txt': 'https://www.cedi-electromenager.com/assets/theme/cedi/js/perso.js?vers=1772641551',
    }
    prefix = 'more-' if args.more else ''
    plan = [{'name': name, 'url': url, 'reference': 'GFT61SWCSE', 'ean': '8806091276001',
             'date': datetime.now(timezone.utc).isoformat(), 'destination': str((OUT/name).relative_to(ROOT)),
             'rights_basis': 'Manufacturer authorization declared by HOMYA user DECISION-016; source attribution to be checked; not an open licence'} for name, url in sources.items()]
    (OUT / (prefix + 'plan.json')).write_text(json.dumps(plan, indent=2) + '\n')
    results = []
    for row in plan:
        path = ROOT / row['destination']
        try:
            if path.exists():
                body = path.read_bytes()
            else:
                with urlopen(Request(row['url'], headers={'User-Agent': 'HOMYA-Photo-Recovery/1.0'}), timeout=30) as response:
                    body = response.read(15_000_001)
                    assert response.status == 200 and len(body) <= 15_000_000
                path.write_bytes(body)
                path.chmod(0o600)
            row.update(status='downloaded', bytes=len(body), sha256=hashlib.sha256(body).hexdigest())
            if path.suffix == '.jpg':
                with Image.open(BytesIO(body)) as image:
                    row.update(dimensions=list(image.size), format=image.format)
                    image.verify()
        except Exception as error:
            row.update(status='failed', error=str(error))
        results.append(row)
        print(json.dumps({k: v for k, v in row.items() if k in {'name','status','dimensions','error'}}), flush=True)
        (OUT / (prefix + 'downloads.json')).write_text(json.dumps(results, indent=2) + '\n')


if __name__ == '__main__':
    main()
