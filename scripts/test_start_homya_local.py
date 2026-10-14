import contextlib
import io
import json
import os
from pathlib import Path
import tempfile
from types import SimpleNamespace
import unittest
from unittest.mock import MagicMock, patch
import start_homya_local as tool


class LocalStartupTest(unittest.TestCase):
    def test_default_status_never_starts_processes(self):
        with patch.object(tool, 'require_private'), patch.object(tool, 'verified_pid', return_value=None), patch.object(tool.subprocess, 'run', side_effect=AssertionError('unexpected')), contextlib.redirect_stdout(io.StringIO()):
            self.assertEqual(tool.main([]), 0)

    def test_start_never_restarts_existing_verified_services(self):
        with patch.object(tool, 'require_private'), patch.object(tool, 'verified_pid', return_value=12345), patch.object(tool.subprocess, 'run', side_effect=AssertionError('unexpected')), contextlib.redirect_stdout(io.StringIO()):
            self.assertEqual(tool.main(['--start']), 0)

    def test_linked_startup_logs_refused_before_child_and_protected_file_unchanged(self):
        for kind in ('symlink', 'hardlink'):
            with self.subTest(kind=kind), tempfile.TemporaryDirectory() as temporary:
                root = Path(temporary).resolve()
                run = root / 'run'
                run.mkdir(mode=0o700)
                protected = root / 'protected-fixture.txt'
                protected.write_bytes(b'synthetic-protected-fixture')
                protected.chmod(0o600)
                logfile = run / 'php-fpm-startup.log'
                if kind == 'symlink':
                    logfile.symlink_to(protected)
                else:
                    os.link(protected, logfile)
                with patch.object(tool, 'RUN', run), \
                        patch.object(tool, 'SERVICES', tool.SERVICES[:1]), \
                        patch.object(tool, 'require_private'), \
                        patch.object(tool, 'verified_pid', return_value=None), \
                        patch.object(tool.socket, 'socket', return_value=MagicMock()), \
                        patch.object(tool.subprocess, 'run') as child, \
                        contextlib.redirect_stdout(io.StringIO()) as output:
                    self.assertEqual(tool.main(['--start']), 1)
                child.assert_not_called()
                self.assertEqual(protected.read_bytes(), b'synthetic-protected-fixture')
                self.assertEqual(json.loads(output.getvalue())['status'],
                                 'LOCAL_RUNTIME_UNAVAILABLE_NO_OTHER_SERVICE_CHANGED')

    def test_log_created_private_and_existing_private_log_appended(self):
        with tempfile.TemporaryDirectory() as temporary:
            root = Path(temporary).resolve()
            root.chmod(0o700)
            logfile = root / 'fixture-startup.log'
            with tool.open_startup_log(logfile) as stream:
                self.assertEqual(os.fstat(stream.fileno()).st_mode & 0o777, 0o600)
                stream.write(b'first-fixture')
            with tool.open_startup_log(logfile) as stream:
                stream.write(b'-second-fixture')
            self.assertEqual(logfile.read_bytes(), b'first-fixture-second-fixture')

    def test_non_private_and_non_regular_startup_logs_refused(self):
        with tempfile.TemporaryDirectory() as temporary:
            root = Path(temporary).resolve()
            logfile = root / 'fixture-startup.log'
            logfile.write_bytes(b'synthetic-only')
            logfile.chmod(0o644)
            with self.assertRaises(RuntimeError):
                tool.open_startup_log(logfile)
            self.assertEqual(logfile.read_bytes(), b'synthetic-only')
            logfile.unlink()
            logfile.mkdir(mode=0o700)
            with self.assertRaises(OSError):
                tool.open_startup_log(logfile)

    def test_foreign_owner_startup_log_refused_by_opened_inode(self):
        with tempfile.TemporaryDirectory() as temporary:
            logfile = Path(temporary).resolve() / 'fixture-startup.log'
            logfile.write_bytes(b'synthetic-only')
            logfile.chmod(0o600)
            original = tool.os.fstat

            def different_owner(descriptor):
                metadata = list(original(descriptor))
                metadata[4] = os.geteuid() + 1
                return os.stat_result(metadata)

            with patch.object(tool.os, 'fstat', side_effect=different_owner):
                with self.assertRaises(RuntimeError):
                    tool.open_startup_log(logfile)
            self.assertEqual(logfile.read_bytes(), b'synthetic-only')

    def test_fifo_log_is_refused_without_reader_blocking_or_starting_child(self):
        with tempfile.TemporaryDirectory() as temporary:
            run = Path(temporary).resolve()
            fifo = run / 'php-fpm-startup.log'
            os.mkfifo(fifo, mode=0o600)
            original_open = tool.os.open

            def nonblocking_open(path, flags, mode):
                # Assert before the real open so a regression cannot block this test.
                self.assertTrue(flags & os.O_NONBLOCK)
                return original_open(path, flags, mode)

            with patch.object(tool, 'RUN', run), \
                    patch.object(tool, 'SERVICES', tool.SERVICES[:1]), \
                    patch.object(tool, 'require_private'), \
                    patch.object(tool, 'verified_pid', return_value=None), \
                    patch.object(tool.socket, 'socket', return_value=MagicMock()), \
                    patch.object(tool.os, 'open', side_effect=nonblocking_open) as opened, \
                    patch.object(tool.subprocess, 'run') as child, \
                    contextlib.redirect_stdout(io.StringIO()):
                self.assertEqual(tool.main(['--start']), 1)
            child.assert_not_called()
            opened.assert_called_once()

    def test_start_uses_bound_process_with_clean_environment(self):
        with tempfile.TemporaryDirectory() as temporary:
            run = Path(temporary).resolve()
            with patch.object(tool, 'RUN', run), \
                    patch.object(tool, 'SERVICES', tool.SERVICES[:1]), \
                    patch.object(tool, 'require_private'), \
                    patch.object(tool, 'verified_pid', side_effect=[None, 12345]), \
                    patch.object(tool.socket, 'socket', return_value=MagicMock()), \
                    patch.object(tool.subprocess, 'run', return_value=SimpleNamespace(returncode=0)) as child, \
                    patch.dict(os.environ, {'APP_ENV': 'production', 'DB_PASSWORD': 'synthetic-only'}), \
                    contextlib.redirect_stdout(io.StringIO()):
                self.assertEqual(tool.main(['--start']), 0)
            options = child.call_args.kwargs
            self.assertIs(options['shell'], False)
            self.assertEqual(options['timeout'], 30)
            self.assertNotIn('APP_ENV', options['env'])
            self.assertNotIn('DB_PASSWORD', options['env'])
            self.assertEqual((run / 'php-fpm-startup.log').stat().st_mode & 0o777, 0o600)

    def test_pid_check_uses_bound_process_with_clean_environment(self):
        with tempfile.TemporaryDirectory() as temporary:
            run = Path(temporary).resolve()
            (run / 'php-fpm.pid').write_text('12345')
            process = SimpleNamespace(returncode=0, stdout=str(run / 'php-fpm.conf').encode())
            with patch.object(tool, 'RUN', run), patch.object(tool.subprocess, 'run', return_value=process) as child:
                self.assertEqual(tool.verified_pid('php-fpm', 'php-fpm.conf'), 12345)
            self.assertIs(child.call_args.kwargs['shell'], False)
            self.assertEqual(child.call_args.kwargs['timeout'], 10)

    def test_redirect_handler_never_returns_a_followup_request(self):
        for status in (301, 302, 303, 307, 308):
            with self.subTest(status=status):
                with self.assertRaisesRegex(RuntimeError, 'LOCAL_HTTP_REDIRECT_REFUSED'):
                    tool.NoLocalRedirects().redirect_request(None, None, status, None, None,
                                                            'https://example.invalid/fixture')

    @staticmethod
    def response(status=200, url='http://127.0.0.1:8080/fr/catalogue'):
        response = MagicMock()
        response.status = status
        response.geturl.return_value = url
        response.__enter__.return_value = response
        return response

    def test_wait_ready_accepts_only_same_origin_success_without_network(self):
        opener = MagicMock()
        opener.open.return_value = self.response()
        with patch.object(tool.Path, 'is_socket', return_value=True), \
                patch.object(tool.urllib.request, 'build_opener', return_value=opener) as build, \
                patch.object(tool.time, 'monotonic', side_effect=[0, 0]):
            tool.wait_ready()
        self.assertIsInstance(build.call_args.args[0], tool.urllib.request.ProxyHandler)
        self.assertEqual(build.call_args.args[0].proxies, {})
        self.assertIsInstance(build.call_args.args[1], tool.NoLocalRedirects)
        opener.open.assert_called_once_with('http://127.0.0.1:8080/fr/catalogue', timeout=3)

    def test_wait_ready_rejects_redirect_status_and_external_or_invalid_origin(self):
        cases = [(status, 'http://127.0.0.1:8080/fr/catalogue') for status in (301, 302, 303, 307, 308)]
        cases += [(200, url) for url in ('https://example.invalid/fixture',
                                       'http://127.0.0.1:8081/fixture',
                                       'http://localhost:8080/fixture',
                                       'http://fixture@127.0.0.1:8080/fixture')]
        for status, url in cases:
            with self.subTest(status=status, url=url):
                opener = MagicMock()
                opener.open.return_value = self.response(status, url)
                with patch.object(tool.Path, 'is_socket', return_value=True), \
                        patch.object(tool.urllib.request, 'build_opener', return_value=opener), \
                        patch.object(tool.time, 'monotonic', side_effect=[0, 0]):
                    with self.assertRaises(RuntimeError):
                        tool.wait_ready()
                opener.open.assert_called_once()

    def test_wait_ready_non_success_or_missing_socket_times_out_without_real_sleep(self):
        for available, status in ((True, 503), (False, 200)):
            with self.subTest(socket=available, status=status):
                opener = MagicMock()
                opener.open.return_value = self.response(status)
                with patch.object(tool.Path, 'is_socket', return_value=available), \
                        patch.object(tool.urllib.request, 'build_opener', return_value=opener), \
                        patch.object(tool.time, 'monotonic', side_effect=[0, 0, 91]), \
                        patch.object(tool.time, 'sleep'):
                    with self.assertRaisesRegex(RuntimeError, 'LOCAL_DEPENDENCIES_NOT_READY'):
                        tool.wait_ready()
                self.assertEqual(opener.open.call_count, 1 if available else 0)


if __name__ == '__main__': unittest.main()
