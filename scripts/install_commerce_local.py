"""Bounded HOMYA commerce installation. Offline plan by default; explicit --apply only.

Creates four local tables and one dedicated account, preserving the archive and existing roles.
Secrets are sent over stdin or written to private files, never printed or passed on argv.
"""
import argparse
import datetime
import hashlib
import json
import os
import re
import secrets
import subprocess
from pathlib import Path
from local_operation_security import (
    command_environment, file_metadata, private_copy, private_write,
    regular_file, require_local_commerce, require_local_project, safe_path,
)

ROOT = Path(__file__).resolve().parents[1]
MYSQL = '/Applications/XAMPP/xamppfiles/bin/mysql'
DUMP = '/Applications/XAMPP/xamppfiles/bin/mysqldump'
SOCKET = '/Applications/XAMPP/xamppfiles/var/mysql/mysql.sock'
DATADIR = '/Applications/XAMPP/xamppfiles/var/mysql'
REGISTRY = Path('/Users/akm/Codex-Workspace/specification/references/DECISION-AND-CHANGE-LOG.md')
TABLES = ['commerce_inventory', 'commerce_orders', 'commerce_order_items', 'commerce_payment_events']
ACCOUNT = 'homya_local_commerce'
SCHEMA = ROOT / 'database/migrations/commerce/2026_10_09_000001_create_commerce_tables.php'
INVENTORY = ROOT / 'storage/app/private/commerce/initial-inventory-v2.json'
PLAN = ROOT / 'docs/PARCOURS-ACHAT-HOMYA.md'


def digest(path):
    path = regular_file(path)
    descriptor = os.open(path, os.O_RDONLY | os.O_NOFOLLOW)
    with os.fdopen(descriptor, 'rb') as stream:
        file_metadata(os.fstat(stream.fileno()))
        return hashlib.sha256(stream.read()).hexdigest()


def require(condition, reason):
    if not condition:
        raise RuntimeError(reason)


def sql(query):
    require_local_project(ROOT)
    try:
        result = subprocess.run([MYSQL, '--no-defaults', '--protocol=SOCKET', '--socket=' + SOCKET,
                                 '--user=root', '--skip-password', '--batch', '--raw',
                                 '--skip-column-names', '--connect-timeout=5'],
                                input=query + ';\n', text=True, capture_output=True,
                                timeout=120, shell=False, env=command_environment())
    except subprocess.TimeoutExpired:
        raise RuntimeError('LOCAL_SQL_TIMEOUT_DETAILS_WITHHELD') from None
    require(result.returncode == 0 and not result.stderr, 'LOCAL_SQL_REFUSED_DETAILS_WITHHELD')
    return result.stdout.strip()


def php(args, environment=None):
    require_local_commerce(ROOT)
    regular_file(ROOT / 'artisan')
    child_environment = command_environment()
    for key, value in (environment or {}).items():
        require(key == 'HOMYA_CATALOGUE_SOURCE' and value == 'files', 'ENV_OVERRIDE_REFUSED')
        child_environment[key] = value
    try:
        result = subprocess.run(['/opt/homebrew/bin/php', 'artisan'] + args, cwd=ROOT,
                                env=child_environment, text=True, capture_output=True,
                                timeout=120, shell=False)
    except subprocess.TimeoutExpired:
        raise RuntimeError('LOCAL_PHP_TIMEOUT_DETAILS_WITHHELD') from None
    require(result.returncode == 0, 'LOCAL_PHP_REFUSED_DETAILS_WITHHELD')
    return result.stdout.strip()


def private_json(path, value):
    private_write(path, json.dumps(value, ensure_ascii=False, indent=2) + '\n')


def read_private_json(path):
    path = regular_file(path, private=True)
    descriptor = os.open(path, os.O_RDONLY | os.O_NOFOLLOW)
    with os.fdopen(descriptor, 'r') as stream:
        file_metadata(os.fstat(stream.fileno()), private=True)
        return json.load(stream)


def checksum_statement(tables):
    require(isinstance(tables, list) and tables and
            all(isinstance(name, str) and re.fullmatch(r'[a-zA-Z0-9_]+', name) for name in tables),
            'UNEXPECTED_EXISTING_TABLES')
    return 'CHECKSUM TABLE ' + ','.join('`homya_local`.`' + name + '`' for name in tables) + ' EXTENDED'


