"""Install reviewed exact-model photographs, preserving immutable source archives.

Default: prepare a plan only. --apply installs new content-addressed files,
updates only reviewed media override entries and adds responsive derivatives.
No network requests, business data, environment settings or SQL changes.
"""
import argparse
from datetime import datetime, timezone
import hashlib
from io import BytesIO
import json
import math
import os
from pathlib import Path

from PIL import Image

from prepare_responsive_media import encode_variants

ROOT = Path(__file__).resolve().parents[1]
RUN = ROOT / 'storage/app/private/product-photo-alternatives-20261019'
OVERLAY = ROOT / 'storage/app/catalogue/media-overrides.json'
RESPONSIVE = ROOT / 'public/media/catalogue-display/manifest.json'
Image.MAX_IMAGE_PIXELS = 64_000_000


def sha(data):
    return hashlib.sha256(data).hexdigest()


def preserve(path, data):
    if path.exists():
        if path.read_bytes() != data:
            raise ValueError('Refusing to replace a different file: ' + str(path))
        return
    path.parent.mkdir(parents=True, exist_ok=True)
    with path.open('xb') as handle:
        handle.write(data)


def reviewed_pairs():
    pairs = set()
    for path in (RUN / 'review').glob('*-review.json'):
        data = json.loads(path.read_text())
        for record in data.get('reviews', [data]):
            if record.get('status') == 'accepted_exact_model':
                pairs.add((record['reference'], record['candidate_sha256']))
    return pairs


