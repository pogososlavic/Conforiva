"""Record GBB manufacturer candidate and explicitly disclose regional EAN difference."""
import hashlib
import json
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
OUT = ROOT / 'storage/app/private/product-photo-alternatives-20261019/gbb'
path = OUT / 'p1-i15.jpg'
data = path.read_bytes()
candidate = {
    'slug': 'lg-gbb62pzffn-combine', 'reference': 'GBB62PZFFN', 'status': 'ready_pending_independent_review',
    'image_path': str(path.relative_to(ROOT)), 'source_page': 'https://geizhals.de/lg-gbb62pzffn-a2270192.html',
    'source_image': 'https://gzhls.at/blob/ldb/7/1/2/0/477de481ee71da55be604cf07b50a1bf1833.pdf',
    'source_publisher': 'LG Electronics Deutschland, fiche constructeur hébergée par Geizhals',
    'source_pdf_sha256': hashlib.sha256((OUT / 'GBB62PZFFN-DE.pdf').read_bytes()).hexdigest(),
    'method': 'Extraction native du packshot JPEG p1image15 de la fiche LG ; fond blanc ajouté sans interpolation',
    'matching_evidence': 'Référence commerciale exacte GBB62PZFFN, Steel/Platinium, afficheur vertical, poignée intégrée et carrosserie concordent avec la photographie française archivée /media/catalogue/4b8e0009c9b9dd46a7eeecbc.jpg. La fiche allemande porte EAN8806098343539, différent du catalogue français8806098467846 : le choix repose sur le visuel et la référence identiques, aucune identité des EAN ni équivalence des performances n’est revendiquée.',
    'rights_note': 'Fiche signée LG Electronics Deutschland ; autorisation utilisateur DECISION-016 existante, contrats non examinés, aucune licence ouverte revendiquée.',
    'visual_review': 'verified', 'reviewed_at': '2026-10-19', 'width': 480, 'height': 480,
    'bytes': len(data), 'sha256': hashlib.sha256(data).hexdigest(), 'format': 'JPEG',
    'catalogue_ean': '8806098467846', 'document_ean': '8806098343539', 'regional_ean_difference_disclosed': True,
}
(OUT / 'results.json').write_text(json.dumps([candidate], ensure_ascii=False, indent=2) + '\n')
print(json.dumps({'prepared': candidate['reference'], 'awaiting_qa': True}))