def append_registry(before, entry):
    require_local_project(ROOT)
    regular_file(REGISTRY)
    descriptor = os.open(REGISTRY, os.O_RDWR | os.O_APPEND | os.O_NOFOLLOW)
    with os.fdopen(descriptor, 'a+') as stream:
        file_metadata(os.fstat(stream.fileno()))
        stream.seek(0)
        require(stream.read() == before, 'REGISTRY_CHANGED')
        stream.write(entry)
        stream.flush()
        os.fsync(stream.fileno())
        stream.seek(0)
        require(stream.read() == before + entry, 'REGISTRY_READBACK')


def record_decision(run, state):
    require_local_project(ROOT)
    require(not REGISTRY.is_symlink(), 'REGISTRY_SYMLINK')
    regular_file(REGISTRY)
    before = REGISTRY.read_text()
    number = max(map(int, re.findall(r'DECISION-(\d+)', before))) + 1
    decision = 'DECISION-' + str(number).zfill(3)
    entry = f'''\n\n#### {decision}

HOMYA — 09/10/2026. **AUTORISÉ : panier, commandes, Stripe Checkout et stock local.** Demande utilisateur « Implémenter le panier, la commande et un paiement réellement opérationnel, puis tester le parcours complet », complétée par « apporte les modifications necessaires idoines quand tu remarques des failles ». Entreprise enregistrée en France, prix affichés confirmés TTC ; achats réels autorisés. Stripe Checkout retenu, compte à créer par l’utilisateur. Stock initial explicitement demandé : 1 000 unités pour chacun des 3 709 produits ; pour les 26 produits à variantes, même prix et stock commun de 1 000 confirmé. Données déclarées, aucun inventaire physique ou taux TVA inventé.

**VALIDÉ : installation locale SQL distincte**, réponse humaine « j autorise » au plan docs/PARCOURS-ACHAT-HOMYA.md : sauvegarder homya_local, créer uniquement commerce_inventory, commerce_orders, commerce_order_items, commerce_payment_events et homya_local_commerce@localhost, installer le manifeste de 1 000 unités/produit, synchroniser et vérifier le site local. Aucun nouvel hébergement/base, changement de service XAMPP ou des droits catalogue, débit financier, compte Stripe, publication ou validation Google. .env : seuls paramètres commerce dédiés ; checkout fermé jusqu’aux prérequis Stripe et commerciaux. Installation conservatrice, arrêt sur collision ou DDL partielle, aucun drop/rollback automatique.

Plan SHA256 {digest(PLAN)} ; migration {digest(SCHEMA)} ; inventaire {digest(INVENTORY)}. Preuve privée sous {run}. Code et migration préparés, 319 tests / 7 413 assertions hors MariaDB passés avant installation (13 scénarios commerce, 116 assertions, API Stripe simulée). Résultats MariaDB et installation à compléter ; aucune réussite physique présumée par cette entrée.
'''
    private_copy(REGISTRY, run / 'registry-before.md')
    append_registry(before, entry)
    state['decision'] = decision
    state['registry_after_sha256'] = digest(REGISTRY)


def update_env(path, updates, run, backup_name='source-env-before'):
    require(not path.is_symlink(), 'ENV_SYMLINK')
    require_local_project(path.parent)
    safe_path(run)
    before = path.read_text()
    lines = before.splitlines()
    for key, value in updates.items():
        require(key.startswith('HOMYA_COMMERCE_') or key in
                ('HOMYA_INVENTORY_ENABLED', 'HOMYA_CHECKOUT_ENABLED', 'HOMYA_STRIPE_MODE'), 'ENV_UPDATE_OUTSIDE_SCOPE')
        require(isinstance(value, str) and '\n' not in value and '\r' not in value, 'ENV_UPDATE_INVALID')
        require(sum(line.startswith(key + '=') for line in lines) <= 1, 'DUPLICATE_ENV_KEY')
        replacement = key + '=' + value
        if any(line.startswith(key + '=') for line in lines):
            lines = [replacement if line.startswith(key + '=') else line for line in lines]
        else:
            lines.append(replacement)
    temporary = safe_path(path.with_name('.env-commerce-new'))
    require(not temporary.exists(), 'ENV_TEMP_COLLISION')
    private_copy(path, run / backup_name, private_source=True)
    private_write(temporary, '\n'.join(lines) + '\n')
    require(path.read_text() == before, 'ENV_CHANGED_DURING_INSTALL')
    require_local_project(path.parent)
    temporary.replace(path)


