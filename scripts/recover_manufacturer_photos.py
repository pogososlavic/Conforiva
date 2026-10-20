"""Recover only reviewed media-overrides sources, preserving recorded hashes.

No catalogue/DB changes. Download once per URL; never replace differing files.
Default is a manifest-only plan; --apply fetches and restores exact matches.
"""
import argparse
from datetime import datetime, timezone
from io import BytesIO
import hashlib
import json
import math
from pathlib import Path
import time
import urllib.error
import urllib.parse
import urllib.request

from PIL import Image

ROOT = Path(__file__).resolve().parents[1]
OVERLAY = ROOT / 'storage/app/catalogue/media-overrides.json'
REPORT = ROOT / 'storage/app/private/media-recovery-20261019/manufacturer'
Image.MAX_IMAGE_PIXELS = 64_000_000


def digest(data):
    return hashlib.sha256(data).hexdigest()


def preserve(path, data):
    if path.exists():
        if path.read_bytes() != data:
            raise ValueError('existing_file_differs: ' + str(path.relative_to(ROOT)))
        return 'existing'
    path.parent.mkdir(parents=True, exist_ok=True)
    with path.open('xb') as out:
        out.write(data)
    return 'restored'


class SafeRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, headers, newurl):
        before, after = urllib.parse.urlsplit(req.full_url), urllib.parse.urlsplit(newurl)
        if after.scheme != 'https' or after.hostname != before.hostname:
            raise ValueError('redirect_needs_review: ' + newurl)
        return super().redirect_request(req, fp, code, msg, headers, newurl)


def download(url):
    cache = REPORT / 'sources' / (digest(url.encode()) + '.bin')
    if cache.exists():
        return cache.read_bytes(), {'cache': True, 'http_status': 200}
    parsed = urllib.parse.urlsplit(url)
    if parsed.scheme != 'https' or parsed.username or parsed.password:
        raise ValueError('invalid_source_url')
    limit = 100_000_000 if parsed.path.endswith('.mp4') else 32_000_000
    req = urllib.request.Request(url, headers={'User-Agent': 'HOMYA-local-media-recovery/1.0'})
    with urllib.request.build_opener(SafeRedirect).open(req, timeout=30) as response:
        mime = response.headers.get_content_type()
        if not (mime.startswith('image/') or mime in ('application/pdf', 'video/mp4', 'application/octet-stream')):
            raise ValueError('unexpected_content_type: ' + mime)
        if int(response.headers.get('Content-Length', '0')) > limit:
            raise ValueError('source_too_large')
        chunks, received, deadline = [], 0, time.monotonic() + 120
        while True:
            if time.monotonic() > deadline:
                raise ValueError('download_total_timeout_120s')
            chunk = response.read(65_536)
            if not chunk:
                break
            received += len(chunk)
            if received > limit:
                raise ValueError('source_too_large')
            chunks.append(chunk)
        data = b''.join(chunks)
        meta = {'http_status': response.status, 'content_type': mime, 'final_url': response.url}
    preserve(cache, data)
    return data, meta


def extract_original(entry, source):
    expected = entry['original_sha256']
    if digest(source) == expected:
        return source
    if source.startswith(b'%PDF'):
        from pypdf import PdfReader
        reader = PdfReader(BytesIO(source))
        for page in reader.pages:
            for picture in page.images:
                if digest(picture.data) == expected:
                    return picture.data
                # JPEG2000 source was converted to native PNG; no resampling.
                for optimized in (False, True):
                    out = BytesIO()
                    try:
                        picture.image.save(out, format='PNG', optimize=optimized)
                    except OSError:
                        continue
                    candidate = out.getvalue()
                    if digest(candidate) == expected:
                        return candidate
        raise ValueError('pdf_original_hash_not_reproduced')
    if entry['source_image'].endswith('.mp4'):
        raise ValueError('video_frame_extraction_required')
    raise ValueError('source_sha256_changed: ' + digest(source))


