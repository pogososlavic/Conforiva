"""Fail-closed guards for explicitly authorized local HOMYA operations.

These helpers inspect files only. They never run PHP, SQL or start a service, and
their error messages contain stable codes rather than configuration values.
"""
import os
import re
import stat
from pathlib import Path

LOCAL_URLS = {
    'http://127.0.0.1:8000', 'http://127.0.0.1:8080',
    'http://localhost:8000', 'http://localhost:8080',
    'http://[::1]:8000', 'http://[::1]:8080',
}
XAMPP_SOCKET = '/Applications/XAMPP/xamppfiles/var/mysql/mysql.sock'


def require(condition, code):
    if not condition:
        raise RuntimeError(code)


def safe_path(path):
    """Refuse links and ancestors another user can replace.

    A root-owned sticky temporary directory is allowed: its private descendants
    still must belong to this user or root and must not be group/world writable.
    """
    path = Path(path)
    require(path.is_absolute() and '..' not in path.parts, 'UNSAFE_LOCAL_PATH')
    for part in (path, *path.parents):
        require(not part.is_symlink(), 'SYMLINK_REFUSED')
        if part.exists() and part.is_dir():
            metadata = part.stat()
            require(metadata.st_uid in (0, os.geteuid()), 'PATH_OWNER_REFUSED')
            sticky_root = metadata.st_uid == 0 and bool(metadata.st_mode & stat.S_ISVTX)
            require(not metadata.st_mode & 0o022 or sticky_root, 'MUTABLE_PARENT_REFUSED')
    return path


def file_metadata(metadata, private=False):
    require(stat.S_ISREG(metadata.st_mode), 'LOCAL_FILE_REQUIRED')
    require(metadata.st_nlink == 1, 'HARDLINK_REFUSED')
    require(metadata.st_uid in (0, os.geteuid()), 'FILE_OWNER_REFUSED')
    require(not metadata.st_mode & 0o022, 'MUTABLE_FILE_REFUSED')
    if private:
        require(metadata.st_uid == os.geteuid() and not metadata.st_mode & 0o077,
                'PRIVATE_FILE_REQUIRED')


def regular_file(path, private=False):
    path = safe_path(path)
    require(path.is_file(), 'LOCAL_FILE_REQUIRED')
    file_metadata(path.stat(), private)
    return path


def environment_values(path):
    """Keep values verbatim for copying; never evaluate dotenv substitutions."""
    path = regular_file(path, private=True)
    descriptor = os.open(path, os.O_RDONLY | os.O_NOFOLLOW)
    with os.fdopen(descriptor, 'r') as stream:
        file_metadata(os.fstat(stream.fileno()), private=True)
        content = stream.read()
    values = {}
    for line in content.splitlines():
        match = re.match(r'^\s*(?:export\s+)?([A-Z][A-Z0-9_]*)\s*=', line)
        if not match:
            continue
        require(line.startswith(match[1] + '='), 'ENV_UNSUPPORTED_SYNTAX')
        require(match[1] not in values, 'DUPLICATE_ENV_KEY')
        values[match[1]] = line.split('=', 1)[1]
    return values


def literal_value(values, key):
    value = values.get(key, '').strip()
    if value.startswith(('"', "'")):
        quote = value[0]
        closing = value.find(quote, 1)
        require(closing > 0 and (not value[closing + 1:].strip()
                                or value[closing + 1:].strip().startswith('#')), 'ENV_LITERAL_REQUIRED')
        value = value[1:closing]
    else:
        value = re.split(r'\s+#', value, maxsplit=1)[0].strip()
    require(not any(character in value for character in ('$','\\', '\r', '\n')), 'ENV_LITERAL_REQUIRED')
    return value


def require_local_project(project):
    project = safe_path(project)
    require(project.is_dir(), 'LOCAL_PROJECT_REQUIRED')
    cache = safe_path(project / 'bootstrap/cache/config.php')
    require(not cache.exists(), 'CONFIG_CACHE_PRESENT')
    values = environment_values(project / '.env')
    require(literal_value(values, 'APP_ENV') == 'local', 'LOCAL_ENV_REQUIRED')
    require(literal_value(values, 'APP_URL') in LOCAL_URLS, 'LOOPBACK_URL_REQUIRED')
    require(not literal_value(values, 'APP_CONFIG_CACHE'), 'CONFIG_CACHE_OVERRIDE_REFUSED')
    return values


def require_local_commerce(project):
    values = require_local_project(project)
    require(literal_value(values, 'HOMYA_COMMERCE_DB_DATABASE') == 'homya_local', 'COMMERCE_DATABASE_REFUSED')
    require(literal_value(values, 'HOMYA_COMMERCE_DB_USERNAME') == 'homya_local_commerce', 'COMMERCE_ACCOUNT_REFUSED')
    require(literal_value(values, 'HOMYA_COMMERCE_DB_HOST') in ('localhost', '127.0.0.1'), 'COMMERCE_HOST_REFUSED')
    require(literal_value(values, 'HOMYA_COMMERCE_DB_SOCKET') == XAMPP_SOCKET, 'COMMERCE_SOCKET_REFUSED')
    require(literal_value(values, 'HOMYA_COMMERCE_DB_PORT') == '3307', 'COMMERCE_PORT_REFUSED')
    require(not literal_value(values, 'DB_URL'), 'DATABASE_URL_REFUSED')
    return values


def command_environment():
    """Do not inherit APP_ENV, cache paths, credentials, DB_URL or PHP options.

    Laravel loads the guarded project's .env, rather than inherited overrides or
    an alternate .env.<APP_ENV>. No .env value is exposed in command arguments.
    """
    return {'PATH': '/opt/homebrew/bin:/usr/bin:/bin', 'LANG': 'C', 'LC_ALL': 'C'}


def private_write(path, content):
    path = safe_path(path)
    descriptor = os.open(path, os.O_WRONLY | os.O_CREAT | os.O_EXCL | os.O_NOFOLLOW, 0o600)
    with os.fdopen(descriptor, 'w') as stream:
        file_metadata(os.fstat(stream.fileno()), private=True)
        stream.write(content)
        stream.flush()
        os.fsync(stream.fileno())


def private_copy(source, target, private_source=False):
    source = regular_file(source, private=private_source)
    target = safe_path(target)
    descriptor = os.open(source, os.O_RDONLY | os.O_NOFOLLOW)
    with os.fdopen(descriptor, 'rb') as stream:
        file_metadata(os.fstat(stream.fileno()), private=private_source)
        content = stream.read()
    descriptor = os.open(target, os.O_WRONLY | os.O_CREAT | os.O_EXCL | os.O_NOFOLLOW, 0o600)
    with os.fdopen(descriptor, 'wb') as stream:
        file_metadata(os.fstat(stream.fileno()), private=True)
        stream.write(content)
        stream.flush()
        os.fsync(stream.fileno())