def finish_installation(run, state, plan, before_checksum, checksum_query):
    require_local_commerce(ROOT)
    safe_path(run)
    state['phase'] = 'remove_ddl_privileges'
    for table in TABLES:
        rights = 'CREATE,ALTER,REFERENCES' if table == 'commerce_orders' else 'CREATE,ALTER'
        sql('REVOKE ' + rights + " ON `homya_local`.`" + table + "` FROM '" + ACCOUNT + "'@'localhost'")
        state['sql_mutations'] += 1
    state['phase'] = 'initial_inventory'
    environment = {'HOMYA_CATALOGUE_SOURCE': 'files'}
    php(['homya:commerce-inventory', '--apply', '--sha256=' + plan['inventory_sha256']], environment)
    state['inventory_installed'] = True
    verify_installed(run, state, plan, before_checksum, checksum_query)


def verify_installed(run, state, plan, before_checksum, checksum_query):
    require_local_commerce(ROOT)
    safe_path(run)
    state['phase'] = 'verification'
    counts = sql('SELECT COUNT(*),SUM(quantity),SUM(reserved),SUM(sale_enabled),MIN(quantity),MAX(quantity) FROM homya_local.commerce_inventory')
    require(counts == '3709\t3709000\t0\t3709\t1000\t1000', 'INVENTORY_COUNTS_MISMATCH')
    require(all(sql('SELECT COUNT(*) FROM homya_local.`' + table + '`') == '0' for table in TABLES[1:]), 'UNEXPECTED_COMMERCIAL_DATA')
    require(sql(checksum_query) == before_checksum, 'EXISTING_DATA_CHANGED')
    require(digest(ROOT / 'storage/app/catalogue/index.json') == read_private_json(run / 'before.json')['catalogue_index_sha256'], 'CATALOGUE_FILE_CHANGED')
    grantee = "CONCAT(CHAR(39),'" + ACCOUNT + "',CHAR(39),'@',CHAR(39),'localhost',CHAR(39))"
    privileges = sql('SELECT TABLE_NAME,PRIVILEGE_TYPE FROM information_schema.TABLE_PRIVILEGES WHERE GRANTEE=' + grantee + ' ORDER BY TABLE_NAME,PRIVILEGE_TYPE')
    expected = sorted((table, privilege) for table in TABLES for privilege in (['SELECT', 'INSERT', 'UPDATE'] if table in TABLES[:2] else ['SELECT', 'INSERT']))
    require(sorted(tuple(line.split('\t')) for line in privileges.splitlines()) == expected, 'PRIVILEGE_MISMATCH')
    require(sql('SELECT COUNT(*) FROM information_schema.USER_PRIVILEGES WHERE GRANTEE=' + grantee + " AND PRIVILEGE_TYPE<>'USAGE'") == '0', 'GLOBAL_PRIVILEGES_PRESENT')
    state['phase'] = 'activate_stock_reading'
    env = ROOT / '.env'
    contents = env.read_text()
    require(contents.count('HOMYA_INVENTORY_ENABLED=false') == 1, 'INVENTORY_FLAG_CHANGED')
    update_env(env, {'HOMYA_INVENTORY_ENABLED': 'true'}, run, backup_name='inventory-env-before')
    state.update(status='complete', existing_data_checksums_preserved=True, products=3709,
                 declared_initial_units=3709000, orders=0, payment_events=0, privileges=expected,
                 checkout_enabled=False, live_payments=False, backup_restore_tested=False)
    private_json(run / 'result.json', state)
    print(json.dumps({'status': 'complete', 'decision': state['decision'], 'products': 3709,
                      'initial_units_per_product': 1000, 'checkout_enabled': False,
                      'existing_data_preserved': True, 'private_evidence': str(run)}, ensure_ascii=False))


