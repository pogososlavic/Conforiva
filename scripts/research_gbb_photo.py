"""Preserve the explicitly linked German LG manufacturer sheet for exact GBB62PZFFN."""
import hashlib
import json
from pathlib import Path
from urllib.request import Request, urlopen
from pypdf import PdfReader

ROOT = Path(__file__).resolve().parents[1]
OUT = ROOT / 'storage/app/private/product-photo-alternatives-20261019/gbb'
OUT.mkdir(exist_ok=True)
url = 'https://gzhls.at/blob/ldb/7/1/2/0/477de481ee71da55be604cf07b50a1bf1833.pdf'
plan = {'reference': 'GBB62PZFFN', 'source_page': 'https://geizhals.de/lg-gbb62pzffn-a2270192.html', 'source_document': url,
        'publisher': 'LG Electronics Deutschland', 'rights_basis': 'Existing user declaration DECISION-016; no new licence',
        'warning': 'German EAN8806098343539 differs from French catalogue EAN8806098467846; candidate requires cross-market visual review before use.'}
(OUT / 'plan.json').write_text(json.dumps(plan, indent=2) + '\n')
path = OUT / 'GBB62PZFFN-DE.pdf'
if not path.exists():
    with urlopen(Request(url, headers={'User-Agent': 'HOMYA-Local-Recovery/1.0'}), timeout=30) as response:
        body = response.read(5_000_001)
        assert response.status == 200 and body.startswith(b'%PDF') and len(body) < 5_000_000
        path.write_bytes(body)
reader = PdfReader(path)
(OUT / 'GBB62PZFFN-DE.txt').write_text('\n'.join(page.extract_text() for page in reader.pages))
images = []
for page_no, page in enumerate(reader.pages, 1):
    for image_no, image in enumerate(page.images, 1):
        destination = OUT / f'p{page_no}-i{image_no}.{image.name.split(".")[-1]}'
        destination.write_bytes(image.data)
        images.append({'path': str(destination.relative_to(ROOT)), 'dimensions': list(image.image.size), 'sha256': hashlib.sha256(image.data).hexdigest()})
(OUT / 'extracted.json').write_text(json.dumps(images, indent=2) + '\n')
print(json.dumps({'document_sha256': hashlib.sha256(path.read_bytes()).hexdigest(), 'images': images}))
