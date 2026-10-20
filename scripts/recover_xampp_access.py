"""One-time administrator operation for HOMYA recovery; invoked via macOS consent.

Cold backup first. A private Unix socket and --skip-networking bound maintenance
to the local administrator. Existing accounts, passwords and databases are kept.
"""
from pathlib import Path
import hashlib
import json
import os
import pwd
import re
import shlex
import signal
import subprocess
import tempfile
import time

ROOT = Path('/Applications/XAMPP/xamppfiles/htdocs/HOMYA')
XAMPP = Path('/Applications/XAMPP/xamppfiles')
RUN = ROOT / 'storage/app/private/database-backups/recovery-20261019'
DATABASES = ('homya_local', 'homya_test', 'homya_restore_op07')
MYSQL = str(XAMPP / 'bin/mysql')


def execute(args, **kwargs):
    result = subprocess.run(args, capture_output=True, text=True, timeout=120, **kwargs)
    if result.returncode:
        # Never echo queries, credentials, account hashes or SQL error payloads.
        error = re.search(r'ERROR (\d+) \(([A-Z0-9]+)\)', result.stderr)
        diagnostic = ' SQL ' + error[1] + '/' + error[2] if error else ''
        raise RuntimeError('Command failed: ' + Path(args[0]).name + ' (code ' + str(result.returncode) + ')' + diagnostic)
    return result.stdout