def apply(args, plan):
    require(args.schema_sha256 == plan['schema_sha256'] and args.inventory_sha256 == plan['inventory_sha256']
            and args.plan_sha256 == plan['plan_sha256'], 'REVIEW_DIGEST_MISMATCH')
    require_local_project(ROOT)
    require(not (ROOT / 'bootstrap/cache/config.php').exists(), 'CONFIG_CACHE_PRESENT')
    require(Path(SOCKET).exists() and not (ROOT / '.env').is_symlink(), 'LOCAL_FILES_UNAVAILABLE')
    parent = safe_path(ROOT / 'storage/app/private/commerce/installation')
    parent.mkdir(parents=True, mode=0o700, exist_ok=True)
    lock = parent / 'installation.lock'
    private_write(lock, 'Preserve this lock on any failure.\n')
    run = parent / datetime.datetime.now(datetime.timezone.utc).strftime('%Y%m%dT%H%M%SZ')
    run.mkdir(mode=0o700)
    state = dict(plan, status='started', sql_mutations=0, account_created=False, tables_created=False, inventory_installed=False)
    phase = 'record_authorization'
    try:
        record_decision(run, state)
        private_json(run / 'authorization.json', state)
        phase = 'preflight'
        identity = sql('SELECT VERSION(), @@port, @@datadir, @@socket, DATABASE() FROM DUAL')
        values = identity.split('\t')
        require(values[0].startswith('10.4.28-MariaDB') and values[1] == '3307'
                and Path(values[2]).resolve() == Path(DATADIR).resolve()
                and Path(values[3]).resolve() == Path(SOCKET).resolve(), 'WRONG_XAMPP_INSTANCE')
        require(sql("SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME='homya_local'") == '1', 'DATABASE_MISSING')
        require(sql("SELECT COUNT(*) FROM mysql.user WHERE User='" + ACCOUNT + "'") == '0', 'ACCOUNT_COLLISION')
        existing = sql('SHOW TABLES FROM homya_local').splitlines()
        require(existing and all(re.fullmatch(r'[a-zA-Z0-9_]+', name) for name in existing), 'UNEXPECTED_EXISTING_TABLES')
        require(not set(existing).intersection(TABLES), 'TABLE_COLLISION')
        checksum_query = checksum_statement(existing)
        before_checksum = sql(checksum_query)
        require('NULL' not in before_checksum, 'CHECKSUM_UNAVAILABLE')
        private_json(run / 'before.json', {'tables': existing, 'checksums': before_checksum,
                     'catalogue_index_sha256': digest(ROOT / 'storage/app/catalogue/index.json')})
        phase = 'backup'
        backup = run / 'homya-local-before.sql'
        require_local_project(ROOT)
        descriptor = os.open(safe_path(backup), os.O_WRONLY | os.O_CREAT | os.O_EXCL | os.O_NOFOLLOW, 0o600)
        with os.fdopen(descriptor, 'wb') as stream:
            file_metadata(os.fstat(stream.fileno()), private=True)
            result = subprocess.run([DUMP, '--no-defaults', '--protocol=SOCKET', '--socket=' + SOCKET,
                                     '--user=root', '--skip-password', '--single-transaction', '--skip-lock-tables',
                                     '--hex-blob', '--routines', '--triggers', 'homya_local'],
                                    stdout=stream, stderr=subprocess.PIPE, timeout=300,
                                    shell=False, env=command_environment())
        require(result.returncode == 0 and not result.stderr and backup.stat().st_size > 0, 'BACKUP_FAILED_DETAILS_WITHHELD')
        state['database_backup_sha256'] = digest(backup)
        phase = 'dedicated_account'
        password = secrets.token_urlsafe(40)
        sql("CREATE USER '" + ACCOUNT + "'@'localhost' IDENTIFIED BY '" + password + "'")
        state['sql_mutations'] += 1
        state['account_created'] = True
        for table in TABLES:
            privileges = ['SELECT', 'INSERT', 'CREATE', 'ALTER']
            if table in ('commerce_inventory', 'commerce_orders'):
                privileges.append('UPDATE')
            if table == 'commerce_orders':
                privileges.append('REFERENCES')
            sql('GRANT ' + ','.join(privileges) + " ON `homya_local`.`" + table + "` TO '" + ACCOUNT + "'@'localhost'")
            state['sql_mutations'] += 1
        phase = 'private_configuration'
        update_env(ROOT / '.env', {'HOMYA_COMMERCE_DB_HOST': '127.0.0.1', 'HOMYA_COMMERCE_DB_PORT': '3307',
                   'HOMYA_COMMERCE_DB_SOCKET': SOCKET, 'HOMYA_COMMERCE_DB_DATABASE': 'homya_local',
                   'HOMYA_COMMERCE_DB_USERNAME': ACCOUNT, 'HOMYA_COMMERCE_DB_PASSWORD': password,
                   'HOMYA_INVENTORY_ENABLED': 'false', 'HOMYA_CHECKOUT_ENABLED': 'false',
                   'HOMYA_COMMERCE_LEGAL_READY': 'false', 'HOMYA_STRIPE_MODE': 'test'}, run)
        phase = 'schema'
        php(['homya:commerce-schema', '--apply', '--sha256=' + plan['schema_sha256']])
        state['tables_created'] = True
        phase = 'finish_installation'
        finish_installation(run, state, plan, before_checksum, checksum_query)
    except Exception as error:
        reason = str(error) if re.fullmatch(r'[A-Z_]+', str(error)) else type(error).__name__
        state.update(status='stopped', phase=state.get('phase', phase), reason=reason)
        private_json(run / 'stopped.json', state)
        print(json.dumps({'status': 'stopped', 'phase': phase, 'private_evidence': str(run),
                          'automatic_rollback': False}, ensure_ascii=False))
        raise SystemExit(1)


