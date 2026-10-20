#!/usr/bin/env python3
"""Create private, reviewable source/catalogue packages. Never deploy or read .env."""
import argparse
import hashlib
import json
import os
from pathlib import Path
import subprocess
import tarfile
from datetime import datetime, timezone


ROOT = Path(__file__).resolve().parents[1]
DENIED = {'.DS_Store', 'hot', 'auth.json', 'config.php', 'packages.php', 'services.php'}


def digest(path):
    result = hashlib.sha256()
    with path.open('rb') as source:
        for chunk in iter(lambda: source.read(1024 * 1024), b''):
            result.update(chunk)
    return result.hexdigest()


def safe_files(folder):
    if folder.is_symlink():
        raise ValueError('Symlink refused: ' + folder.relative_to(ROOT).as_posix())
    for item in sorted(folder.rglob('*')):
        if item.is_symlink():
            raise ValueError('Symlink refused: ' + item.relative_to(ROOT).as_posix())
        if item.is_file():
            relative = item.relative_to(ROOT)
            if relative.parts[0] == 'public' and item.suffix.lower() in {'.php', '.phtml', '.phar', '.cgi', '.sh', '.py'} and relative.as_posix() != 'public/index.php':
                raise ValueError('Unexpected executable public file: ' + relative.as_posix())
            if item.name.startswith('.env') or item.name in DENIED or item.suffix in {'.map', '.log', '.sql', '.sqlite', '.key', '.pem', '.pyc'}:
                continue
            if relative.parts[:2] == ('bootstrap', 'cache'):
                continue
            yield item


def pack(destination, files, skeleton=False):
    members = []
    with tarfile.open(destination, 'w:gz') as archive:
        if skeleton:
            for name in ('bootstrap/cache', 'storage/app/private', 'storage/app/public', 'storage/framework/cache/data', 'storage/framework/cache/locks', 'storage/framework/sessions', 'storage/framework/views', 'storage/logs'):
                info = tarfile.TarInfo(name)
                info.type = tarfile.DIRTYPE
                info.mode = 0o700 if name in ('storage/app/private', 'storage/logs', 'storage/framework/cache/locks') else 0o755
                archive.addfile(info)
        for path in files:
            name = path.relative_to(ROOT).as_posix()
            info = archive.gettarinfo(str(path), arcname=name)
            info.uid = info.gid = 0
            info.uname = info.gname = ''
            info.mode = 0o644
            with path.open('rb') as source:
                archive.addfile(info, source)
            members.append({'path': name, 'size': info.size, 'sha256': digest(path)})
    os.chmod(destination, 0o600)
    return members


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--output-dir', required=True)
    args = parser.parse_args()
    output = Path(args.output_dir).expanduser().resolve()
    if ROOT / 'public' == output or ROOT / 'public' in output.parents:
        raise ValueError('Output must be outside public/')
    output.mkdir(parents=True, exist_ok=False, mode=0o700)
    app_files = []
    for folder in ('app', 'bootstrap', 'config', 'database', 'lang', 'resources', 'routes', 'deploy'):
        app_files.extend(safe_files(ROOT / folder))
    app_files.extend(ROOT / name for name in ('artisan', 'composer.json', 'composer.lock', 'package.json', 'package-lock.json', 'vite.config.js') if (ROOT / name).is_file())
    app_files.extend(path for path in safe_files(ROOT / 'public') if path.relative_to(ROOT).parts[1] != 'media')
    application = pack(output / 'application.tar.gz', sorted(set(app_files)), skeleton=True)
    catalogue = pack(output / 'catalogue.tar.gz', safe_files(ROOT / 'storage/app/catalogue'))
    media = [{'path': path.relative_to(ROOT).as_posix(), 'size': path.stat().st_size, 'sha256': digest(path)} for path in safe_files(ROOT / 'public/media')]
    # Deliberately do not package local sessions, logs, accounts, customers or vendor/dev.
    report = {
        'created_utc': datetime.now(timezone.utc).isoformat(),
        'commit': subprocess.check_output(['git', 'rev-parse', 'HEAD'], cwd=ROOT, text=True).strip(),
        'working_tree_dirty': bool(subprocess.check_output(['git', 'status', '--porcelain'], cwd=ROOT)),
        'status': 'prepared_not_deployed',
        'requires': ['confirmed_domain', 'composer_install_no_dev', 'private_env', 'media_transfer', 'catalogue_review', 'hosting_preflight', 'backup_restore'],
        'packages': {name: digest(output / name) for name in ('application.tar.gz', 'catalogue.tar.gz')},
        'application': application, 'catalogue': catalogue, 'media': media,
        'media_total_bytes': sum(item['size'] for item in media),
    }
    manifest = output / 'manifest.json'
    manifest.write_text(json.dumps(report, indent=2) + '\n')
    os.chmod(manifest, 0o600)
    print(json.dumps({'output': str(output), 'application_files': len(application), 'catalogue_files': len(catalogue), 'media_files': len(media), 'media_bytes': report['media_total_bytes'], 'deployed': False}))


if __name__ == '__main__':
    main()
