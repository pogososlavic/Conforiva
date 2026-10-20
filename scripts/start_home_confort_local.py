"""Start the loopback-only PHP 8.5 development server; never stop other services."""
import argparse
import json
import os
from pathlib import Path
import shutil
import socket
import subprocess
import time

ROOT = Path(__file__).resolve().parents[1]
RUN = ROOT / 'storage/app/private/local-runtime'
URL = 'http://127.0.0.1:8080/fr'


def main(argv=None):
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--start', action='store_true')
    args = parser.parse_args(argv)
    php = shutil.which('php')
    if not php:
        raise RuntimeError('PHP_NOT_FOUND')
    check = subprocess.run([php, '-r', 'exit(PHP_VERSION_ID >= 80300 ? 0 : 1);'], check=False)
    if check.returncode:
        raise RuntimeError('PHP_83_REQUIRED')
    # Check the actual listener, never trust a stale PID or stop an occupied port.
    with socket.socket() as probe:
        occupied = probe.connect_ex(('127.0.0.1', 8080)) == 0
    if occupied:
        print(json.dumps({'status': 'PORT_ALREADY_OCCUPIED_NO_CHANGE', 'url': URL}))
        return 0
    if not args.start:
        print(json.dumps({'status': 'STOPPED', 'url': URL}))
        return 0
    os.umask(0o077)
    for path in (RUN, *RUN.parents):
        if path.is_symlink():
            raise RuntimeError('SYMLINK_REFUSED')
    RUN.mkdir(mode=0o700, parents=True, exist_ok=True)
    log = RUN / 'server.log'
    fd = os.open(log, os.O_WRONLY | os.O_CREAT | os.O_APPEND | os.O_NOFOLLOW, 0o600)
    command = [php, '-d', 'display_errors=0', '-d', 'log_errors=1', '-d', 'expose_php=0',
               '-S', '127.0.0.1:8080', '-t', str(ROOT / 'public'), str(ROOT / 'scripts/local_router.php')]
    with os.fdopen(fd, 'ab') as stream:
        process = subprocess.Popen(command, cwd=ROOT, stdin=subprocess.DEVNULL,
                                   stdout=stream, stderr=stream, start_new_session=True)
    for _ in range(100):
        if process.poll() is not None:
            raise RuntimeError('SERVER_START_FAILED_SEE_PRIVATE_LOG')
        with socket.socket() as probe:
            if probe.connect_ex(('127.0.0.1', 8080)) == 0:
                print(json.dumps({'status': 'STARTED', 'pid': process.pid, 'url': URL,
                                  'document_root': str(ROOT / 'public')}))
                return 0
        time.sleep(0.05)
    raise RuntimeError('SERVER_START_TIMEOUT')


if __name__ == '__main__':
    raise SystemExit(main())
