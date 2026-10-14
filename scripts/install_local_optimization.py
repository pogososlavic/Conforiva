"""Install this bounded optimization into the existing local runtime.

Default: validate and prepare a reviewable plan only. --apply backs up existing
files, verifies known starting hashes, then copies additions and changed files.
Only the dedicated Apache 8080 instance receives a graceful configuration reload.
No database, .env, FPM configuration, service stop or production operation.
"""
import argparse
import datetime
import hashlib
import json
import os
from pathlib import Path
import subprocess
from local_operation_security import (
    command_environment, file_metadata, private_copy, private_write,
    regular_file, require_local_project, safe_path,
)

ROOT = Path(__file__).resolve().parents[1]
TARGET = Path('/Applications/XAMPP/xamppfiles/htdocs/HOMYA')
RUNTIME = Path('/Applications/XAMPP/xamppfiles/homya-local/20261008T180754Z-1afc52ac-d21f-4530-a1ac-83890af9b236')
HTTPD = '/Applications/XAMPP/xamppfiles/bin/httpd'
CHANGED = ['app/Services/Catalogue.php','app/Providers/AppServiceProvider.php','app/Services/ResponsiveMedia.php',
           'resources/js/app.js','resources/views/components/product-image.blade.php','resources/views/catalogue/product.blade.php',
           'public/.htaccess','.gitignore','pint.json','scripts/profile_catalogue.php','scripts/prepare_responsive_media.py',
           'scripts/test_prepare_responsive_media.py','scripts/verify_optimization_http.py','scripts/install_local_optimization.py',
           'scripts/local_operation_security.py',
           'tests/Unit/ResponsiveMediaTest.php']


def digest(path):
    path = regular_file(path)
    descriptor = os.open(path, os.O_RDONLY | os.O_NOFOLLOW)
    with os.fdopen(descriptor, 'rb') as stream:
        file_metadata(os.fstat(stream.fileno()))
        return hashlib.sha256(stream.read()).hexdigest()


def atomic_copy(source, target):
    source = regular_file(source)
    target = safe_path(target)
    target_metadata = regular_file(target).stat() if target.exists() else None
    target_before = digest(target) if target_metadata is not None else None
    target_identity = (target_metadata.st_dev, target_metadata.st_ino) if target_metadata else None
    target.parent.mkdir(parents=True,exist_ok=True)
    pending = safe_path(target.with_name(target.name+'.homya-optimization-pending'))
    source_descriptor = os.open(source, os.O_RDONLY | os.O_NOFOLLOW)
    with os.fdopen(source_descriptor, 'rb') as source_stream:
        source_metadata = os.fstat(source_stream.fileno())
        file_metadata(source_metadata)
        descriptor = os.open(pending, os.O_WRONLY | os.O_CREAT | os.O_EXCL | os.O_NOFOLLOW, 0o600)
        with os.fdopen(descriptor, 'wb') as stream:
            pending_metadata = os.fstat(stream.fileno())
            file_metadata(pending_metadata, private=True)
            copied_hash = hashlib.sha256()
            for chunk in iter(lambda: source_stream.read(1024 * 1024), b''):
                stream.write(chunk)
                copied_hash.update(chunk)
            stream.flush()
            os.fsync(stream.fileno())
            if digest(pending) != copied_hash.hexdigest() or digest(source) != copied_hash.hexdigest():
                raise RuntimeError('COPY_MISMATCH')
            safe_path(target)
            current_metadata = regular_file(target).stat() if target.exists() else None
            current_identity = (current_metadata.st_dev, current_metadata.st_ino) if current_metadata else None
            if current_identity != target_identity or (digest(target) if current_metadata else None) != target_before:
                raise RuntimeError('TARGET_CHANGED')
            os.fchmod(stream.fileno(), (target_metadata or source_metadata).st_mode & 0o777)
            publication = regular_file(pending).stat()
            if (publication.st_dev, publication.st_ino) != (pending_metadata.st_dev, pending_metadata.st_ino):
                raise RuntimeError('PENDING_CHANGED')
            safe_path(target)
            pending.replace(target)


def command(arguments, **options):
    return subprocess.run(arguments, shell=False, timeout=60,
                          env=command_environment(), **options)


