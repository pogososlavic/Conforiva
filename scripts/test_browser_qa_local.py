"""Fixture-only security checks; no browser, real credentials, database or network."""
import contextlib
import io
import json
import os
from pathlib import Path
import tempfile
import unittest
from unittest.mock import patch

import browser_qa_local as qa


class BrowserQaBoundaryTests(unittest.TestCase):
    def test_command_and_environment_do_not_transport_secrets(self):
        with tempfile.TemporaryDirectory() as temporary:
            runtime = Path(temporary)
            node = runtime / 'node'
            node.write_text('synthetic executable placeholder')
            module = runtime / 'playwright'
            module.mkdir()
            (module / 'package.json').write_text('{}')
            secret = 'Synthetic-only-password-123!'
            with patch.dict(os.environ, {
                'HOMYA_QA_NODE': str(node), 'HOMYA_QA_PLAYWRIGHT_MODULE': str(module),
                'HOMYA_ADMIN_PASSWORD': secret, 'DEBUG': secret, 'NODE_OPTIONS': secret,
            }, clear=True):
                command = qa.runner_command('admin')
                environment = qa.runner_environment()
            self.assertNotIn(secret, json.dumps(command))
            self.assertNotIn(secret, json.dumps(environment))
            self.assertNotIn('run-code', command)
            self.assertTrue(command[1].endswith('/scripts/browser_qa/runner.mjs'))
            self.assertEqual(command[2], 'admin')
            self.assertEqual(command[3], str(module))

    def test_runner_failures_never_print_captured_output(self):
        secret = 'Synthetic-only-password-123!'
        process = type('Process', (), {'returncode': 1, 'stdout': secret, 'stderr': secret})()
        with patch.object(qa, 'runner_command', return_value=['fixture-node']), \
                patch.object(qa.subprocess, 'run', return_value=process), \
                contextlib.redirect_stdout(io.StringIO()) as output:
            with self.assertRaises(SystemExit):
                qa.run_scenario('admin')
        self.assertNotIn(secret, output.getvalue())
        self.assertEqual(json.loads(output.getvalue())['reason'], 'BROWSER_CHECK_FAILED')

    def test_reports_are_private_and_refuse_symlinks(self):
        with tempfile.TemporaryDirectory() as temporary:
            root = Path(temporary).resolve()
            with patch.object(qa, 'ROOT', root):
                qa.write_report('fixture.json', {'fixture': True})
                report = root / 'output/qa-commerce/fixture.json'
                self.assertEqual(report.stat().st_mode & 0o777, 0o600)
                report.unlink()
                target = root / 'untouched.json'
                target.write_text('synthetic placeholder')
                report.symlink_to(target)
                with self.assertRaises(OSError):
                    qa.write_report('fixture.json', {'fixture': True})
                self.assertEqual(target.read_text(), 'synthetic placeholder')

    def test_original_wrappers_no_longer_read_credentials_or_interpolate_code(self):
        for filename in ('verify_admin_local.py', 'verify_admin_management_local.py',
                         'verify_admin_upload_local.py', 'verify_commercial_documents_local.py'):
            source = (qa.ROOT / 'scripts' / filename).read_text()
            self.assertNotIn('read_text()', source)
            self.assertNotIn('run-code', source)
            self.assertNotIn('.replace(', source)
            self.assertNotIn('/htdocs/HOMYA/', source)
            self.assertIn('run_scenario(', source)


if __name__ == '__main__':
    unittest.main()