def resume_empty(args, plan):
    require(args.schema_sha256 == plan['schema_sha256'] and args.inventory_sha256 == plan['inventory_sha256']
            and args.plan_sha256 == plan['plan_sha256'], 'REVIEW_DIGEST_MISMATCH')
    require_local_commerce(ROOT)
    old = safe_path(ROOT / 'storage/app/private/commerce/installation/20261009T113810Z')
    stopped = read_private_json(old / 'stopped.json')
    require(stopped['phase'] == 'schema' and stopped['account_created'] and not stopped['tables_created']
            and not stopped['inventory_installed'] and stopped['schema_sha256'] == plan['schema_sha256']
            and stopped['inventory_sha256'] == plan['inventory_sha256'], 'UNEXPECTED_STOPPED_STATE')
    require(digest(old / 'homya-local-before.sql') == stopped['database_backup_sha256'], 'BACKUP_CHANGED')
    before = read_private_json(old / 'before.json')
    checksum_query = checksum_statement(before['tables'])
    require(sql(checksum_query) == before['checksums'], 'EXISTING_DATA_CHANGED')
    identity = sql('SELECT VERSION(), @@port, @@datadir, @@socket FROM DUAL').split('\t')
    require(identity[0].startswith('10.4.28-MariaDB') and identity[1] == '3307'
            and Path(identity[2]).resolve() == Path(DATADIR).resolve()
            and Path(identity[3]).resolve() == Path(SOCKET).resolve(), 'WRONG_XAMPP_INSTANCE')
    current = sql("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA='homya_local' AND TABLE_NAME LIKE 'commerce_%' ORDER BY TABLE_NAME")
    require(current.splitlines() == TABLES[:2], 'UNEXPECTED_PARTIAL_TABLES')
    require(all(sql('SELECT COUNT(*) FROM homya_local.`' + table + '`') == '0' for table in TABLES[:2]), 'TABLE_NOT_EMPTY')
    require(sql("SELECT COUNT(*) FROM mysql.user WHERE User='" + ACCOUNT + "' AND Host='localhost'") == '1', 'ACCOUNT_MISSING')
    require(digest(ROOT / 'storage/app/catalogue/index.json') == before['catalogue_index_sha256'], 'CATALOGUE_FILE_CHANGED')
    flags = (ROOT / '.env').read_text()
    require('HOMYA_INVENTORY_ENABLED=false' in flags and 'HOMYA_CHECKOUT_ENABLED=false' in flags
            and 'HOMYA_COMMERCE_LEGAL_READY=false' in flags, 'CHECKOUT_FLAGS_CHANGED')
    run = old / ('explicit-empty-schema-completion-' + datetime.datetime.now(datetime.timezone.utc).strftime('%Y%m%dT%H%M%SZ'))
    run.mkdir(mode=0o700)  # Exclusive creation: a second failure cannot automatically retry.
    private_copy(old / 'before.json', run / 'before.json')
    state = dict(stopped, status='resuming', phase='record_completion_plan', plan_sha256=plan['plan_sha256'])
    try:
        require_local_commerce(ROOT)
        regular_file(REGISTRY)
        original_registry = REGISTRY.read_text()
        private_copy(REGISTRY, run / 'registry-before.md')
        entry = ('\n\nComplément HOMYA à DECISION-061 — 09/10/2026 : arrêt conservateur après les deux CREATE vides. '
                 'Laravel nécessite ALTER pour les index, absent du plan initial. La reprise explicitement préparée '
                 'sous l’autorisation existante termine seulement les quatre tables et leurs index/FK, sans DROP, '
                 'recréation de compte ou modification d’archives. ALTER temporaire limité aux quatre noms ; '
                 'retrait CREATE/ALTER/REFERENCES avant import. Plan révisé SHA256 ' + plan['plan_sha256'] +
                 '. État exact et sauvegarde vérifiés avant la reprise ; résultat à compléter.\n')
        require_local_commerce(ROOT)
        append_registry(original_registry, entry)
        state['phase'] = 'temporary_alter'
        for table in TABLES:
            sql("GRANT CREATE,ALTER ON `homya_local`.`" + table + "` TO '" + ACCOUNT + "'@'localhost'")
        state['phase'] = 'complete_exact_empty_schema'
        php(['homya:commerce-schema', '--apply', '--resume-empty-schema', '--sha256=' + plan['schema_sha256']])
        state['tables_created'] = True
        finish_installation(run, state, plan, before['checksums'], checksum_query)
    except Exception as error:
        reason = str(error) if re.fullmatch(r'[A-Z_]+', str(error)) else type(error).__name__
        state.update(status='stopped', reason=reason)
        private_json(run / 'stopped.json', state)
        print(json.dumps({'status': 'stopped', 'phase': state['phase'], 'private_evidence': str(run)}))
        raise SystemExit(1)