def main():
    parser=argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--apply',action='store_true')
    args=parser.parse_args()
    require_local_project(ROOT)
    require_local_project(TARGET)
    safe_path(RUNTIME)
    evidence=safe_path(ROOT/'output/qa-optimization')
    before=json.loads(regular_file(evidence/'before.json').read_text())
    expected={r['path']:r['sha256'] for r in before['source_files']}
    protected=before['protected']
    for path,value in protected.items():
        if digest(ROOT/path)!=value or digest(TARGET/path)!=value: raise ValueError('Protected file changed')
    runtime_before=json.loads(regular_file(evidence/'runtime-before.json').read_text())
    for path,value in runtime_before.items():
        if digest(RUNTIME/path)!=value: raise ValueError('Runtime configuration changed')
    apache_pid=regular_file(RUNTIME/'apache.pid',private=True).read_text().strip()
    fpm_pid=regular_file(RUNTIME/'php-fpm.pid',private=True).read_text().strip()
    if not apache_pid.isdigit() or not fpm_pid.isdigit(): raise ValueError('Invalid PID')
    process_command=command(['/bin/ps','-p',apache_pid,'-o','command='],capture_output=True,text=True,check=True).stdout
    if str(RUNTIME/'httpd.conf') not in process_command: raise ValueError('Unowned Apache')
    files=list(CHANGED)
    files += [str(p.relative_to(ROOT)) for p in (ROOT/'public/build/assets').glob('*') if p.is_file()]
    files += [str(p.relative_to(ROOT)) for p in (ROOT/'public/media/catalogue-display').glob('*') if p.is_file()]
    # Publish the manifest only after its new assets are present.
    files.append('public/build/manifest.json')
    plan=[]
    for path in files:
        source=safe_path(ROOT/path);target=safe_path(TARGET/path)
        safe_path(target.with_name(target.name+'.homya-optimization-pending'))
        source_hash=digest(source)
        target_hash=digest(target) if target.exists() else None
        if target_hash==source_hash: continue
        if target_hash is not None and target_hash!=expected.get(path): raise ValueError('Unexpected target collision: '+path)
        plan.append({'path':path,'source_sha256':source_hash,'target_before_sha256':target_hash})
    candidate=evidence/'httpd-candidate.conf'
    check=command([HTTPD,'-t','-f',str(candidate)],capture_output=True,text=True)
    if check.returncode!=0: raise ValueError('Apache candidate invalid')
    safe_path(evidence/'installation-plan.json').write_text(json.dumps({'files':plan,'config_before':runtime_before['httpd.conf'],
        'config_after':digest(candidate),'apache_pid':apache_pid,'fpm_pid':fpm_pid},indent=2)+'\n')
    if not args.apply:
        print(json.dumps({'phase':'prepared','files':len(plan),'apache_syntax':'OK'}));return
    tests=json.loads(regular_file(Path('/private/tmp/homya-optimization-tests-final.json')).read_text())
    if tests.get('result')!='passed': raise ValueError('Application tests not passed')
    require_local_project(ROOT)
    require_local_project(TARGET)
    stamp=datetime.datetime.now(datetime.timezone.utc).strftime('%Y%m%dT%H%M%SZ')
    backup=safe_path(RUNTIME/('optimization-backup-'+stamp))
    backup.mkdir(mode=0o700)
    # Finish and verify every backup before any runtime mutation.
    for item in plan:
        if item['target_before_sha256'] is None: continue
        path=item['path'];saved=backup/'files'/path
        safe_path(saved).parent.mkdir(parents=True,mode=0o700,exist_ok=True)
        private_copy(TARGET/path,saved)
        if digest(saved)!=item['target_before_sha256']: raise ValueError('Backup mismatch')
    private_copy(RUNTIME/'httpd.conf',backup/'httpd.conf')
    if digest(backup/'httpd.conf')!=runtime_before['httpd.conf']: raise ValueError('Config backup mismatch')
    private_write(backup/'plan.json',json.dumps(plan,indent=2)+'\n')
    installed=[]
    for item in plan:
        path=item['path'];target=TARGET/path
        if digest(ROOT/path)!=item['source_sha256']: raise ValueError('Source changed during installation')
        current=digest(target) if target.exists() else None
        if current!=item['target_before_sha256']: raise ValueError('Target changed during installation')
        require_local_project(ROOT)
        require_local_project(TARGET)
        atomic_copy(ROOT/path,target)
        installed.append(path)
    if digest(RUNTIME/'httpd.conf')!=runtime_before['httpd.conf']: raise ValueError('Config changed during installation')
    require_local_project(ROOT)
    require_local_project(TARGET)
    atomic_copy(candidate,RUNTIME/'httpd.conf')
    os.chmod(RUNTIME/'httpd.conf',0o600)
    command([HTTPD,'-t','-f',str(RUNTIME/'httpd.conf')],capture_output=True,check=True)
    require_local_project(ROOT)
    require_local_project(TARGET)
    command([HTTPD,'-f',str(RUNTIME/'httpd.conf'),'-k','graceful'],capture_output=True,check=True)
    if regular_file(RUNTIME/'apache.pid',private=True).read_text().strip()!=apache_pid or regular_file(RUNTIME/'php-fpm.pid',private=True).read_text().strip()!=fpm_pid:
        raise ValueError('Unexpected process identity change')
    for item in plan:
        if digest(TARGET/item['path'])!=item['source_sha256']: raise ValueError('Installed hash mismatch')
    for path,value in protected.items():
        if digest(ROOT/path)!=value or digest(TARGET/path)!=value: raise ValueError('Protected file changed after installation')
    if digest(RUNTIME/'php-fpm.conf')!=runtime_before['php-fpm.conf']: raise ValueError('FPM configuration changed')
    result={'backup':str(backup),'installed_files':len(installed),'apache_pid':apache_pid,'fpm_pid':fpm_pid,
            'protected_files_unchanged':True,'sql_writes':False,'services_stopped':False,'apache_graceful_reload':True}
    safe_path(evidence/'installation.json').write_text(json.dumps(result,indent=2)+'\n')
    print(json.dumps(result))


if __name__=='__main__':main()
