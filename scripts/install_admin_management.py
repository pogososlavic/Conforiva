"""Six additive admin tables only. Default: prepare plan, no database access/mutation.

--apply --sha256 requires explicit human authorization of docs/DASHBOARD-GESTION-HOMYA.md.
No automatic retry/drop/rollback; preserves existing commerce/archive data and services.
"""
import argparse
import datetime
import hashlib
import json
import os
import re
import subprocess
from pathlib import Path
from local_operation_security import (
    command_environment, file_metadata, private_write, regular_file, require_local_commerce,
    require_local_project, safe_path,
)

ROOT = Path(__file__).resolve().parents[1]
TARGET = Path('/Applications/XAMPP/xamppfiles/htdocs/HOMYA')
SOCKET = '/Applications/XAMPP/xamppfiles/var/mysql/mysql.sock'
MYSQL = '/Applications/XAMPP/xamppfiles/bin/mysql'
DUMP = '/Applications/XAMPP/xamppfiles/bin/mysqldump'
ACCOUNT = 'homya_local_commerce'
TABLES = ['commerce_admin_products', 'commerce_admin_threads', 'commerce_admin_messages', 'commerce_admin_events', 'commerce_admin_shipments', 'commerce_admin_reports']
PLAN = ROOT / 'output/qa-commerce/admin-management-plan.json'
SCHEMA = ROOT / 'database/migrations/commerce/2026_10_09_000002_create_admin_tables.php'
DOC = ROOT / 'docs/DASHBOARD-GESTION-HOMYA.md'


def require(condition, reason):
    if not condition:
        raise RuntimeError(reason)


def digest(path):
    path = regular_file(path)
    descriptor = os.open(path, os.O_RDONLY | os.O_NOFOLLOW)
    with os.fdopen(descriptor, 'rb') as stream:
        file_metadata(os.fstat(stream.fileno()))
        return hashlib.sha256(stream.read()).hexdigest()


def run(command, input=None):
    try:
        result = subprocess.run(command, input=input, text=True, capture_output=True,
                                shell=False, timeout=60, env=command_environment())
    except subprocess.TimeoutExpired:
        raise RuntimeError('COMMAND_TIMEOUT') from None
    require(result.returncode == 0, 'COMMAND_FAILED')
    return result.stdout.strip()


def sql(statement):
    return run([MYSQL, '--no-defaults', '--protocol=SOCKET', '--socket=' + SOCKET, '-u', 'root', '--batch', '--skip-column-names', 'homya_local'], statement)


def private(path, content):
    private_write(path, content)


def prepare():
    require_local_project(ROOT)
    prepared = json.loads(run(['/opt/homebrew/bin/php', str(ROOT / 'scripts/prepare_admin_schema.php')]))
    require(prepared['migration_sha256'] == digest(SCHEMA), 'SCHEMA_CHANGED')
    for query in prepared['queries']:
        match = re.match(r'(?:create table|alter table) `([^`]+)`', query, re.I)
        require(match and match[1] in TABLES and ';' not in query, 'DDL_OUTSIDE_SCOPE')
    value = {'target': 'XAMPP MariaDB / homya_local', 'tables': TABLES, 'migration_sha256': digest(SCHEMA), 'document_sha256': digest(DOC), 'queries': prepared['queries'], 'backup': 'Full private SQL dump before DDL', 'rights': 'SELECT,INSERT,UPDATE on six new tables only', 'existing_data': 'Preserved; no stock reset', 'activation': 'HOMYA_ADMIN_MANAGEMENT_ENABLED=true in source and local served copy only after schema verification', 'sql_executed': False}
    safe_path(PLAN).parent.mkdir(parents=True, exist_ok=True)
    PLAN.write_text(json.dumps(value, ensure_ascii=False, indent=2) + '\n')
    print(json.dumps({'result': 'prepared', 'plan': str(PLAN), 'sha256': digest(PLAN), 'tables': len(TABLES), 'sql_executed': False}))


def enable(path, backup):
    require_local_project(path.parent)
    require(path.is_file() and not path.is_symlink(), 'ENV_UNSAFE')
    before = path.read_text()
    require(len(re.findall(r'^HOMYA_ADMIN_MANAGEMENT_ENABLED=', before, re.M)) <= 1, 'DUPLICATE_FLAG')
    private(backup, before)
    key = 'HOMYA_ADMIN_MANAGEMENT_ENABLED=true'
    after = re.sub(r'^HOMYA_ADMIN_MANAGEMENT_ENABLED=.*$', key, before, flags=re.M) if re.search(r'^HOMYA_ADMIN_MANAGEMENT_ENABLED=', before, re.M) else before.rstrip() + '\n' + key + '\n'
    tmp = path.with_name('.env-admin-management-pending')
    private(tmp, after)
    require(path.read_text() == before, 'ENV_CHANGED')
    tmp.replace(path)