def transform(entry, data):
    with Image.open(BytesIO(data)) as opened:
        if opened.format not in {'JPEG', 'PNG', 'WEBP', 'GIF'} or getattr(opened, 'n_frames', 1) != 1:
            raise ValueError('unsupported_image')
        opened.load()
        im = opened.copy()
    spec = entry['transform']
    if list(im.size) != spec['source_dimensions']:
        raise ValueError('original_dimensions_changed')
    if spec.get('resampled'):
        raise ValueError('resampling_not_supported')
    if spec.get('crop'):
        im = im.crop(spec['crop'])
    if spec.get('background') == 'white':
        side = max(500, math.ceil(max(im.size) / 0.9))
        offset = ((side - im.width) // 2, (side - im.height) // 2)
        if list(offset) != spec['offset']:
            raise ValueError('background_offset_changed')
        canvas = Image.new('RGBA', (side, side), 'white')
        canvas.alpha_composite(im.convert('RGBA'), dest=offset)
        im = canvas.convert('RGB')
    if spec.get('crop') or spec.get('background'):
        out = BytesIO()
        im.save(out, format='PNG', optimize=True)
        output = out.getvalue()
    else:
        output = data
    if im.size != (entry['image']['width'], entry['image']['height']):
        raise ValueError('output_dimensions_changed')
    return output


def main():
    args = argparse.ArgumentParser(description=__doc__)
    args.add_argument('--apply', action='store_true')
    options = args.parse_args()
    overlay_bytes = OVERLAY.read_bytes()
    entries = json.loads(overlay_bytes)['products']
    REPORT.mkdir(parents=True, exist_ok=True)
    manifest = {'planned_at': datetime.now(timezone.utc).isoformat(), 'overlay_sha256': digest(overlay_bytes),
                'entries': list(entries.values())}
    preserve(REPORT / ('plan-' + digest(overlay_bytes)[:24] + '.json'),
             json.dumps(manifest, ensure_ascii=False, indent=2).encode()) if not (REPORT / ('plan-' + digest(overlay_bytes)[:24] + '.json')).exists() else None
    if not options.apply:
        print(json.dumps({'products': len(entries), 'unique_sources': len({e['source_image'] for e in entries.values()})}))
        return
    fetched = {}
    outcomes = []
    for slug, entry in entries.items():
        record = {'slug': slug, 'reference': entry['reference'], 'source_url': entry['source_image'],
                  'rights_status': entry.get('rights_status'), 'rights_scope': entry.get('rights_scope'),
                  'expected_original_sha256': entry['original_sha256'], 'expected_output_sha256': entry['image']['sha256']}
        try:
            local = ROOT / 'public' / entry['image']['local_url'].lstrip('/')
            original = ROOT / 'storage/app/catalogue' / entry['original_file']
            if original.exists() and digest(original.read_bytes()) == entry['original_sha256']:
                data = original.read_bytes()
            else:
                url = entry['source_image']
                if url not in fetched:
                    try:
                        fetched[url] = download(url)
                    except Exception as exc:
                        fetched[url] = exc
                if isinstance(fetched[url], Exception):
                    raise fetched[url]
                source, meta = fetched[url]
                record.update(meta, source_sha256=digest(source), source_bytes=len(source))
                data = extract_original(entry, source)
                record['original_status'] = preserve(original, data)
            output = transform(entry, data)
            record.update(original_sha256=digest(data), output_sha256=digest(output), output_bytes=len(output),
                          local_url=entry['image']['local_url'])
            if digest(output) != entry['image']['sha256'] or len(output) != entry['image']['bytes']:
                preserve(REPORT / 'pending' / (digest(output) + '.png'), output)
                raise ValueError('output_encoding_differs_original_preserved')
            record['output_status'] = preserve(local, output)
            record['status'] = 'ok'
        except Exception as exc:
            record.update(status='blocked', error=str(exc))
        outcomes.append(record)
        print(json.dumps({'slug': slug, 'status': record['status'], 'error': record.get('error')}, ensure_ascii=False), flush=True)
        # Incremental checkpoint supports interrupted recovery without losing provenance.
        report = {'updated_at': datetime.now(timezone.utc).isoformat(), 'overlay_sha256': digest(overlay_bytes), 'results': outcomes}
        (REPORT / 'results.json').write_text(json.dumps(report, ensure_ascii=False, indent=2) + '\n')
    print(json.dumps({'products': len(outcomes), 'ok': sum(r['status'] == 'ok' for r in outcomes),
                      'blocked': sum(r['status'] == 'blocked' for r in outcomes)}))


if __name__ == '__main__':
    main()
