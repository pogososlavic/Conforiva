"""Recover only CDN images explicitly referenced by the restored HOMYA catalogue.

Offline inventory by default. --apply downloads with bounded parallelism and
validates original bytes, SHA-256, image type and dimensions before installation.
No catalogue documents, database rows, existing different files or overlays change.
"""
import argparse
from collections import Counter
from concurrent.futures import ThreadPoolExecutor, as_completed
from datetime import datetime, timezone
import hashlib
import http.client
from io import BytesIO
import json
import os
from pathlib import Path
import re
import ssl
import tempfile
import threading
import time
from urllib.parse import urlsplit, urljoin

from PIL import Image

ROOT = Path(__file__).resolve().parents[1]
CATALOGUE = ROOT / 'storage/app/catalogue'
RUN = ROOT / 'storage/app/private/media-recovery-20261019/cdn'
HOST = 'damdam.b-cdn.net'
URL_PATTERN = re.compile(r'^/media/catalogue/[a-f0-9]{24}\.(jpg|jpeg|png|webp|gif|avif)$')
FORMATS = {'JPEG': 'image/jpeg', 'PNG': 'image/png', 'WEBP': 'image/webp', 'GIF': 'image/gif', 'AVIF': 'image/avif'}
LOCAL = threading.local()
SSL = ssl.create_default_context()
COOLDOWN = 0
LOCK = threading.Lock()
RATE_LOCK = threading.Lock()
NEXT_REQUEST = 0
HASH_LOCKS = {}
CONTENT = {}
CONTENT_CANDIDATES = {}


def stamp():
    return datetime.now(timezone.utc).isoformat()


def write_json(path, value):
    temporary = path.with_name(path.name + '.new')
    temporary.write_text(json.dumps(value, ensure_ascii=False, indent=2))
    os.chmod(temporary, 0o600)
    temporary.replace(path)


def inventory():
    index = json.loads((CATALOGUE / 'index.json').read_text())
    images = {}
    witnesses = {}
    for path in sorted(CATALOGUE.rglob('*.json')):
        # Originals recovered by the manufacturer worker are not source documents.
        if 'media-originals' not in path.parts:
            witnesses[str(path.relative_to(ROOT))] = hashlib.sha256(path.read_bytes()).hexdigest()
    for product in index['products']:
        slug = product['slug']
        if not re.fullmatch('[a-z0-9][a-z0-9-]{0,220}', slug):
            raise ValueError('Invalid catalogue slug')
        detail = json.loads((CATALOGUE / 'products' / (slug + '.json')).read_text())
        candidates = ([product['image']] if product.get('image') else []) + detail.get('images', [])
        for image in candidates:
            url, local = image.get('source_url'), image.get('local_url')
            if not url or not local:
                raise ValueError('Incomplete catalogue image reference')
            parsed = urlsplit(url)
            if (parsed.scheme != 'https' or parsed.hostname != HOST or parsed.username
                    or parsed.password or parsed.port not in (None, 443) or not URL_PATTERN.fullmatch(local)):
                raise ValueError('Image outside the archived CDN scope')
            if not re.fullmatch('[a-f0-9]{64}', image.get('sha256', '')) or not isinstance(image.get('bytes'), int):
                raise ValueError('Missing expected image fingerprint')
            entry = {key: image[key] for key in ['source_url', 'local_url', 'sha256', 'bytes', 'mime']}
            entry['destination'] = 'public' + local
            entry['source_owner'] = 'DAMDAM / catalogue fourni pour HOMYA'
            entry['authorization_basis'] = 'Demande utilisateur de récupération ; déclaration de droits consignée dans docs/CADRAGE-HOMYA.md'
            entry['rights_evidence'] = 'Déclaration utilisateur, contrats non examinés'
            entry['transformations'] = []
            if local in images:
                existing = images[local]
                if any(existing[key] != entry[key] for key in ['source_url', 'sha256', 'bytes', 'mime']):
                    raise ValueError('Conflicting destination in catalogue')
                existing['products'].add(slug)
            else:
                images[local] = entry | {'products': {slug}}
    primary = {p['image']['local_url'] for p in index['products'] if p.get('image')}
    entries = sorted(images.values(), key=lambda entry: (entry['local_url'] not in primary, entry['local_url']))
    for entry in entries:
        entry['products'] = sorted(entry['products'])
    return entries, witnesses


def inspect(data, entry):
    digest = hashlib.sha256(data).hexdigest()
    if len(data) != entry['bytes'] or digest != entry['sha256']:
        raise ValueError('SOURCE_BYTES_CHANGED')
    with Image.open(BytesIO(data)) as image:
        mime = FORMATS.get(image.format)
        dimensions = image.size
        if mime is None or mime != entry['mime'] or image.width < 1 or image.height < 1:
            raise ValueError('IMAGE_TYPE_MISMATCH')
        image.verify()
    return {'sha256': digest, 'bytes': len(data), 'mime': mime, 'width': dimensions[0], 'height': dimensions[1]}


def connection():
    if not getattr(LOCAL, 'connection', None):
        LOCAL.connection = http.client.HTTPSConnection(HOST, timeout=35, context=SSL)
    return LOCAL.connection


