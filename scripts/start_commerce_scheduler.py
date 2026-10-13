"""Start only the local HOMYA reservation scheduler. Default reports status; never stops existing services."""
import argparse
import json
import os
import stat
import subprocess
import time
from pathlib import Path
from local_operation_security import (
    command_environment, file_metadata, regular_file, require_local_commerce,
    safe_path,
)

TARGET = Path('/Applications/XAMPP/xamppfiles/htdocs/HOMYA')
RUNTIME = Path('/Applications/XAMPP/xamppfiles/homya-local/commerce-scheduler')
PHP = '/opt/homebrew/bin/php'
COMMAND = [PHP, str(TARGET / 'artisan'), 'schedule:work', '--no-interaction']


def status():
    pidfile = RUNTIME / 'scheduler.pid'
    safe_path(pidfile)
    if not pidfile.exists():
        return None
    regular_file(pidfile, private=True)
    pid = pidfile.read_text().strip()
    if not pid.isdigit() or int(pid) < 2:
        raise RuntimeError('INVALID_PID')
    process = subprocess.run(['/bin/ps', '-p', pid, '-o', 'command='], capture_output=True,
                             text=True, shell=False, timeout=10,
                             env=command_environment())
    if process.returncode != 0:
        return None
    if ' '.join(COMMAND) not in process.stdout:
        raise RuntimeError('PID_NOT_OWNED')
    return int(pid)


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument('--start', action='store_true')
    args = parser.parse_args()
    if args.start:
        require_local_commerce(TARGET)
        regular_file(TARGET / 'artisan')
    pid = status()
    if args.start and pid is None:
        safe_path(RUNTIME)
        regular_file(TARGET / 'app/Console/Commands/ExpireBankTransfers.php')
        os.umask(0o077)
        RUNTIME.mkdir(mode=0o700, exist_ok=True)
        if RUNTIME.stat().st_mode & 0o077 or RUNTIME.stat().st_uid != os.geteuid():
            raise RuntimeError('PRIVATE_RUNTIME_REQUIRED')
        log_path = safe_path(RUNTIME / 'scheduler.log')
        pid_path = safe_path(RUNTIME / 'scheduler.pid')
        temporary = safe_path(pid_path.with_name('scheduler.pid-pending'))
        if temporary.exists():
            raise RuntimeError('PID_TEMPORARY_COLLISION')
        if log_path.exists():
            regular_file(log_path, private=True)
            metadata = log_path.stat()
            if metadata.st_mode & 0o077 or metadata.st_uid != os.geteuid():
                raise RuntimeError('PRIVATE_LOG_REQUIRED')
        require_local_commerce(TARGET)
        check = subprocess.run([PHP, str(TARGET / 'artisan'), 'homya:expire-bank-transfers'],
                               cwd=TARGET, capture_output=True, text=True, shell=False,
                               timeout=60, env=command_environment())
        if check.returncode != 0:
            raise RuntimeError('RESERVATION_TARGET_UNAVAILABLE')
        require_local_commerce(TARGET)
        descriptor = os.open(log_path, os.O_WRONLY | os.O_CREAT | os.O_APPEND | os.O_NOFOLLOW, 0o600)
        with os.fdopen(descriptor, 'ab') as log:
            metadata = os.fstat(log.fileno())
            file_metadata(metadata, private=True)
            if not stat.S_ISREG(metadata.st_mode) or metadata.st_mode & 0o077 or metadata.st_uid != os.geteuid():
                raise RuntimeError('PRIVATE_LOG_REQUIRED')
            descriptor = os.open(temporary, os.O_WRONLY | os.O_CREAT | os.O_EXCL | os.O_NOFOLLOW, 0o600)
            with os.fdopen(descriptor, 'w') as pid_stream:
                file_metadata(os.fstat(pid_stream.fileno()), private=True)
                require_local_commerce(TARGET)
                process = subprocess.Popen(COMMAND, cwd=TARGET, stdin=subprocess.DEVNULL,
                                           stdout=log, stderr=log, start_new_session=True,
                                           shell=False, env=command_environment())
                pid_stream.write(str(process.pid) + '\n')
                pid_stream.flush()
                os.fsync(pid_stream.fileno())
            temporary.replace(pid_path)
        time.sleep(0.3)
        if process.poll() is not None:
            raise RuntimeError('SCHEDULER_START_FAILED')
        pid = status()
    print(json.dumps({'running': pid is not None, 'pid': pid, 'checks': 'bank reservations every minute', 'other_services_changed': False}))


if __name__ == '__main__':
    try:
        main()
    except Exception:
        print('{"status":"RESERVATION_SCHEDULER_UNAVAILABLE"}')
        raise SystemExit(1)