def finish_verified_installation(args, plan):
    require(args.schema_sha256 == plan['schema_sha256'] and args.inventory_sha256 == plan['inventory_sha256']
            and args.plan_sha256 == plan['plan_sha256'], 'REVIEW_DIGEST_MISMATCH')
    require_local_commerce(ROOT)
    previous = safe_path(ROOT / 'storage/app/private/commerce/installation/20261009T113810Z/explicit-empty-schema-completion-20261009T114256Z')
    state = read_private_json(previous / 'stopped.json')
    require(state['phase'] == 'verification' and state['tables_created'] and state['inventory_installed']
            and state['reason'] == 'PRIVILEGE_MISMATCH', 'UNEXPECTED_INSTALLED_STATE')
    # SQL collation orders identifiers differently from Python; compare a normalized exact privilege set.
    run = previous / 'verification-completed'
    run.mkdir(mode=0o700)
    private_copy(previous / 'before.json', run / 'before.json')
    before = read_private_json(run / 'before.json')
    checksum_query = checksum_statement(before['tables'])
    verify_installed(run, state, plan, before['checksums'], checksum_query)


def main():
    os.umask(0o077)
    parser = argparse.ArgumentParser()
    parser.add_argument('--apply', action='store_true')
    parser.add_argument('--resume-empty-schema', action='store_true')
    parser.add_argument('--verify-installed', action='store_true')
    parser.add_argument('--schema-sha256')
    parser.add_argument('--inventory-sha256')
    parser.add_argument('--plan-sha256')
    args = parser.parse_args()
    plan = {'target': 'XAMPP MariaDB homya_local', 'tables': TABLES, 'account': ACCOUNT,
            'schema_sha256': digest(SCHEMA), 'inventory_sha256': digest(INVENTORY), 'plan_sha256': digest(PLAN)}
    if args.apply and args.verify_installed:
        finish_verified_installation(args, plan)
    elif args.apply and args.resume_empty_schema:
        resume_empty(args, plan)
    elif args.apply:
        apply(args, plan)
    else:
        print(json.dumps(plan, ensure_ascii=False))


if __name__ == '__main__':
    main()