def apply(expected):
    # Refuse production, a remote URL or a substituted DB before any SQL/DDL.
    require_local_commerce(ROOT)
    require_local_commerce(TARGET)
    require(PLAN.is_file() and digest(PLAN) == expected, 'PLAN_CHANGED')
    plan = json.loads(PLAN.read_text())
    require(plan['tables'] == TABLES and plan['migration_sha256'] == digest(SCHEMA) and plan['document_sha256'] == digest(DOC), 'INPUT_CHANGED')
    require(digest(TARGET / SCHEMA.relative_to(ROOT)) == digest(SCHEMA), 'LOCAL_CODE_NOT_INSTALLED')
    require(not (TARGET / 'bootstrap/cache/config.php').exists() and not (ROOT / 'bootstrap/cache/config.php').exists(), 'CACHED_CONFIG')
    require(sql('SELECT VERSION(), DATABASE(), @@datadir') == '10.4.28-MariaDB\thomya_local\t/Applications/XAMPP/xamppfiles/var/mysql/', 'TARGET_IDENTITY')
    existing = sql('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() ORDER BY TABLE_NAME').splitlines()
    require(not set(TABLES).intersection(existing), 'TABLE_ALREADY_PRESENT_NO_RETRY')
    require({'commerce_inventory', 'commerce_orders', 'commerce_order_items', 'commerce_payment_events'}.issubset(existing), 'COMMERCE_SCHEMA_MISSING')
    require(sql("SELECT COUNT(*) FROM mysql.user WHERE User='homya_local_commerce' AND Host='localhost'") == '1', 'DEDICATED_ACCOUNT_MISSING')
    checksum_query = 'CHECKSUM TABLE ' + ','.join('`' + t + '`' for t in existing)
    checksums = sql(checksum_query)
    catalogue = ROOT / 'storage/app/catalogue/index.json'
    catalogue_hash = digest(catalogue)
    stamp = datetime.datetime.now(datetime.timezone.utc).strftime('%Y%m%dT%H%M%SZ')
    directory = safe_path(ROOT / 'storage/app/private/admin' / ('install-' + stamp))
    directory.mkdir(parents=True, mode=0o700)
    require(directory.stat().st_mode & 0o077 == 0, 'PRIVATE_BACKUP_REQUIRED')
    private(directory / 'plan.json', PLAN.read_text())
    backup = directory / 'homya_local-before.sql'
    descriptor = os.open(backup, os.O_WRONLY | os.O_CREAT | os.O_EXCL | os.O_NOFOLLOW, 0o600)
    with os.fdopen(descriptor, 'w') as stream:
        try:
            result = subprocess.run([DUMP, '--no-defaults', '--protocol=SOCKET', '--socket=' + SOCKET, '-u', 'root', '--single-transaction', '--routines', '--triggers', 'homya_local'], stdout=stream, stderr=subprocess.PIPE, text=True,
                                    shell=False, timeout=180, env=command_environment())
        except subprocess.TimeoutExpired:
            raise RuntimeError('BACKUP_TIMEOUT') from None
    require(result.returncode == 0 and backup.stat().st_size > 1000, 'BACKUP_FAILED')
    state = {'result': 'started', 'ddl_completed': 0, 'backup': str(directory), 'existing_tables': len(existing)}
    private(directory / 'before.json', json.dumps({'checksums': checksums, 'catalogue_sha256': catalogue_hash}))
    try:
        for query in plan['queries']:
            match = re.match(r'(?:create table|alter table) `([^`]+)`', query, re.I)
            require(match and match[1] in TABLES and ';' not in query, 'DDL_OUTSIDE_SCOPE')
            sql(query)
            state['ddl_completed'] += 1
        for table in TABLES:
            sql('GRANT SELECT,INSERT,UPDATE ON `homya_local`.`' + table + "` TO 'homya_local_commerce'@'localhost'")
            require(sql('SELECT COUNT(*) FROM `' + table + '`') == '0', 'UNEXPECTED_NEW_DATA')
        require(sql(checksum_query) == checksums and digest(catalogue) == catalogue_hash, 'EXISTING_DATA_CHANGED')
        for table in TABLES:
            privileges = sql("SELECT PRIVILEGE_TYPE FROM information_schema.TABLE_PRIVILEGES WHERE GRANTEE=CONCAT(CHAR(39),'homya_local_commerce',CHAR(39),'@',CHAR(39),'localhost',CHAR(39)) AND TABLE_SCHEMA='homya_local' AND TABLE_NAME='" + table + "' ORDER BY PRIVILEGE_TYPE")
            require(privileges.splitlines() == ['INSERT', 'SELECT', 'UPDATE'], 'NEW_PRIVILEGE_MISMATCH')
        enable(ROOT / '.env', directory / 'source-env-before')
        enable(TARGET / '.env', directory / 'served-env-before')
        state.update(result='installed', tables=6, existing_data_preserved=True, stock_reset=False, services_changed=False, module_enabled=True)
    except Exception:
        state['result'] = 'stopped_preserve_partial_ddl'
        raise
    finally:
        private(directory / 'result.json', json.dumps(state, indent=2))
    print(json.dumps(state))


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--apply', action='store_true')
    parser.add_argument('--sha256')
    args = parser.parse_args()
    try:
        if args.apply:
            require(args.sha256 is not None, 'REVIEWED_DIGEST_REQUIRED')
            apply(args.sha256)
        else:
            prepare()
    except Exception as error:
        print(json.dumps({'result': 'refused_or_stopped', 'reason': str(error) if isinstance(error, RuntimeError) else 'PRIVATE_FAILURE'}))
        raise SystemExit(1)
