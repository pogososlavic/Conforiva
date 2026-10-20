"""Install only the two independently reviewed photo quality upgrades, offline."""
import argparse
from datetime import datetime, timezone
from io import BytesIO
import json
import math
import os
from pathlib import Path
from PIL import Image
from install_alternative_product_photos import preserve, serialize, sha
from prepare_responsive_media import encode_variants

ROOT = Path(__file__).resolve().parents[1]
RUN = ROOT / 'storage/app/private/photo-quality-upgrade-20261019'


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--apply', action='store_true')
    args = parser.parse_args()
    assert not (RUN / 'installation.json').exists(), 'Already installed; inspect receipt'
    baseline = json.loads((RUN / 'baseline.json').read_text())
    overlay_path = ROOT / 'storage/app/catalogue/media-overrides.json'
    responsive_path = ROOT / 'public/media/catalogue-display/manifest.json'
    old_bytes, responsive_bytes = overlay_path.read_bytes(), responsive_path.read_bytes()
    assert sha(old_bytes) == baseline[str(overlay_path.relative_to(ROOT))]
    assert sha(responsive_bytes) == baseline[str(responsive_path.relative_to(ROOT))]
    overlay, responsive = json.loads(old_bytes), json.loads(responsive_bytes)
    expected = {'lg-gft61swcse-une-porte', 'lg-gfm61mccsd-une-porte'}
    files, products = {}, []
    for folder in ('gft', 'gfm'):
        candidates = json.loads((RUN / folder / 'results.json').read_text())
        assert len(candidates) == 1
        c = candidates[0]
        slug, reference = c['slug'], c['reference']
        assert slug in expected and overlay['products'][slug]['reference'] == reference
        review = json.loads((RUN / 'review' / (folder + '-review.json')).read_text())
        assert review['reference'] == reference and review['candidate_sha256'] == c['sha256']
        assert review['status'] == 'accepted_exact_model_quality_upgrade'
        assert review['improvement_object_height_factor'] >= 1.5
        path = (ROOT / c['image_path']).resolve()
        assert path.is_relative_to(RUN.resolve())
        original = path.read_bytes()
        assert sha(original) == c['sha256']
        with Image.open(BytesIO(original)) as im:
            im.load()
            assert im.format in {'JPEG', 'PNG', 'WEBP'} and list(im.size) == [c['width'], c['height']]
            assert im.width * im.height <= 64_000_000 and len(original) <= 16_000_000
            extension = {'JPEG': 'jpg', 'PNG': 'png', 'WEBP': 'webp'}[im.format]
            source_extension = extension
            prepared = original
            transform = {'resampled': False, 'source_dimensions': list(im.size)}
            width, height = im.size
            if min(im.size) < 500:
                side = math.ceil(max(im.size) / .9)
                assert side >= 800
                offset = ((side-width)//2, (side-height)//2)
                canvas = Image.new('RGBA', (side,side), 'white')
                canvas.alpha_composite(im.convert('RGBA'), offset)
                stream = BytesIO()
                canvas.convert('RGB').save(stream, format='PNG', optimize=True)
                prepared, extension, width, height = stream.getvalue(), 'png', side, side
                transform.update(background='white', offset=list(offset))
        url = '/media/catalogue/' + sha(prepared)[:24] + '.' + extension
        original_file = 'media-originals/' + sha(original)[:24] + '.' + source_extension
        files[ROOT / 'public' / url.lstrip('/')] = prepared
        files[ROOT / 'storage/app/catalogue' / original_file] = original
        entry = dict(overlay['products'][slug])
        entry.pop('source_document_sha256', None)
        for key in ('source_page', 'source_image', 'source_publisher', 'method', 'matching_evidence', 'rights_note', 'quality_note'):
            entry[key] = c[key]
        entry.update(reviewed_at='2026-10-19', original_file=original_file, original_sha256=sha(original), transform=transform,
                     recovery_proof=str(path.relative_to(ROOT)), image={'local_url':url, 'width':width,'height':height,'bytes':len(prepared),'sha256':sha(prepared)})
        overlay['products'][slug] = entry
        variants = []
        for v in encode_variants(prepared):
            data, name = v.pop('data'), v.pop('name')
            v['url'] = '/media/catalogue-display/' + name
            files[ROOT / 'public' / v['url'].lstrip('/')] = data
            variants.append(v)
        responsive['images'][url] = {**{k:entry['image'][k] for k in ('width','height','bytes','sha256')}, 'variants':variants}
        products.append({'slug':slug,'reference':reference,'candidate_sha256':sha(original),'image':entry['image'], 'original_file':original_file, 'transform':transform,'variant_count':len(variants)})
    assert {p['slug'] for p in products} == expected
    plan = {'products':products,'before_overlay_sha256':sha(old_bytes),'before_responsive_sha256':sha(responsive_bytes),'sql_written':False,'business_data_changed':False}
    (RUN / 'installation-plan.json').write_bytes(serialize(plan))
    if args.apply:
        preserve(RUN / 'media-overrides.before.json', old_bytes)
        preserve(RUN / 'responsive-manifest.before.json', responsive_bytes)
        for path, data in files.items():
            preserve(path, data)
        for path, data in ((responsive_path,serialize(responsive)),(overlay_path,serialize(overlay))):
            temporary = path.with_suffix('.quality-tmp')
            preserve(temporary, data)
            os.chmod(temporary, path.stat().st_mode & 0o777)
            temporary.replace(path)
        receipt = {**plan,'installed_at_utc':datetime.now(timezone.utc).isoformat(),'after_overlay_sha256':sha(overlay_path.read_bytes()),'after_responsive_sha256':sha(responsive_path.read_bytes())}
        (RUN / 'installation.json').write_bytes(serialize(receipt))
    print(json.dumps({'apply':args.apply,'reviewed_products':len(products),'files':len(files)}))


if __name__ == '__main__':
    main()
