"""Record reviewed GSK gallery candidate; no network or public changes."""
import json
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
RUN = ROOT / 'storage/app/private/product-photo-alternatives-20261019'
entry = json.loads((RUN / 'gsk/downloads.json').read_text())[0]
overrides = json.loads((ROOT / 'storage/app/catalogue/media-overrides.json').read_text())['products']
slug = next(slug for slug, value in overrides.items() if value['reference'] == 'GSK6876SC')
candidate = {
    'slug': slug, 'reference': 'GSK6876SC', 'status': 'ready',
    'image_path': entry['path'], 'source_image': entry['url'],
    'source_page': 'https://guadeloupe.darty-dom.com/p/lg-gsk6876sc',
    'source_publisher': 'LG, visuel catalogue publié par Darty DOM sur sa fiche exacte GSK6876SC',
    'method': 'Photographie catalogue native publiée sur la fiche modèle/EAN exacte ; aucune interpolation',
    'matching_evidence': 'GSK6876SC et EAN8806098739011 liés à cette galerie dans le JSON-LD sauvegardé. Inox, InstaView à droite, distributeur à gauche et poignées chromées concordent. Vues ouvertes219045 et219043 confirment deux portes pleine hauteur et Door-in-Door.',
    'rights_note': 'Visuel catalogue attribué au fabricant par sa présentation ; autorisation utilisateur DECISION-016 conservée, contrats non examinés, aucune licence ouverte revendiquée.',
    'visual_review': 'verified', 'reviewed_at': '2026-10-19',
    'width': entry['dimensions'][0], 'height': entry['dimensions'][1],
    'bytes': entry['bytes'], 'sha256': entry['sha256'], 'format': entry['format'],
}
(RUN / 'gsk/results.json').write_text(json.dumps([candidate], ensure_ascii=False, indent=2) + '\n')
plan_path = RUN / 'gsk/plan.json'
plan = json.loads(plan_path.read_text())
plan['correction'] = 'Le soupçon initial de quatre portes est retiré après examen des vues ouvertes219045/219043. La photographie archivée n’est pas rejetée pour sa carrosserie ; le lien SAV vers GSS6876SC reste écarté.'
plan_path.write_text(json.dumps(plan, ensure_ascii=False, indent=2) + '\n')
print(json.dumps({'prepared': slug, 'public_changed': False}))
