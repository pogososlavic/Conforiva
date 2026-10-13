"""Launch static local scenarios without ever reading or transporting credentials."""
import json
import os
from pathlib import Path
import shutil
import stat
import subprocess

ROOT = Path(__file__).resolve().parents[1]
BUNDLED_NODE = Path.home() / '.cache/codex-runtimes/codex-primary-runtime/dependencies/node'


def runner_command(scenario, screenshots_only=False):
    if scenario not in {'admin', 'management', 'upload', 'documents'}:
        raise ValueError('UNKNOWN_LOCAL_QA_SCENARIO')
    node = os.environ.get('HOMYA_QA_NODE') or str(BUNDLED_NODE / 'bin/node')
    if not Path(node).is_file():
        node = shutil.which('node') or ''
    module = Path(os.environ.get('HOMYA_QA_PLAYWRIGHT_MODULE') or BUNDLED_NODE / 'node_modules/playwright')
    if not node or not module.is_absolute() or not (module / 'package.json').is_file():
        raise RuntimeError('LOCAL_QA_RUNTIME_UNAVAILABLE')
    command = [node, str(ROOT / 'scripts/browser_qa/runner.mjs'), scenario, str(module)]
    if screenshots_only:
        if scenario != 'documents':
            raise ValueError('SCREENSHOTS_MODE_INVALID')
        command.append('--screenshots-only')
    return command


def runner_environment():
    # Do not forward DEBUG, NODE_OPTIONS, browser logging settings, or application secrets.
    environment = {'HOME': str(Path.home()), 'PATH': os.defpath, 'LANG': 'en_US.UTF-8'}
    if os.environ.get('PLAYWRIGHT_BROWSERS_PATH'):
        environment['PLAYWRIGHT_BROWSERS_PATH'] = os.environ['PLAYWRIGHT_BROWSERS_PATH']
    return environment


def run_scenario(scenario, screenshots_only=False, timeout=150):
    try:
        process = subprocess.run(runner_command(scenario, screenshots_only), cwd=ROOT,
                                 capture_output=True, text=True, timeout=timeout,
                                 env=runner_environment())
        if process.returncode:
            raise RuntimeError('BROWSER_CHECK_FAILED')
        result = json.loads(process.stdout)
        if not isinstance(result, dict) or result.get('result') == 'failed':
            raise RuntimeError('BROWSER_CHECK_FAILED')
        return result
    except subprocess.TimeoutExpired:
        print(json.dumps({'result': 'failed', 'reason': 'BROWSER_TIMEOUT'}))
        raise SystemExit(1) from None
    except (OSError, ValueError, RuntimeError):
        # The runner's captured stdout/stderr can never become a diagnostic report.
        print(json.dumps({'result': 'failed', 'reason': 'BROWSER_CHECK_FAILED'}))
        raise SystemExit(1) from None


def write_report(filename, result):
    if '/' in filename or '\\' in filename or not filename.endswith('.json'):
        raise ValueError('REPORT_PATH_INVALID')
    directory = ROOT
    for segment in ('output', 'qa-commerce'):
        directory /= segment
        directory.mkdir(mode=0o700, exist_ok=True)
        metadata = directory.lstat()
        if not stat.S_ISDIR(metadata.st_mode) or directory.is_symlink() or metadata.st_uid != os.getuid():
            raise RuntimeError('REPORT_PATH_INVALID')
        directory.chmod(0o700)
    target = directory / filename
    descriptor = os.open(target, os.O_WRONLY | os.O_CREAT | os.O_NOFOLLOW, 0o600)
    with os.fdopen(descriptor, 'w') as stream:
        metadata = os.fstat(stream.fileno())
        if not stat.S_ISREG(metadata.st_mode) or metadata.st_uid != os.getuid() or metadata.st_nlink != 1:
            raise RuntimeError('REPORT_PATH_INVALID')
        os.fchmod(stream.fileno(), 0o600)
        stream.truncate(0)
        stream.write(json.dumps(result, indent=2) + '\n')
