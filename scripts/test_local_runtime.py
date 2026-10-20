"""Exercise the router against an isolated synthetic document root, never real data."""
import shutil
import socket
import subprocess
import tempfile
import time
import unittest
import urllib.error
import urllib.request
from pathlib import Path


class LocalRouterTest(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.tmp = tempfile.TemporaryDirectory(prefix='home-confort-router-')
        root = Path(cls.tmp.name)
        (root / 'scripts').mkdir()
        (root / 'public').mkdir()
        shutil.copyfile(Path(__file__).with_name('local_router.php'), root / 'scripts/local_router.php')
        (root / 'public/index.php').write_text('<?php echo "APPLICATION";')
        (root / 'public/asset.css').write_text('body{}')
        (root / 'public/payload.php.jpg').write_text('<?php echo "EXECUTED";')
        (root / 'public/.env').write_text('SYNTHETIC_PRIVATE')
        (root / 'private.txt').write_text('SYNTHETIC_PRIVATE')
        (root / 'public/leak.txt').symlink_to(root / 'private.txt')
        with socket.socket() as probe:
            probe.bind(('127.0.0.1', 0))
            port = probe.getsockname()[1]
        cls.url = 'http://127.0.0.1:'+str(port)
        cls.process = subprocess.Popen([shutil.which('php'), '-S', '127.0.0.1:'+str(port),
                                       '-t', str(root / 'public'), str(root / 'scripts/local_router.php')],
                                      stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
        for _ in range(100):
            try:
                urllib.request.urlopen(cls.url, timeout=1).close()
                return
            except OSError:
                time.sleep(.05)
        cls.process.terminate()
        cls.process.wait()
        cls.tmp.cleanup()
        raise RuntimeError('Synthetic server did not start')

    @classmethod
    def tearDownClass(cls):
        cls.process.terminate()
        cls.process.wait(timeout=5)
        cls.tmp.cleanup()

    def response(self, path):
        try:
            with urllib.request.urlopen(self.url+path, timeout=2) as response:
                return response.status, response.read()
        except urllib.error.HTTPError as error:
            return error.code, error.read()

    def test_static_asset_and_application_routes_work(self):
        self.assertEqual(self.response('/asset.css'), (200, b'body{}'))
        self.assertEqual(self.response('/fr/catalogue'), (200, b'APPLICATION'))
        self.assertEqual(self.response('/index.php'), (200, b'APPLICATION'))

    def test_private_paths_and_executable_uploads_are_denied(self):
        for path in ['/.env', '/%2eenv', '/.git/config', '/vendor/autoload.php',
                     '/payload.php.jpg', '/payload.PHP8', '/backup.sql.gz', '/storage/logs/a.log']:
            with self.subTest(path=path):
                status, body = self.response(path)
                self.assertEqual(status, 403)
                self.assertNotIn(b'SYNTHETIC_PRIVATE', body)
                self.assertNotIn(b'EXECUTED', body)

    def test_static_symlink_cannot_escape_document_root(self):
        status, body = self.response('/leak.txt')
        self.assertEqual(status, 200)
        self.assertEqual(body, b'APPLICATION')


if __name__ == '__main__':
    unittest.main()
