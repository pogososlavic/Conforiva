"""Local-operation regression checks: fixtures and subprocess mocks only.

Run: python3 -m unittest discover -s scripts -p local_operation_security_test.py
No actual .env, XAMPP, MariaDB, PHP process or scheduler is accessed or started.
"""
import contextlib
import io
import json
import os
import tempfile
import unittest
from pathlib import Path
from types import SimpleNamespace
from unittest.mock import patch

import install_admin_management as admin
import install_commerce_preview as preview
import local_operation_security as security
import start_commerce_scheduler as scheduler


class LocalOperationSecurityTests(unittest.TestCase):
    def setUp(self):
        self.temporary = tempfile.TemporaryDirectory(dir='/private/tmp')
        self.addCleanup(self.temporary.cleanup)
        self.base = Path(self.temporary.name)
        self.source = self.project('source')
        self.target = self.project('htdocs/target')
        self.runtime = self.base / 'runtime'

    def project(self, name):
        project = self.base / name
        (project / 'bootstrap/cache').mkdir(parents=True)
        (project / 'app/Console/Commands').mkdir(parents=True)
        (project / 'artisan').write_text('fixture-only')
        (project / 'app/Console/Commands/ExpireBankTransfers.php').write_text('fixture-only')
        (project / '.env').write_text(
            'APP_ENV=local\nAPP_URL=http://127.0.0.1:8000\nAPP_KEY=fixture-only\n'
            'HOMYA_COMMERCE_DB_DATABASE=homya_local\n'
            'HOMYA_COMMERCE_DB_USERNAME=homya_local_commerce\n'
            'HOMYA_COMMERCE_DB_HOST=127.0.0.1\nHOMYA_COMMERCE_DB_PORT=3307\n'
            'HOMYA_COMMERCE_DB_SOCKET=' + security.XAMPP_SOCKET + '\n'
            'HOMYA_COMMERCE_LEGAL_READY=false\nHOMYA_CHECKOUT_ENABLED=false\n'
        )
        (project / '.env').chmod(0o600)
        return project

    def change(self, project, key, value):
        path = project / '.env'
        lines = [line for line in path.read_text().splitlines() if not line.startswith(key + '=')]
        path.write_text('\n'.join(lines + [key + '=' + value]) + '\n')

    def test_local_loopback_ports_and_quoted_literals_are_accepted(self):
        for url in sorted(security.LOCAL_URLS):
            with self.subTest(url=url):
                self.change(self.target, 'APP_ENV', '"local" # fixture')
                self.change(self.target, 'APP_URL', "'" + url + "'")
                security.require_local_commerce(self.target)

    def test_production_and_public_origins_are_refused(self):
        for key, value, code in (
            ('APP_ENV', 'production', 'LOCAL_ENV_REQUIRED'),
            ('APP_URL', 'https://homya.eu', 'LOOPBACK_URL_REQUIRED'),
            ('APP_URL', 'http://127.0.0.1:9000', 'LOOPBACK_URL_REQUIRED'),
            ('APP_URL', 'http://127.0.0.1:8000@evil.invalid', 'LOOPBACK_URL_REQUIRED'),
        ):
            with self.subTest(key=key, value=value):
                project = self.project('refuse-' + str(len(list(self.base.iterdir()))))
                self.change(project, key, value)
                with self.assertRaisesRegex(RuntimeError, '^' + code + '$'):
                    security.require_local_project(project)

    def test_duplicate_and_interpolated_environment_keys_are_refused(self):
        path = self.target / '.env'
        original = path.read_text()
        path.write_text(original + 'APP_ENV=local\n')
        with self.assertRaisesRegex(RuntimeError, '^DUPLICATE_ENV_KEY$'):
            security.require_local_project(self.target)
        path.write_text(original)
        self.change(self.target, 'APP_ENV', '${ENVIRONMENT}')
        with self.assertRaisesRegex(RuntimeError, '^ENV_LITERAL_REQUIRED$'):
            security.require_local_project(self.target)

    def test_config_cache_and_custom_cache_path_are_refused(self):
        cache = self.target / 'bootstrap/cache/config.php'
        cache.write_text('fixture-only')
        with self.assertRaisesRegex(RuntimeError, '^CONFIG_CACHE_PRESENT$'):
            security.require_local_project(self.target)
        cache.unlink()
        self.change(self.target, 'APP_CONFIG_CACHE', '/private/tmp/fixture-config.php')
        with self.assertRaisesRegex(RuntimeError, '^CONFIG_CACHE_OVERRIDE_REFUSED$'):
            security.require_local_project(self.target)

    def test_dangling_cache_symlink_is_refused(self):
        (self.target / 'bootstrap/cache/config.php').symlink_to(self.base / 'absent')
        with self.assertRaisesRegex(RuntimeError, '^SYMLINK_REFUSED$'):
            security.require_local_project(self.target)

    def test_environment_and_parent_directory_symlinks_are_refused(self):
        (self.target / '.env').unlink()
        (self.target / '.env').symlink_to(self.source / '.env')
        with self.assertRaisesRegex(RuntimeError, '^SYMLINK_REFUSED$'):
            security.require_local_project(self.target)
        link = self.base / 'linked-project'
        link.symlink_to(self.source, target_is_directory=True)
        with self.assertRaisesRegex(RuntimeError, '^SYMLINK_REFUSED$'):
            security.require_local_project(link)

    def test_group_or_world_writable_ancestor_is_refused(self):
        self.target.parent.chmod(0o777)
        with self.assertRaisesRegex(RuntimeError, '^MUTABLE_PARENT_REFUSED$'):
            security.require_local_project(self.target)

    def test_root_sticky_temporary_ancestor_and_private_descendant_are_accepted(self):
        self.assertTrue(Path('/private/tmp').stat().st_mode & 0o1000)
        security.require_local_project(self.target)

    def test_hardlinked_environment_is_refused(self):
        os.link(self.target / '.env', self.base / 'other-env')
        with self.assertRaisesRegex(RuntimeError, '^HARDLINK_REFUSED$'):
            security.require_local_project(self.target)

    def test_nonprivate_environment_permissions_are_refused(self):
        (self.target / '.env').chmod(0o644)
        with self.assertRaisesRegex(RuntimeError, '^PRIVATE_FILE_REQUIRED$'):
            security.require_local_project(self.target)

    def test_mutable_input_file_and_hardlinked_copy_source_are_refused(self):
        source = self.base / 'source-fixture'
        source.write_text('synthetic')
        source.chmod(0o666)
        with self.assertRaisesRegex(RuntimeError, '^MUTABLE_FILE_REFUSED$'):
            security.private_copy(source, self.base / 'backup-fixture')
        source.chmod(0o644)
        os.link(source, self.base / 'linked-fixture')
        with self.assertRaisesRegex(RuntimeError, '^HARDLINK_REFUSED$'):
            security.private_copy(source, self.base / 'backup-fixture')
        self.assertFalse((self.base / 'backup-fixture').exists())

    def test_every_commerce_target_override_is_refused(self):
        for key, value, code in (
            ('HOMYA_COMMERCE_DB_DATABASE', 'production', 'COMMERCE_DATABASE_REFUSED'),
            ('HOMYA_COMMERCE_DB_USERNAME', 'root', 'COMMERCE_ACCOUNT_REFUSED'),
            ('HOMYA_COMMERCE_DB_HOST', 'db.example.invalid', 'COMMERCE_HOST_REFUSED'),
            ('HOMYA_COMMERCE_DB_SOCKET', '/private/tmp/other.sock', 'COMMERCE_SOCKET_REFUSED'),
            ('HOMYA_COMMERCE_DB_PORT', '3306', 'COMMERCE_PORT_REFUSED'),
            ('DB_URL', 'mysql://fixture@external.invalid/db', 'DATABASE_URL_REFUSED'),
        ):
            with self.subTest(key=key):
                project = self.project('db-refuse-' + str(len(list(self.base.iterdir()))))
                self.change(project, key, value)
                with self.assertRaisesRegex(RuntimeError, '^' + code + '$'):
                    security.require_local_commerce(project)

    def test_private_file_is_mode_600_at_creation_with_exclusive_nofollow_flags(self):
        actual_open = os.open
        observations = []

        def inspect_open(path, flags, mode=0o777):
            descriptor = actual_open(path, flags, mode)
            observations.append((flags, os.fstat(descriptor).st_mode & 0o777))
            return descriptor

        destination = self.base / 'private-fixture'
        with patch.object(security.os, 'open', side_effect=inspect_open):
            security.private_write(destination, 'synthetic-only')
        self.assertEqual(observations[0][1], 0o600)
        self.assertTrue(observations[0][0] & os.O_EXCL)
        self.assertTrue(observations[0][0] & os.O_NOFOLLOW)

    def test_private_copy_does_not_inherit_world_readable_source_permissions(self):
        source = self.source / '.env'
        source.chmod(0o644)
        destination = self.base / 'backup-fixture'
        security.private_copy(source, destination)
        self.assertEqual(destination.stat().st_mode & 0o777, 0o600)
        self.assertEqual(destination.read_text(), source.read_text())

    def test_private_output_never_overwrites_an_existing_file_or_symlink(self):
        destination = self.base / 'private-fixture'
        destination.write_text('preserved')
        with self.assertRaises(FileExistsError):
            security.private_write(destination, 'changed')
        self.assertEqual(destination.read_text(), 'preserved')
        destination.unlink()
        destination.symlink_to(self.base / 'missing')
        with self.assertRaisesRegex(RuntimeError, '^SYMLINK_REFUSED$'):
            security.private_write(destination, 'changed')

    def test_preview_prepare_and_apply_guard_both_copies_in_both_modes(self):
        for project_name in ('source', 'target'):
            for mode in (False, True):
                for operation in ('prepare', 'apply'):
                    with self.subTest(project=project_name, mode=mode, operation=operation):
                        project = getattr(self, project_name)
                        self.change(project, 'APP_ENV', 'production')
                        with patch.object(preview, 'ROOT', self.source), patch.object(preview, 'TARGET', self.target), \
                                patch.object(preview, 'names') as names, patch.object(preview, 'private_copy') as copy:
                            with self.assertRaisesRegex(RuntimeError, '^LOCAL_ENV_REQUIRED$'):
                                if operation == 'prepare':
                                    preview.prepare(mode)
                                else:
                                    preview.apply('fixture-digest', mode)
                            names.assert_not_called()
                            copy.assert_not_called()
                        self.change(project, 'APP_ENV', 'local')

    def test_preview_apply_refuses_cached_source_before_copy(self):
        (self.source / 'bootstrap/cache/config.php').write_text('fixture')
        with patch.object(preview, 'ROOT', self.source), patch.object(preview, 'TARGET', self.target), \
                patch.object(preview, 'private_copy') as copy:
            with self.assertRaisesRegex(RuntimeError, '^CONFIG_CACHE_PRESENT$'):
                preview.apply('not-reached')
            copy.assert_not_called()

    def test_preview_plan_paths_reject_traversal_absolute_and_symlink(self):
        for name in ('../outside', '/private/tmp/outside'):
            with self.assertRaisesRegex(RuntimeError, '^UNSAFE_PLAN_PATH$'):
                preview.file_path(self.target, name)
        (self.target / 'routes').symlink_to(self.source, target_is_directory=True)
        with self.assertRaisesRegex(RuntimeError, '^SYMLINK_REFUSED$'):
            preview.file_path(self.target, 'routes/web.php')

    def test_preview_apply_preserves_other_env_keys_and_private_backup(self):
        self.change(self.source, 'HOMYA_INVENTORY_ENABLED', 'true')
        original = (self.target / '.env').read_text()
        plan_file = self.source / 'output/qa-commerce/preview-installation-plan.json'
        plan_file.parent.mkdir(parents=True)
        (self.base / 'homya-local').mkdir(mode=0o700)
        plan_file.write_text(json.dumps({
            'target': str(self.target), 'open_deferred': False, 'files': [],
            'env_keys': ['HOMYA_INVENTORY_ENABLED'],
            'source_env_sha256': preview.digest(self.source / '.env'),
            'target_env_sha256': preview.digest(self.target / '.env'),
        }))
        with patch.object(preview, 'ROOT', self.source), patch.object(preview, 'TARGET', self.target), \
                patch.object(preview, 'PLAN', plan_file), contextlib.redirect_stdout(io.StringIO()) as output:
            preview.apply(preview.digest(plan_file))
        result = json.loads(output.getvalue())
        backup = Path(result['backup'])
        self.assertEqual(backup.stat().st_mode & 0o777, 0o700)
        self.assertEqual((backup / '.env-before').read_text(), original)
        self.assertEqual((backup / '.env-before').stat().st_mode & 0o777, 0o600)
        self.assertEqual((self.target / '.env').stat().st_mode & 0o777, 0o600)
        self.assertEqual((self.target / '.env').read_text(), original + 'HOMYA_INVENTORY_ENABLED=true\n')

    def test_admin_refuses_nonlocal_before_any_command_or_sql(self):
        self.change(self.source, 'APP_ENV', 'production')
        with patch.object(admin, 'ROOT', self.source), patch.object(admin, 'TARGET', self.target), \
                patch.object(admin, 'run') as run, patch.object(admin, 'sql') as sql:
            for operation in (admin.prepare, lambda: admin.apply('fixture')):
                with self.assertRaisesRegex(RuntimeError, '^LOCAL_ENV_REQUIRED$'):
                    operation()
            run.assert_not_called()
            sql.assert_not_called()

    def test_admin_checks_served_copy_before_any_sql(self):
        self.change(self.target, 'APP_URL', 'https://homya.eu')
        with patch.object(admin, 'ROOT', self.source), patch.object(admin, 'TARGET', self.target), \
                patch.object(admin, 'sql') as sql:
            with self.assertRaisesRegex(RuntimeError, '^LOOPBACK_URL_REQUIRED$'):
                admin.apply('fixture')
            sql.assert_not_called()

    def test_admin_command_is_bounded_shell_false_and_does_not_inherit_credentials(self):
        result = SimpleNamespace(returncode=0, stdout='fixture-result')
        with patch.dict(os.environ, {'DB_URL': 'fixture-secret', 'MYSQL_PWD': 'fixture-secret',
                                     'APP_ENV': 'production', 'PHPRC': '/private/tmp/unsafe'}), \
                patch.object(admin.subprocess, 'run', return_value=result) as run:
            self.assertEqual(admin.run(['/usr/bin/true']), 'fixture-result')
        options = run.call_args.kwargs
        self.assertIs(options['shell'], False)
        self.assertEqual(options['timeout'], 60)
        self.assertFalse({'DB_URL', 'MYSQL_PWD', 'APP_ENV', 'PHPRC'} & options['env'].keys())

    def test_admin_timeout_and_stderr_are_never_disclosed(self):
        with patch.object(admin.subprocess, 'run', side_effect=admin.subprocess.TimeoutExpired('fixture-secret', 60)):
            with self.assertRaisesRegex(RuntimeError, '^COMMAND_TIMEOUT$'):
                admin.run(['fixture-only'])
        with patch.object(admin.subprocess, 'run', return_value=SimpleNamespace(returncode=1, stderr='fixture-secret', stdout='fixture-secret')):
            with self.assertRaisesRegex(RuntimeError, '^COMMAND_FAILED$'):
                admin.run(['fixture-only'])

    def test_scheduler_start_refuses_nonlocal_before_status_or_subprocess(self):
        self.change(self.target, 'APP_ENV', 'production')
        with patch.object(scheduler, 'TARGET', self.target), patch('sys.argv', ['scheduler', '--start']), \
                patch.object(scheduler, 'status') as status, patch.object(scheduler.subprocess, 'run') as run, \
                patch.object(scheduler.subprocess, 'Popen') as popen:
            with self.assertRaisesRegex(RuntimeError, '^LOCAL_ENV_REQUIRED$'):
                scheduler.main()
            status.assert_not_called()
            run.assert_not_called()
            popen.assert_not_called()

    def test_scheduler_start_refuses_database_override_before_any_command(self):
        self.change(self.target, 'HOMYA_COMMERCE_DB_DATABASE', 'production')
        with patch.object(scheduler, 'TARGET', self.target), patch('sys.argv', ['scheduler', '--start']), \
                patch.object(scheduler.subprocess, 'run') as run, patch.object(scheduler.subprocess, 'Popen') as popen:
            with self.assertRaisesRegex(RuntimeError, '^COMMERCE_DATABASE_REFUSED$'):
                scheduler.main()
            run.assert_not_called()
            popen.assert_not_called()

    def test_scheduler_rechecks_environment_before_spawn(self):
        def expired_fixture(*args, **kwargs):
            self.change(self.target, 'APP_ENV', 'production')
            return SimpleNamespace(returncode=0)

        with patch.object(scheduler, 'TARGET', self.target), patch.object(scheduler, 'RUNTIME', self.runtime), \
                patch('sys.argv', ['scheduler', '--start']), patch.object(scheduler, 'status', return_value=None), \
                patch.object(scheduler.os, 'umask'), patch.object(scheduler.subprocess, 'run', side_effect=expired_fixture), \
                patch.object(scheduler.subprocess, 'Popen') as popen:
            with self.assertRaisesRegex(RuntimeError, '^LOCAL_ENV_REQUIRED$'):
                scheduler.main()
            popen.assert_not_called()

    def test_scheduler_start_uses_private_files_and_clean_command_environment(self):
        process = SimpleNamespace(pid=12345, poll=lambda: None)
        with patch.dict(os.environ, {'APP_ENV': 'production', 'APP_CONFIG_CACHE': '/fixture/cache',
                                     'HOMYA_COMMERCE_DB_DATABASE': 'external', 'DB_URL': 'fixture-secret',
                                     'PHPRC': '/fixture/php', 'LD_PRELOAD': '/fixture/loader'}), \
                patch.object(scheduler, 'TARGET', self.target), patch.object(scheduler, 'RUNTIME', self.runtime), \
                patch('sys.argv', ['scheduler', '--start']), patch.object(scheduler.os, 'umask'), \
                patch.object(scheduler, 'status', side_effect=[None, process.pid]), patch.object(scheduler.time, 'sleep'), \
                patch.object(scheduler.subprocess, 'run', return_value=SimpleNamespace(returncode=0)) as run, \
                patch.object(scheduler.subprocess, 'Popen', return_value=process) as popen:
            with contextlib.redirect_stdout(io.StringIO()) as output:
                scheduler.main()
        self.assertTrue(json.loads(output.getvalue())['running'])
        self.assertEqual(self.runtime.stat().st_mode & 0o777, 0o700)
        for name in ('scheduler.log', 'scheduler.pid'):
            self.assertEqual((self.runtime / name).stat().st_mode & 0o777, 0o600)
        for command in (run, popen):
            self.assertIs(command.call_args.kwargs['shell'], False)
            self.assertEqual(command.call_args.kwargs['env'], security.command_environment())
        self.assertEqual(run.call_args.kwargs['timeout'], 60)

    def test_scheduler_refuses_symlink_log_before_expiration_command(self):
        self.runtime.mkdir(mode=0o700)
        (self.runtime / 'scheduler.log').symlink_to(self.base / 'absent')
        with patch.object(scheduler, 'TARGET', self.target), patch.object(scheduler, 'RUNTIME', self.runtime), \
                patch('sys.argv', ['scheduler', '--start']), patch.object(scheduler, 'status', return_value=None), \
                patch.object(scheduler.os, 'umask'), patch.object(scheduler.subprocess, 'run') as run:
            with self.assertRaisesRegex(RuntimeError, '^SYMLINK_REFUSED$'):
                scheduler.main()
            run.assert_not_called()


if __name__ == '__main__':
    unittest.main()