def close_connection():
    if getattr(LOCAL, 'connection', None):
        LOCAL.connection.close()
        LOCAL.connection = None


def request_slot():
    global NEXT_REQUEST
    with RATE_LOCK:
        now = time.monotonic()
        slot = max(now, NEXT_REQUEST)
        NEXT_REQUEST = slot + 1 / 16
    if slot > now:
        time.sleep(slot - now)


def fetch_source(entry):
    global COOLDOWN
    target = ROOT / entry['destination']
    if target.is_symlink():
        return {'local_url': entry['local_url'], 'status': 'existing_symlink_refused'}
    if target.exists():
        try:
            facts = inspect(target.read_bytes(), entry)
            return {'local_url': entry['local_url'], 'status': 'already_verified', **facts}
        except Exception:
            return {'local_url': entry['local_url'], 'status': 'existing_different_file_refused'}
    error = 'UNKNOWN'
    for attempt in range(3):
        remaining = COOLDOWN - time.monotonic()
        if remaining > 0:
            time.sleep(remaining)
        try:
            current = entry['source_url']
            for redirect in range(4):
                parsed = urlsplit(current)
                if parsed.scheme != 'https' or parsed.hostname != HOST or parsed.port not in (None, 443) or parsed.username or parsed.password:
                    raise ValueError('REDIRECT_OUT_OF_SCOPE')
                path = parsed.path + ('?' + parsed.query if parsed.query else '')
                conn = connection()
                request_slot()
                conn.request('GET', path, headers={'User-Agent': 'HOMYA-Local-Recovery/1.0', 'Accept': 'image/*', 'Accept-Encoding': 'identity'})
                response = conn.getresponse()
                if response.status in (301, 302, 303, 307, 308):
                    location = response.getheader('Location')
                    response.read(65536)
                    close_connection()
                    if not location:
                        raise ValueError('REDIRECT_WITHOUT_LOCATION')
                    current = urljoin(current, location)
                    continue
                break
            else:
                raise ValueError('REDIRECT_LIMIT')
            if response.status != 200:
                status = response.status
                retry = response.getheader('Retry-After')
                response.read(65536)
                close_connection()
                error = 'HTTP_' + str(status)
                if status == 429:
                    delay = min(120, max(5, int(retry) if retry and retry.isdigit() else 15))
                    with LOCK:
                        COOLDOWN = max(COOLDOWN, time.monotonic() + delay)
                if status in (403, 404, 410):
                    break
                time.sleep(min(15, 2 ** attempt))
                continue
            announced = response.getheader('Content-Length')
            if announced and (not announced.isdigit() or int(announced) > max(entry['bytes'], 16_000_000)):
                close_connection()
                raise ValueError('RESPONSE_TOO_LARGE')
            data = response.read(max(entry['bytes'], 16_000_000) + 1)
            mime = (response.getheader('Content-Type') or '').split(';')[0].strip().lower()
            if not mime.startswith('image/'):
                raise ValueError('HTTP_CONTENT_NOT_IMAGE')
            facts = inspect(data, entry)
            # A newly appeared path is checked, never overwritten.
            fd, temporary = tempfile.mkstemp(prefix='download-', dir=RUN)
            try:
                with os.fdopen(fd, 'wb') as handle:
                    handle.write(data)
                    os.fchmod(handle.fileno(), 0o644)
                os.link(temporary, target)
            except FileExistsError:
                if target.is_symlink() or target.read_bytes() != data:
                    raise ValueError('CONCURRENT_DESTINATION_COLLISION')
            finally:
                Path(temporary).unlink()
            return {'local_url': entry['local_url'], 'source_url': entry['source_url'],
                    'final_source_url': current, 'status': 'recovered', 'recovered_at_utc': stamp(),
                    'transformations': [], **facts}
        except (ValueError, Image.DecompressionBombError) as failure:
            close_connection()
            error = str(failure) if re.fullmatch('[A-Z_]+', str(failure)) else type(failure).__name__
            break
        except Exception as failure:
            close_connection()
            error = type(failure).__name__
            time.sleep(min(8, 2 ** attempt))
    return {'local_url': entry['local_url'], 'source_url': entry['source_url'], 'status': 'unavailable', 'reason': error, 'checked_at_utc': stamp()}


def fetch(entry):
    """Fetch identical bytes once, retain every archived URL/path via hard links."""
    digest = entry['sha256']
    target = ROOT / entry['destination']
    with HASH_LOCKS[digest]:
        if target.exists() or target.is_symlink():
            result = fetch_source(entry)
            if result['status'] == 'already_verified':
                CONTENT[digest] = (target, {k: result[k] for k in ['sha256', 'bytes', 'mime', 'width', 'height']})
            return result
        if digest not in CONTENT:
            for candidate in CONTENT_CANDIDATES[digest]:
                path = ROOT / candidate['destination']
                if path.is_file() and not path.is_symlink():
                    try:
                        CONTENT[digest] = (path, inspect(path.read_bytes(), candidate))
                        break
                    except Exception:
                        continue
        if digest in CONTENT:
            source, facts = CONTENT[digest]
            try:
                os.link(source, target)
            except FileExistsError:
                if target.is_symlink() or inspect(target.read_bytes(), entry)['sha256'] != digest:
                    raise ValueError('CONCURRENT_DESTINATION_COLLISION')
            return {'local_url': entry['local_url'], 'source_url': entry['source_url'], 'status': 'recovered_identical_content',
                    'content_source': str(source.relative_to(ROOT)), 'recovered_at_utc': stamp(), 'transformations': [], **facts}
        result = fetch_source(entry)
        if result['status'] == 'recovered':
            CONTENT[digest] = (target, {k: result[k] for k in ['sha256', 'bytes', 'mime', 'width', 'height']})
        return result


