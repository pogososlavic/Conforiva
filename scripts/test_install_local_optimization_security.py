"""Optimization installer checks on private fixtures; no Apache or SQL calls."""
import os
import tempfile
import unittest
from pathlib import Path
from types import SimpleNamespace
from unittest.mock import patch

import install_local_optimization as installer


class OptimizationSecurityTests(unittest.TestCase):
    def setUp(self):
        self.temporary = tempfile.TemporaryDirectory(dir='/private/tmp')
        self.addCleanup(self.temporary.cleanup)
        self.root = Path(self.temporary.name)

    def files(self):
        source = self.root / 'source.txt'
        target = self.root / 'destination.txt'
        source.write_text('synthetic replacement')
        target.write_text('preserved target')
        return source, target

    def project(self, name, production=False):
        root = self.root / name
        root.mkdir(mode=0o700)
        (root / '.env').write_text('APP_ENV=production\nAPP_URL=https://homya.eu\n' if production else
                                 'APP_ENV=local\nAPP_URL=http://127.0.0.1:8000\n')
        (root / '.env').chmod(0o600)
        return root

    def test_dangling_pending_symlink_never_writes_outside_destination(self):
        with tempfile.TemporaryDirectory(dir='/private/tmp') as directory:
            root = Path(directory)
            source = root / 'source.txt'
            source.write_text('synthetic replacement')
            target = root / 'destination.txt'
            target.write_text('preserved target')
            outside = root / 'outside.txt'
            pending = target.with_name(target.name + '.homya-optimization-pending')
            pending.symlink_to(outside)
            with self.assertRaises((RuntimeError, ValueError)):
                installer.atomic_copy(source, target)
            self.assertFalse(outside.exists(), 'A pending symlink allowed writing outside the destination.')
            self.assertEqual(target.read_text(), 'preserved target')

    def test_existing_pending_symlink_does_not_modify_external_file(self):
        source, target = self.files()
        outside = self.root / 'outside.txt'
        outside.write_text('preserved outside')
        target.with_name(target.name + '.homya-optimization-pending').symlink_to(outside)
        with self.assertRaisesRegex(RuntimeError, '^SYMLINK_REFUSED$'):
            installer.atomic_copy(source, target)
        self.assertEqual(outside.read_text(), 'preserved outside')
        self.assertEqual(target.read_text(), 'preserved target')

    def test_target_symlink_is_refused_before_external_write(self):
        source, target = self.files()
        target.unlink()
        outside = self.root / 'outside.txt'
        target.symlink_to(outside)
        with self.assertRaisesRegex(RuntimeError, '^SYMLINK_REFUSED$'):
            installer.atomic_copy(source, target)
        self.assertFalse(outside.exists())

    def test_symlink_and_hardlinked_sources_are_refused(self):
        source, target = self.files()
        linked_source = self.root / 'linked-source'
        linked_source.symlink_to(source)
        with self.assertRaisesRegex(RuntimeError, '^SYMLINK_REFUSED$'):
            installer.atomic_copy(linked_source, target)
        os.link(source, self.root / 'hardlinked-source')
        with self.assertRaisesRegex(RuntimeError, '^HARDLINK_REFUSED$'):
            installer.atomic_copy(source, target)
        self.assertEqual(target.read_text(), 'preserved target')

    def test_mutable_and_linked_ancestors_are_refused(self):
        source, target = self.files()
        mutable = self.root / 'mutable'
        mutable.mkdir(mode=0o777)
        mutable.chmod(0o777)
        with self.assertRaisesRegex(RuntimeError, '^MUTABLE_PARENT_REFUSED$'):
            installer.atomic_copy(source, mutable / 'destination.txt')
        linked = self.root / 'linked'
        linked.symlink_to(mutable, target_is_directory=True)
        with self.assertRaisesRegex(RuntimeError, '^SYMLINK_REFUSED$'):
            installer.atomic_copy(source, linked / 'destination.txt')
        self.assertEqual(list(mutable.iterdir()), [])

    def test_copy_is_exclusive_private_at_creation_and_preserves_destination_mode(self):
        source, target = self.files()
        source.chmod(0o644)
        target.chmod(0o600)
        actual_open = os.open
        created = []

        def inspect_open(path, flags, mode=0o777):
            descriptor = actual_open(path, flags, mode)
            if flags & os.O_CREAT:
                created.append((flags, os.fstat(descriptor).st_mode & 0o777))
            return descriptor

        with patch.object(installer.os, 'open', side_effect=inspect_open):
            installer.atomic_copy(source, target)
        self.assertEqual(target.read_text(), source.read_text())
        self.assertEqual(installer.digest(target), installer.digest(source))
        self.assertEqual(target.stat().st_mode & 0o777, 0o600)
        self.assertEqual(len(created), 1)
        self.assertEqual(created[0][1], 0o600)
        self.assertTrue(created[0][0] & os.O_EXCL)
        self.assertTrue(created[0][0] & os.O_NOFOLLOW)
        self.assertFalse(target.with_name(target.name + '.homya-optimization-pending').exists())

    def test_existing_pending_file_is_preserved(self):
        source, target = self.files()
        pending = target.with_name(target.name + '.homya-optimization-pending')
        pending.write_text('preserved pending')
        with self.assertRaises(FileExistsError):
            installer.atomic_copy(source, target)
        self.assertEqual(pending.read_text(), 'preserved pending')
        self.assertEqual(target.read_text(), 'preserved target')

    def test_changed_target_is_not_overwritten_during_atomic_copy(self):
        source, target = self.files()
        with patch.object(installer.os, 'fsync', side_effect=lambda descriptor: target.write_text('intervening change')):
            with self.assertRaisesRegex(RuntimeError, '^TARGET_CHANGED$'):
                installer.atomic_copy(source, target)
        self.assertEqual(target.read_text(), 'intervening change')

    def test_changed_source_is_refused_before_publication(self):
        source, target = self.files()
        with patch.object(installer.os, 'fsync', side_effect=lambda descriptor: source.write_text('intervening change')):
            with self.assertRaisesRegex(RuntimeError, '^COPY_MISMATCH$'):
                installer.atomic_copy(source, target)
        self.assertEqual(target.read_text(), 'preserved target')

    def test_replaced_pending_symlink_is_refused_before_publication(self):
        source, target = self.files()
        pending = target.with_name(target.name + '.homya-optimization-pending')
        outside = self.root / 'outside.txt'

        def replace_pending(descriptor, mode):
            pending.unlink()
            pending.symlink_to(outside)

        with patch.object(installer.os, 'fchmod', side_effect=replace_pending):
            with self.assertRaisesRegex(RuntimeError, '^SYMLINK_REFUSED$'):
                installer.atomic_copy(source, target)
        self.assertEqual(target.read_text(), 'preserved target')
        self.assertFalse(target.is_symlink())
        self.assertFalse(outside.exists())

    def test_main_refuses_production_before_commands_or_copy_in_both_modes(self):
        for side in ('source', 'target'):
            for apply in (False, True):
                with self.subTest(side=side, apply=apply):
                    source = self.project('source-' + side + '-' + str(apply), production=side == 'source')
                    target = self.project('target-' + side + '-' + str(apply), production=side == 'target')
                    with patch.object(installer, 'ROOT', source), patch.object(installer, 'TARGET', target), \
                            patch('sys.argv', ['installer'] + (['--apply'] if apply else [])), \
                            patch.object(installer, 'command') as command, patch.object(installer, 'atomic_copy') as copy:
                        with self.assertRaisesRegex(RuntimeError, '^LOCAL_ENV_REQUIRED$'):
                            installer.main()
                        command.assert_not_called()
                        copy.assert_not_called()

    def test_command_is_bounded_shell_false_and_does_not_inherit_overrides(self):
        with patch.dict(os.environ, {'APP_ENV': 'production', 'PHPRC': '/fixture/php', 'DB_URL': 'fixture-secret'}), \
                patch.object(installer.subprocess, 'run', return_value=SimpleNamespace(returncode=0)) as run:
            installer.command(['fixture-only'], capture_output=True)
        options = run.call_args.kwargs
        self.assertIs(options['shell'], False)
        self.assertEqual(options['timeout'], 60)
        self.assertFalse({'APP_ENV', 'PHPRC', 'DB_URL'} & options['env'].keys())


if __name__ == '__main__':
    unittest.main()