def main():
    if os.geteuid() != 0:
        raise RuntimeError('macOS administrator access required')
    owner = ROOT.stat()
    secrets = json.loads((RUN / 'accounts.json').read_text())
    if set(secrets) != {'homya_recovery_schema', 'homya_local_commerce', 'homya_local_reader', 'homya_local_app'}:
        raise RuntimeError('Account scope mismatch')
    if any(not re.fullmatch('[a-f0-9]{64}', v) for v in secrets.values()):
        raise RuntimeError('Invalid generated credentials')
    backup = RUN / ('xampp-mariadb-cold-' + time.strftime('%Y%m%dT%H%M%S', time.gmtime()) + '.tar')
    if (RUN / 'access-recovered.json').exists():
        raise RuntimeError('Recovery operation already attempted; inspect before retry')
    prior_path = RUN / 'access-progress.json'
    if prior_path.exists():
        prior = json.loads(prior_path.read_text())
        if prior.get('phase') != 'cold_backup':
            raise RuntimeError('Previous SQL attempt requires inspection')
    config = XAMPP / 'etc/my.cnf'
    before_config = config.read_bytes()
    record = {'root_password_changed': False, 'targets': DATABASES, 'phase': 'starting'}

    def save():
        path = RUN / 'access-progress.json'
        path.write_text(json.dumps(record, indent=2))
        os.chown(path, owner.st_uid, owner.st_gid)
        os.chmod(path, 0o600)

    maintenance = None
    stopped = False
    try:
        save()
        execute([str(XAMPP / 'bin/mysql.server'), 'stop'])
        stopped = True
        def processes():
            result = []
            for line in execute(['/bin/ps', '-axo', 'pid=,command=']).splitlines():
                match = re.match(r'\s*(\d+)\s+(.*)', line)
                if not match:
                    continue
                if not any(path in match[2] for path in (str(XAMPP / 'sbin/mysqld'), str(XAMPP / 'bin/mysqld_safe'))):
                    continue
                args = shlex.split(match[2])
                daemon = bool(args and args[0] == str(XAMPP / 'sbin/mysqld'))
                supervisor = len(args) > 1 and args[1] == str(XAMPP / 'bin/mysqld_safe')
                if (daemon or supervisor) and '--datadir=' + str(XAMPP / 'var/mysql') in args:
                    result.append((int(match[1]), supervisor))
            return result
        # A missing PID file is not evidence of shutdown. Stop only the exact
        # XAMPP supervisor and daemon bound to this datadir, never a foreign server.
        for pid, supervisor in processes():
            if supervisor:
                os.kill(pid, signal.SIGTERM)
        for pid, supervisor in processes():
            if not supervisor:
                os.kill(pid, signal.SIGTERM)
        for _ in range(300):
            if not processes():
                break
            time.sleep(0.2)
        else:
            raise RuntimeError('XAMPP processes still running; backup refused')
        holders = subprocess.run(['/usr/sbin/lsof', '-t', str(XAMPP / 'var/mysql/ibdata1'), str(XAMPP / 'var/mysql/aria_log_control')], capture_output=True, text=True)
        if holders.stdout.strip():
            raise RuntimeError('Database files still open; backup refused')
        record['phase'] = 'cold_backup'
        save()
        execute(['/usr/bin/tar', '-cpf', str(backup), '-C', str(XAMPP), 'var/mysql', 'etc/my.cnf'])
        os.chown(backup, owner.st_uid, owner.st_gid)
        os.chmod(backup, 0o600)
        record['cold_backup_bytes'] = backup.stat().st_size
        record['cold_backup_file'] = str(backup)
        record['cold_backup_sha256'] = hashlib.sha256(backup.read_bytes()).hexdigest()
        if (RUN / 'my.cnf.before').read_bytes() != before_config:
            raise RuntimeError('Initial configuration changed before maintenance')
        os.chown(RUN / 'my.cnf.before', owner.st_uid, owner.st_gid)
        os.chmod(RUN / 'my.cnf.before', 0o600)
        save()
        private = Path(tempfile.mkdtemp(prefix='homya-db-maintenance-', dir='/private/tmp'))
        record['maintenance_directory'] = str(private)
        save()
        mysql_user = pwd.getpwnam('_mysql')
        os.chown(private, mysql_user.pw_uid, mysql_user.pw_gid)
        os.chmod(private, 0o700)
        socket = private / 'mysql.sock'
        log = (RUN / 'maintenance-process.log').open('a')
        os.chown(RUN / 'maintenance-process.log', owner.st_uid, owner.st_gid)
        os.chmod(RUN / 'maintenance-process.log', 0o600)
        maintenance = subprocess.Popen([
            str(XAMPP / 'sbin/mysqld'), '--defaults-file=' + str(config),
            '--user=mysql', '--skip-grant-tables', '--skip-networking',
            '--socket=' + str(socket), '--pid-file=' + str(private / 'mysql.pid'),
            '--log-error=' + str(private / 'server.err'), '--max-allowed-packet=16M',
        ], stdout=log, stderr=log)
        for _ in range(150):
            if socket.exists():
                break
            if maintenance.poll() is not None:
                raise RuntimeError('Maintenance server failed to start')
            time.sleep(0.2)
        else:
            raise RuntimeError('Maintenance socket timeout')
        client = [MYSQL, '--no-defaults', '--protocol=socket', '--socket=' + str(socket), '--user=root', '--batch', '--skip-column-names']

        def sql(query):
            return execute(client, input=query)

        identity = sql('SELECT VERSION(), @@datadir, @@socket, @@skip_networking;').strip().split('\t')
        if len(identity) != 4 or identity[0] != '10.4.28-MariaDB' or identity[1] != str(XAMPP / 'var/mysql') + '/' or identity[2] != str(socket) or identity[3] != '1':
            raise RuntimeError('Unexpected maintenance instance')
        names = ','.join("'" + name + "'" for name in DATABASES)
        if sql('SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME IN (' + names + ');').strip():
            raise RuntimeError('Existing HOMYA schema encountered; no overwrite allowed')
        if sql("SELECT User FROM mysql.user WHERE User IN (" + ','.join("'" + name + "'" for name in secrets) + ');').strip():
            raise RuntimeError('Existing HOMYA account encountered')
        root_hash_query = "SELECT * FROM mysql.user WHERE User='root' ORDER BY Host;"
        root_before = hashlib.sha256(sql(root_hash_query).encode()).hexdigest()
        # Re-enable privilege tables before using standard account-management SQL.
        statements = ['FLUSH PRIVILEGES']
        for database in DATABASES:
            statements.append('CREATE DATABASE `' + database + '` CHARACTER SET utf8mb4 COLLATE utf8mb4_bin')
        for user, password in secrets.items():
            statements.append("CREATE USER '" + user + "'@'localhost' IDENTIFIED BY '" + password + "'")
        for database in DATABASES:
            statements.append("GRANT SELECT,INSERT,UPDATE,DELETE,CREATE,ALTER,INDEX,REFERENCES,LOCK TABLES ON `" + database + "`.* TO 'homya_recovery_schema'@'localhost' WITH GRANT OPTION")
        root_after = hashlib.sha256(sql(';\n'.join(statements) + ';\n' + root_hash_query).encode()).hexdigest()
        if root_after != root_before:
            raise RuntimeError('Root account changed unexpectedly')
        record['root_accounts_sha256'] = root_before
        record['phase'] = 'accounts_created'
        save()
        maintenance.send_signal(signal.SIGTERM)
        maintenance.wait(timeout=90)
        maintenance = None
        # Keep XAMPP's existing port. Only packet size and loopback bind change.
        text = before_config.decode()
        start = text.index('[mysqld]')
        next_section = text.find('\n[', start + 1)
        if next_section < 0:
            next_section = len(text)
        section = text[start:next_section]
        section, count = re.subn(r'(?m)^max_allowed_packet\s*=.*$', 'max_allowed_packet=16M', section)
        if count != 1:
            raise RuntimeError('Unexpected XAMPP packet configuration')
        if re.search(r'(?m)^bind-address\s*=', section):
            section = re.sub(r'(?m)^bind-address\s*=.*$', 'bind-address=127.0.0.1', section)
        else:
            section = section.replace('[mysqld]', '[mysqld]\nbind-address=127.0.0.1', 1)
        if config.read_bytes() != before_config:
            raise RuntimeError('XAMPP configuration changed concurrently')
        config.write_text(text[:start] + section + text[next_section:])
        record['configuration_backup'] = str(RUN / 'my.cnf.before')
        record['phase'] = 'normal_restart'
        save()
        execute([str(XAMPP / 'bin/mysql.server'), 'start'])
        stopped = False
        schema_client = [MYSQL, '--defaults-file=' + str(RUN / 'schema.cnf'), '--batch', '--skip-column-names']
        result = execute(schema_client, input='SELECT VERSION(),@@port,@@socket,@@datadir,@@max_allowed_packet,CURRENT_USER(),@@skip_networking;')
        record['normal_server'] = result.strip().split('\t')
        if len(record['normal_server']) != 7 or record['normal_server'][5:] != ['homya_recovery_schema@localhost', '0']:
            raise RuntimeError('Normal authentication mode not confirmed')
        record['phase'] = 'complete'
        save()
        final = RUN / 'access-recovered.json'
        final.write_text(json.dumps(record, indent=2))
        os.chown(final, owner.st_uid, owner.st_gid)
        os.chmod(final, 0o600)
        print('Acces HOMYA cree. Sauvegarde a froid verifiee. Authentification normale reactivee. Mot de passe root conserve.')
    finally:
        if 'private' in locals() and (private / 'server.err').exists():
            diagnostic = RUN / ('maintenance-server-' + private.name + '.log')
            diagnostic.write_bytes((private / 'server.err').read_bytes())
            os.chown(diagnostic, owner.st_uid, owner.st_gid)
            os.chmod(diagnostic, 0o600)
        if maintenance is not None and maintenance.poll() is None:
            maintenance.send_signal(signal.SIGTERM)
            maintenance.wait(timeout=90)
        if stopped:
            subprocess.run([str(XAMPP / 'bin/mysql.server'), 'start'], capture_output=True, timeout=120)


if __name__ == '__main__':
    main()