def fetch_group(group):
    """Schedule one worker per distinct content, never block workers on duplicates."""
    results = [fetch(entry) for entry in group]
    if group[0]['sha256'] in CONTENT:
        for i, result in enumerate(results):
            if result['status'] == 'unavailable':
                # The same archived bytes can be restored from another archived URL.
                results[i] = fetch(group[i])
    return results


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--apply', action='store_true')
    parser.add_argument('--workers', type=int, default=8, choices=range(1, 33))
    args = parser.parse_args()
    entries, witnesses = inventory()
    RUN.mkdir(parents=True, exist_ok=True, mode=0o700)
    os.chmod(RUN, 0o700)
    if not (RUN / 'inventory.json').exists():
        write_json(RUN / 'inventory.json', {'inventoried_at_utc': stamp(), 'images': entries, 'catalogue_sha256': witnesses})
    else:
        prior = json.loads((RUN / 'inventory.json').read_text())
        if prior['catalogue_sha256'] != witnesses or prior['images'] != entries:
            raise ValueError('Source catalogue changed since inventory')
    summary = {'phase': 'planned', 'expected': len(entries), 'expected_bytes': sum(e['bytes'] for e in entries),
               'counts': {}, 'verified_bytes': 0, 'started_at_utc': stamp(), 'workers': args.workers}
    write_json(RUN / 'progress.json', summary)
    if not args.apply:
        print(json.dumps(summary), flush=True)
        return
    destination = ROOT / 'public/media/catalogue'
    if destination.is_symlink():
        raise ValueError('Media directory symlink refused')
    destination.mkdir(parents=True, exist_ok=True)
    if (RUN / 'running.lock').exists():
        raise RuntimeError('Another media recovery may be running; inspect its lock first')
    lock = (RUN / 'running.lock').open('x')
    lock.write(str(os.getpid()))
    lock.close()
    counts = Counter()
    index = json.loads((CATALOGUE / 'index.json').read_text())
    rank = {p['image']['local_url']: i for i, p in enumerate(index['products']) if p.get('image')}
    entries.sort(key=lambda entry: rank.get(entry['local_url'], len(rank)))
    for entry in entries:
        HASH_LOCKS.setdefault(entry['sha256'], threading.Lock())
        CONTENT_CANDIDATES.setdefault(entry['sha256'], []).append(entry)
    start = time.monotonic()
    results_path = RUN / ('results-' + datetime.now(timezone.utc).strftime('%Y%m%dT%H%M%SZ') + '.jsonl')
    try:
        with results_path.open('x') as results, ThreadPoolExecutor(max_workers=args.workers) as pool:
            os.chmod(results_path, 0o600)
            groups = list(CONTENT_CANDIDATES.values())
            futures = {pool.submit(fetch_group, group): group for group in groups}
            done = 0
            last_print = 0
            for future in as_completed(futures):
                group = futures[future]
                try:
                    group_results = future.result()
                except Exception as failure:
                    group_results = [{'local_url': entry['local_url'], 'status': 'worker_error', 'reason': type(failure).__name__} for entry in group]
                for result in group_results:
                    done += 1
                    counts[result['status']] += 1
                    if result['status'] in ('recovered', 'already_verified', 'recovered_identical_content'):
                        summary['verified_bytes'] += result['bytes']
                    results.write(json.dumps(result, ensure_ascii=False) + '\n')
                results.flush()
                summary.update(phase='downloading', processed=done, counts=dict(counts), elapsed_seconds=round(time.monotonic() - start, 1), updated_at_utc=stamp())
                write_json(RUN / 'progress.json', summary)
                if done - last_print >= 500:
                    last_print = done
                    print(json.dumps({k: summary[k] for k in ('processed', 'expected', 'counts', 'verified_bytes', 'elapsed_seconds')}), flush=True)
        changed = [path for path, digest in witnesses.items() if hashlib.sha256((ROOT / path).read_bytes()).hexdigest() != digest]
        summary.update(phase='complete', catalogue_documents_changed=changed, results=str(results_path.relative_to(ROOT)), completed_at_utc=stamp())
        write_json(RUN / 'progress.json', summary)
        if changed:
            raise RuntimeError('Catalogue changed during media restoration')
        print(json.dumps(summary), flush=True)
    finally:
        (RUN / 'running.lock').unlink()


if __name__ == '__main__':
    main()