def serialize(data):
    return (json.dumps(data, ensure_ascii=False, indent=2) + '\n').encode()


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--apply', action='store_true')
    args = parser.parse_args()
    installed_receipt = RUN / 'installation.json'
    if installed_receipt.exists():
        raise SystemExit('An installation already exists; inspect its receipt before changing it.')
    accepted = reviewed_pairs()
    candidates = []
    for name in ('laundry', 'cooling', 'bronpi', 'gsk', 'gbb'):
        path = RUN / name / 'results.json'
        if path.exists():
            candidates.extend(json.loads(path.read_text()))
    before_bytes = OVERLAY.read_bytes()
    before = json.loads(before_bytes)
    overlay = json.loads(before_bytes)
    baseline = json.loads((RUN / 'baseline.json').read_text())
    assert sha(before_bytes) == baseline['catalogue_media_overrides_sha256'], 'Overlay changed during review'
    responsive_before_bytes = RESPONSIVE.read_bytes()
    responsive = json.loads(responsive_before_bytes)
    allowed_slugs = set(json.loads((ROOT / 'storage/app/private/media-recovery-20261019/verification.json').read_text())['summary']['products_without_display_photo'])
    planned, files, seen_slugs = [], {}, set()
    for candidate in candidates:
        if candidate.get('status') not in {'ready', 'ready_pending_independent_review'}:
            continue
        slug, reference = candidate['slug'], candidate['reference']
        if slug not in allowed_slugs or slug in seen_slugs:
            raise ValueError('Unapproved or duplicate product: ' + slug)
        seen_slugs.add(slug)
        old = before['products'][slug]
        assert reference == old['reference'], 'Model reference mismatch'
        private = (ROOT / candidate['image_path']).resolve()
        assert private.is_relative_to(RUN.resolve())
        data = private.read_bytes()
        assert sha(data) == candidate['sha256']
        assert (reference, sha(data)) in accepted, 'Independent review missing: ' + reference
        assert candidate.get('visual_review') == 'verified'
        with Image.open(BytesIO(data)) as image:
            image.load()
            assert list(image.size) == [candidate['width'], candidate['height']]
            assert image.width * image.height <= 64_000_000 and len(data) <= 16_000_000
            assert image.format in {'JPEG', 'PNG', 'WEBP'}
            width, height, image_format = image.width, image.height, image.format
            transformation = {'resampled': False, 'source_dimensions': [width, height]}
            prepared = data
            if min(width, height) < 500:
                # Portrait packshots can be narrow. Add a truthful white margin,
                # never interpolate pixels to pretend a higher native resolution.
                assert max(width, height) >= 400 and min(width, height) >= 150, 'Native photograph too small'
                side = max(500, math.ceil(max(width, height) / .9))
                offset = ((side - width) // 2, (side - height) // 2)
                canvas = Image.new('RGBA', (side, side), 'white')
                canvas.alpha_composite(image.convert('RGBA'), offset)
                stream = BytesIO()
                canvas.convert('RGB').save(stream, format='PNG', optimize=True)
                prepared = stream.getvalue()
                transformation.update(background='white', offset=list(offset))
                width, height, image_format = side, side, 'PNG'
        extension = {'JPEG': 'jpg', 'PNG': 'png', 'WEBP': 'webp'}[image_format]
        url = '/media/catalogue/' + sha(prepared)[:24] + '.' + extension
        assert not (ROOT / 'public' / url.lstrip('/')).exists() or (ROOT / 'public' / url.lstrip('/')).read_bytes() == prepared
        files[ROOT / 'public' / url.lstrip('/')] = prepared
        original_path = ROOT / candidate.get('original_path', candidate['image_path'])
        original_data = original_path.read_bytes()
        with Image.open(BytesIO(original_data)) as original:
            original_extension = {'JPEG': 'jpg', 'PNG': 'png', 'WEBP': 'webp'}[original.format]
        original_name = 'media-originals/' + sha(original_data)[:24] + '.' + original_extension
        files[ROOT / 'storage/app/catalogue' / original_name] = original_data
        entry = {**old, 'reviewed_at': '2026-10-19', 'source_page': candidate['source_page'],
                 'source_image': candidate['source_image'], 'source_publisher': candidate['source_publisher'],
                 'method': candidate['method'], 'original_file': original_name,
                 'original_sha256': sha(original_data), 'transform': transformation,
                 'image': {'local_url': url, 'width': width, 'height': height, 'bytes': len(prepared), 'sha256': sha(prepared)},
                 'recovery_proof': str(private.relative_to(ROOT)), 'matching_evidence': candidate['matching_evidence']}
        if slug == 'bronpi-nicol':
            proof = json.loads((RUN / 'bronpi/video-frame-proof.json').read_text())
            entry['transform'] = {'resampled': False, 'source_dimensions': proof['native_dimensions'], 'crop': proof['crop']}
            entry['frame_timestamp_seconds'] = proof['timestamp_seconds']
        document_hash = candidate.get('source_pdf_sha256', candidate.get('source_document_sha256'))
        if document_hash:
            entry['source_document_sha256'] = document_hash
        if candidate.get('rights_note'):
            entry['rights_note'] = candidate['rights_note']
        for field in ('quality_note', 'catalogue_ean', 'document_ean', 'regional_ean_difference_disclosed'):
            if field in candidate:
                entry[field] = candidate[field]
        overlay['products'][slug] = entry
        variants = []
        for variant in encode_variants(prepared):
            encoded = variant.pop('data')
            name = variant.pop('name')
            variant['url'] = '/media/catalogue-display/' + name
            files[ROOT / 'public' / variant['url'].lstrip('/')] = encoded
            variants.append(variant)
        responsive['images'][url] = {'sha256': sha(prepared), 'width': width, 'height': height, 'bytes': len(prepared), 'variants': variants}
        planned.append({'slug': slug, 'reference': reference, 'candidate_sha256': sha(data), 'image': entry['image'],
                        'original_file': original_name, 'source_page': entry['source_page'], 'source_image': entry['source_image'],
                        'transform': entry['transform'], 'variant_count': len(variants)})
    if not planned:
        raise SystemExit('No independently accepted candidate')
    assert seen_slugs == allowed_slugs, 'All 21 exact models must be reviewed before publication'
    unchanged = [slug for slug in before['products'] if slug not in seen_slugs]
    assert all(overlay['products'][slug] == before['products'][slug] for slug in unchanged)
    plan = {'planned_at_utc': datetime.now(timezone.utc).isoformat(), 'products': planned,
            'before_overlay_sha256': sha(before_bytes), 'before_responsive_sha256': sha(responsive_before_bytes),
            'unmodified_override_products': unchanged, 'business_fields_changed': False, 'sql_written': False}
    (RUN / 'installation-plan.json').write_bytes(serialize(plan))
    print(json.dumps({'apply': args.apply, 'accepted_products': len(planned), 'files_prepared': len(files)}), flush=True)
    if not args.apply:
        return
    preserve(RUN / 'media-overrides.before.json', before_bytes)
    preserve(RUN / 'responsive-manifest.before.json', responsive_before_bytes)
    for path, encoded in files.items():
        preserve(path, encoded)
    # Publish references only after every reviewed photograph and variant exists.
    overlay_output = serialize(overlay)
    responsive_output = serialize(responsive)
    for path, encoded in ((RESPONSIVE, responsive_output), (OVERLAY, overlay_output)):
        temporary = path.with_suffix('.recovery-tmp')
        preserve(temporary, encoded)
        os.chmod(temporary, path.stat().st_mode & 0o777)
        temporary.replace(path)
    receipt = {**plan, 'installed_at_utc': datetime.now(timezone.utc).isoformat(),
               'after_overlay_sha256': sha(overlay_output), 'after_responsive_sha256': sha(responsive_output),
               'catalogue_index_unchanged': sha((ROOT / 'storage/app/catalogue/index.json').read_bytes()) == baseline['catalogue_index_sha256']}
    installed_receipt.write_bytes(serialize(receipt))
    print(json.dumps({'installed': len(planned), 'unchanged_overrides': len(unchanged), 'index_unchanged': receipt['catalogue_index_unchanged']}))


if __name__ == '__main__':
    main()
