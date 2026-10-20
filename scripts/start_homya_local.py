"""Restart only the approved local HOMYA processes; no database writes.

Default reports status. --start leaves existing verified processes untouched.
Never stops a service or uses an occupied port belonging to another process.
"""
import argparse
import json
import os
from pathlib import Path
import socket
import stat
import subprocess
import time
import urllib.request
from urllib.parse import urlsplit
from op10_local_switch import require_private
from local_operation_security import command_environment, file_metadata, safe_path

RUN = Path('/Applications/XAMPP/xamppfiles/homya-local/20261008T180754Z-1afc52ac-d21f-4530-a1ac-83890af9b236')
SERVICES = [
    ('php-fpm', 19085, 'php-fpm.conf', ['/opt/homebrew/sbin/php-fpm', '--daemonize', '--fpm-config']),
    ('apache', 8080, 'httpd.conf', ['/Applications/XAMPP/xamppfiles/bin/httpd', '-f']),
]


def verified_pid(name, config):
    file = RUN / (name+'.pid')
    if not file.is_file() or file.is_symlink():
        return None
    text = file.read_text().strip()
    if not text.isdigit() or int(text) < 2:
        raise RuntimeError('PID_INVALID')
    result = subprocess.run(['/bin/ps', '-p', text, '-o', 'command='], capture_output=True,
                            shell=False, timeout=10, env=command_environment())
    if result.returncode != 0:
        return None
    if str(RUN / config) not in result.stdout.decode(errors='replace'):
        # After reboot a stale PID may belong to another program. Do not touch it;
        # the port probe below still refuses to start over an occupied listener.
        return None
    return int(text)


def open_startup_log(path):
    """Append only after validating the opened private inode, before any child starts."""
    path = safe_path(path)
    descriptor = os.open(path, os.O_WRONLY | os.O_CREAT | os.O_APPEND | os.O_NOFOLLOW | os.O_NONBLOCK, 0o600)
    try:
        metadata = os.fstat(descriptor)
        file_metadata(metadata, private=True)
        if stat.S_IMODE(metadata.st_mode) != 0o600:
            raise RuntimeError('STARTUP_LOG_PRIVATE_REQUIRED')
        return os.fdopen(descriptor, 'ab')
    except Exception:
        os.close(descriptor)
        raise


class NoLocalRedirects(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, request, response, code, message, headers, new_url):
        raise RuntimeError('LOCAL_HTTP_REDIRECT_REFUSED')


def wait_ready(timeout=90):
    """Wait for MariaDB and the storefront without exposing response bodies."""
    opener = urllib.request.build_opener(urllib.request.ProxyHandler({}), NoLocalRedirects())
    deadline = time.monotonic() + timeout
    database_socket = Path('/Applications/XAMPP/xamppfiles/var/mysql/mysql.sock')
    while time.monotonic() < deadline:
        try:
            if database_socket.is_socket():
                with opener.open('http://127.0.0.1:8080/fr/catalogue', timeout=3) as response:
                    if response.status in (301, 302, 303, 307, 308):
                        raise RuntimeError('LOCAL_HTTP_REDIRECT_REFUSED')
                    origin = urlsplit(response.geturl())
                    if origin.scheme != 'http' or origin.netloc != '127.0.0.1:8080':
                        raise RuntimeError('LOCAL_HTTP_ORIGIN_REFUSED')
                    if response.status == 200:
                        return
        except (OSError, ValueError):
            pass
        time.sleep(2)
    raise RuntimeError('LOCAL_DEPENDENCIES_NOT_READY')


def main(argv=None):
    parser = argparse.ArgumentParser()
    parser.add_argument('--start', action='store_true')
    parser.add_argument('--wait-ready', action='store_true')
    args = parser.parse_args(argv)
    try:
        os.umask(0o077)
        require_private(RUN, True)
        facts = {}
        for name, port, config, command in SERVICES:
            require_private(RUN / config)
            pid = verified_pid(name, config)
            if pid is None and args.start:
                with socket.socket() as probe:
                    probe.bind(('127.0.0.1', port))
                arguments = command + [str(RUN / config)] + (['-k', 'start'] if name == 'apache' else [])
                with open_startup_log(RUN / (name+'-startup.log')) as log:
                    if subprocess.run(arguments, stdout=log, stderr=log, shell=False,
                                      timeout=30, env=command_environment()).returncode != 0:
                        raise RuntimeError('START_FAILED')
                deadline = time.monotonic() + 3
                while time.monotonic() < deadline:
                    pid = verified_pid(name, config)
                    if pid is not None: break
                    time.sleep(0.05)
                if pid is None: raise RuntimeError('START_NOT_READY')
            facts[name] = {'running': pid is not None, 'pid': pid}
        if args.wait_ready:
            wait_ready()
        print(json.dumps({'url': 'http://127.0.0.1:8080/fr', 'services': facts, 'sql_executed': False}))
        return 0
    except Exception:
        print('{"status":"LOCAL_RUNTIME_UNAVAILABLE_NO_OTHER_SERVICE_CHANGED"}')
        return 1


if __name__ == '__main__':
    # Preserve the legacy implementation for installations with the original pool.
    if not RUN.exists():
        from start_home_confort_local import main as start_current
        raise SystemExit(start_current())
    raise SystemExit(main())
