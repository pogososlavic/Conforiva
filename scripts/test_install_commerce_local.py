"""Installer guards: no SQL, account creation or real registry access."""
import contextlib
import io
import os
import tempfile
import unittest
from pathlib import Path
from types import SimpleNamespace
from unittest.mock import patch
import install_commerce_local as installer


class InstallerGuards(unittest.TestCase):
    def setUp(self):
        self.temporary = tempfile.TemporaryDirectory(dir='/private/tmp')
        self.addCleanup(self.temporary.cleanup)
        self.root = Path(self.temporary.name)
        (self.root / '.env').write_text(
            'APP_ENV=local\nAPP_URL=http://127.0.0.1:8000\n'
            'HOMYA_COMMERCE_DB_HOST=127.0.0.1\nHOMYA_COMMERCE_DB_PORT=3307\n'
            'HOMYA_COMMERCE_DB_SOCKET=' + installer.SOCKET + '\n'
            'HOMYA_COMMERCE_DB_DATABASE=homya_local\n'
            'HOMYA_COMMERCE_DB_USERNAME=homya_local_commerce\n'
        )
        (self.root / '.env').chmod(0o600)
        (self.root / 'artisan').write_text('fixture-only')
        self.root_patch = patch.object(installer, 'ROOT', self.root)
        self.root_patch.start()
        self.addCleanup(self.root_patch.stop)

    def test_production_apply_is_refused_before_authorization_sql_or_env_write(self):
        with tempfile.TemporaryDirectory(dir='/private/tmp') as directory:
            root = Path(directory)
            env = root / '.env'
            original = 'APP_ENV=production\nAPP_URL=https://homya.eu\n'
            env.write_text(original)
            env.chmod(0o600)
            socket = root / 'fixture-socket'
            socket.write_text('fixture-only')
            plan = dict(schema_sha256='schema', inventory_sha256='inventory', plan_sha256='plan')
            args = SimpleNamespace(**plan)
            with patch.object(installer, 'ROOT', root), patch.object(installer, 'SOCKET', str(socket)), \
                    patch.object(installer, 'sql', side_effect=RuntimeError('FIXTURE_SQL_BOUNDARY')) as sql, \
                    patch.object(installer, 'record_decision') as record, patch.object(installer, 'update_env') as update, \
                    contextlib.redirect_stdout(io.StringIO()):
                with self.assertRaises((RuntimeError, SystemExit)):
                    installer.apply(args, plan)
                record.assert_not_called()
                sql.assert_not_called()
                update.assert_not_called()
            self.assertEqual(env.read_text(), original)
            self.assertFalse((root / 'storage').exists())

    def test_review_digests_are_required_before_any_side_effect(self):
        plan = dict(schema_sha256='schema', inventory_sha256='inventory', plan_sha256='plan')
        args = SimpleNamespace(schema_sha256='different', inventory_sha256='inventory', plan_sha256='plan')
        with patch.object(installer, 'sql') as sql, patch.object(installer, 'record_decision') as record:
            with self.assertRaisesRegex(RuntimeError, 'REVIEW_DIGEST_MISMATCH'):
                installer.apply(args, plan)
            sql.assert_not_called()
            record.assert_not_called()

    def test_offline_command_cannot_execute_sql(self):
        with patch('sys.argv', ['installer']), patch.object(installer, 'digest', return_value='offline'), \
                patch.object(installer, 'sql') as sql, patch.object(installer, 'apply') as apply:
            with contextlib.redirect_stdout(io.StringIO()) as output:
                installer.main()
            self.assertIn('schema_sha256', output.getvalue())
            sql.assert_not_called()
            apply.assert_not_called()

    def test_env_update_preserves_unrelated_keys_and_saves_original(self):
        with tempfile.TemporaryDirectory(dir='/private/tmp') as directory:
            root = Path(directory)
            env = root / '.env'
            original = 'APP_ENV=local\nAPP_URL=http://127.0.0.1:8080\nDB_USERNAME=preserved_reader\nHOMYA_CHECKOUT_ENABLED=false\n'
            env.write_text(original)
            env.chmod(0o600)
            installer.update_env(env, {'HOMYA_INVENTORY_ENABLED': 'true'}, root)
            self.assertEqual((root / 'source-env-before').read_text(), original)
            self.assertEqual(env.read_text(), original + 'HOMYA_INVENTORY_ENABLED=true\n')
            self.assertEqual(env.stat().st_mode & 0o777, 0o600)

    def test_duplicate_env_key_and_symlink_are_refused(self):
        with tempfile.TemporaryDirectory(dir='/private/tmp') as directory:
            root = Path(directory)
            env = root / '.env'
            env.write_text('APP_ENV=local\nAPP_URL=http://127.0.0.1:8000\nHOMYA_INVENTORY_ENABLED=false\nHOMYA_INVENTORY_ENABLED=true\n')
            env.chmod(0o600)
            with self.assertRaisesRegex(RuntimeError, 'DUPLICATE_ENV_KEY'):
                installer.update_env(env, {'HOMYA_INVENTORY_ENABLED': 'true'}, root)
            link = root / '.env-link'
            link.symlink_to(env)
            with self.assertRaisesRegex(RuntimeError, 'ENV_SYMLINK'):
                installer.update_env(link, {}, root)

    def test_sql_failures_never_reveal_stderr_or_password(self):
        result = SimpleNamespace(returncode=1, stderr='fixture_secret_password', stdout='')
        with patch('subprocess.run', return_value=result):
            with self.assertRaisesRegex(RuntimeError, '^LOCAL_SQL_REFUSED_DETAILS_WITHHELD$'):
                installer.sql('SELECT 1')

    def test_production_resume_and_finish_are_refused_before_any_action(self):
        env = self.root / '.env'
        original = 'APP_ENV=production\nAPP_URL=https://homya.eu\n'
        env.write_text(original)
        plan = dict(schema_sha256='schema', inventory_sha256='inventory', plan_sha256='plan')
        args = SimpleNamespace(**plan)
        with patch.object(installer, 'sql') as sql, patch.object(installer, 'php') as php, \
                patch.object(installer, 'record_decision') as record, patch.object(installer, 'update_env') as update, \
                patch.object(installer, 'private_json') as write:
            for operation in (
                lambda: installer.resume_empty(args, plan),
                lambda: installer.finish_verified_installation(args, plan),
                lambda: installer.finish_installation(self.root, {}, plan, '', ''),
                lambda: installer.verify_installed(self.root, {}, plan, '', ''),
            ):
                with self.subTest(operation=operation):
                    with self.assertRaisesRegex(RuntimeError, '^LOCAL_ENV_REQUIRED$'):
                        operation()
            sql.assert_not_called()
            php.assert_not_called()
            record.assert_not_called()
            update.assert_not_called()
            write.assert_not_called()
        self.assertEqual(env.read_text(), original)

    def test_production_direct_registry_and_env_update_are_refused(self):
        env = self.root / '.env'
        original = 'APP_ENV=production\nAPP_URL=https://homya.eu\n'
        env.write_text(original)
        with patch.object(installer, 'REGISTRY', self.root / 'unopened-registry'), \
                patch.object(installer, 'private_copy') as copy:
            with self.assertRaisesRegex(RuntimeError, '^LOCAL_ENV_REQUIRED$'):
                installer.record_decision(self.root, {})
            with self.assertRaisesRegex(RuntimeError, '^LOCAL_ENV_REQUIRED$'):
                installer.update_env(env, {'HOMYA_INVENTORY_ENABLED': 'true'}, self.root)
            copy.assert_not_called()
        self.assertEqual(env.read_text(), original)
        self.assertFalse((self.root / 'unopened-registry').exists())

    def test_public_url_and_config_cache_are_refused_before_sql_or_record(self):
        env = self.root / '.env'
        plan = dict(schema_sha256='schema', inventory_sha256='inventory', plan_sha256='plan')
        args = SimpleNamespace(**plan)
        for reason in ('LOOPBACK_URL_REQUIRED', 'CONFIG_CACHE_PRESENT'):
            if reason == 'LOOPBACK_URL_REQUIRED':
                env.write_text('APP_ENV=local\nAPP_URL=https://homya.eu\n')
            else:
                env.write_text('APP_ENV=local\nAPP_URL=http://127.0.0.1:8000\n')
                (self.root / 'bootstrap/cache').mkdir(parents=True)
                (self.root / 'bootstrap/cache/config.php').write_text('fixture-only')
            with patch.object(installer, 'sql') as sql, patch.object(installer, 'record_decision') as record:
                with self.assertRaisesRegex(RuntimeError, '^' + reason + '$'):
                    installer.apply(args, plan)
                sql.assert_not_called()
                record.assert_not_called()

    def test_env_pending_symlink_is_refused_before_any_external_write(self):
        env = self.root / '.env'
        before = env.read_text()
        outside = self.root / 'outside-env'
        env.with_name('.env-commerce-new').symlink_to(outside)
        with self.assertRaisesRegex(RuntimeError, '^SYMLINK_REFUSED$'):
            installer.update_env(env, {'HOMYA_INVENTORY_ENABLED': 'true'}, self.root)
        self.assertFalse(outside.exists())
        self.assertFalse((self.root / 'source-env-before').exists())
        self.assertEqual(env.read_text(), before)

    def test_private_json_is_exclusive_and_private_at_creation(self):
        destination = self.root / 'private.json'
        installer.private_json(destination, {'fixture': 'synthetic'})
        self.assertEqual(destination.stat().st_mode & 0o777, 0o600)
        with self.assertRaises(FileExistsError):
            installer.private_json(destination, {'fixture': 'changed'})

    def test_php_and_sql_use_clean_environment_shell_false_and_timeouts(self):
        result = SimpleNamespace(returncode=0, stderr='', stdout='fixture-result')
        with patch.dict(os.environ, {'DB_URL': 'fixture-secret', 'MYSQL_PWD': 'fixture-secret',
                                     'APP_ENV': 'production', 'PHPRC': '/fixture/php'}), \
                patch.object(installer.subprocess, 'run', return_value=result) as run:
            installer.sql('SELECT 1')
            sql_options = run.call_args.kwargs
            installer.php(['fixture-only'], {'HOMYA_CATALOGUE_SOURCE': 'files'})
            php_options = run.call_args.kwargs
        for options in (sql_options, php_options):
            self.assertIs(options['shell'], False)
            self.assertEqual(options['timeout'], 120)
            self.assertFalse({'DB_URL', 'MYSQL_PWD', 'APP_ENV', 'PHPRC'} & options['env'].keys())
        self.assertEqual(php_options['env']['HOMYA_CATALOGUE_SOURCE'], 'files')

    def test_php_refuses_arbitrary_environment_override_before_subprocess(self):
        with patch.object(installer.subprocess, 'run') as run:
            with self.assertRaisesRegex(RuntimeError, '^ENV_OVERRIDE_REFUSED$'):
                installer.php(['fixture-only'], {'HOMYA_COMMERCE_DB_DATABASE': 'production'})
            run.assert_not_called()

    def test_command_timeouts_never_disclose_details(self):
        for command, reason in (
            (lambda: installer.sql('SELECT 1'), 'LOCAL_SQL_TIMEOUT_DETAILS_WITHHELD'),
            (lambda: installer.php(['fixture-only']), 'LOCAL_PHP_TIMEOUT_DETAILS_WITHHELD'),
        ):
            with patch.object(installer.subprocess, 'run', side_effect=installer.subprocess.TimeoutExpired('fixture-secret', 120, output='fixture-secret')):
                with self.assertRaisesRegex(RuntimeError, '^' + reason + '$'):
                    command()

    def test_checksum_names_from_resume_evidence_cannot_be_sql_fragments(self):
        self.assertEqual(installer.checksum_statement(['archive_products']),
                         'CHECKSUM TABLE `homya_local`.`archive_products` EXTENDED')
        for names in ([], ['archive`; DROP TABLE fixture; --'], ['archive.products'], ['valid', 1]):
            with self.assertRaisesRegex(RuntimeError, '^UNEXPECTED_EXISTING_TABLES$'):
                installer.checksum_statement(names)

    def test_private_resume_evidence_refuses_links_or_public_permissions(self):
        path = self.root / 'evidence.json'
        installer.private_json(path, {'fixture': 'synthetic'})
        self.assertEqual(installer.read_private_json(path), {'fixture': 'synthetic'})
        path.chmod(0o644)
        with self.assertRaisesRegex(RuntimeError, '^PRIVATE_FILE_REQUIRED$'):
            installer.read_private_json(path)
        path.chmod(0o600)
        linked = self.root / 'linked-evidence.json'
        linked.symlink_to(path)
        with self.assertRaisesRegex(RuntimeError, '^SYMLINK_REFUSED$'):
            installer.read_private_json(linked)


if __name__ == '__main__':
    unittest.main()
