#!/usr/bin/env python3
"""Small read-only local HTTP security recipe; never saves response bodies or cookies."""
import argparse
import json
import re
from datetime import datetime, timezone
from pathlib import Path
from urllib.error import HTTPError, URLError
from urllib.parse import quote, urlsplit
from urllib.request import HTTPRedirectHandler, Request, build_opener


class NoRedirect(HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, headers, newurl):
        return None


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--base', default='http://127.0.0.1:8080')
    parser.add_argument('--htdocs-base', default='http://127.0.0.1/HOMECONFORT')
    parser.add_argument('--output', default='storage/app/private/audits/runtime-2026-10-20/http-security.json')
    args = parser.parse_args()
    for base in (args.base, args.htdocs_base):
        parsed = urlsplit(base)
        if parsed.hostname not in ('127.0.0.1', 'localhost', '::1') or parsed.scheme != 'http':
            parser.error('Only local HTTP endpoints are supported')
    opener = build_opener(NoRedirect())
    results = []

    def check(label, path, expected, method='GET', base=None, body_check=None, extra_headers=None):
        url = (base or args.base).rstrip('/') + path
        request = Request(url, data=b'' if method == 'POST' else None, method=method,
                          headers={'User-Agent': 'HOME-CONFORT-local-security-recipe/1.0', **(extra_headers or {})})
        try:
            try:
                response = opener.open(request, timeout=20)
            except HTTPError as error:
                response = error
            with response:
                body = response.read(2000000).decode('utf-8', errors='replace')
                headers = response.headers
                status = response.code
            row = {'check': label, 'method': method, 'url': url, 'status': status,
                   'expected': expected, 'passed': status in expected}
            if body_check:
                row['body_check_passed'] = body_check(body)
                row['passed'] = row['passed'] and row['body_check_passed']
            if label == 'home fr':
                row['headers'] = {key: headers.get(key) for key in (
                    'Content-Security-Policy', 'X-Content-Type-Options', 'X-Frame-Options',
                    'Referrer-Policy', 'Permissions-Policy', 'Cache-Control')}
                cookies = headers.get_all('Set-Cookie', [])
                row['cookie_attributes'] = [{'httponly': 'httponly' in value.lower(),
                                            'samesite': 'samesite=' in value.lower(),
                                            'secure': '; secure' in value.lower()} for value in cookies]
            if headers.get('Location'):
                location = urlsplit(headers['Location'])
                row['redirect_path'] = location.path
                row['redirect_local'] = not location.hostname or location.hostname in ('localhost', '127.0.0.1', '::1')
            results.append(row)
            return body
        except (URLError, TimeoutError, OSError) as error:
            results.append({'check': label, 'method': method, 'url': url,
                            'passed': False, 'error_type': type(error).__name__})
            return ''

    check('root redirect', '/', [301, 302])
    check('old public entry redirects', '/public/index.php', [301, 302], base=args.htdocs_base)
    for locale in ('fr', 'en', 'de'):
        check('home ' + locale, '/' + locale, [200])
        check('head home ' + locale, '/' + locale, [200], 'HEAD')
    catalogue = check('catalogue', '/fr/catalogue', [200])
    products = re.findall(r'href=[\"\'](?:https?://[^/\"\']+)?(/fr/produit/[a-z0-9-]+)', catalogue)
    if products:
        check('product discovered in catalogue', products[0], [200])
    else:
        results.append({'check': 'product discovered in catalogue', 'passed': False,
                        'limitation': 'No product URL found in catalogue HTML'})
    for path in ('/admin', '/admin/commandes', '/admin/documents', '/admin/parametres'):
        check('anonymous admin', path, [302, 303, 401, 403])
    private_paths = ('/.env', '/.git/config', '/storage/logs/laravel.log', '/vendor/autoload.php',
                     '/composer.json', '/backup.sql', '/storage/app/private/audits/herozion-2026-10-20/LECTURE-RESULTATS.txt')
    for base in (args.base, args.htdocs_base):
        for path in private_paths:
            check('private file denied', path, [403, 404], base=base)
    for path in ('/%2eenv', '/%2e%2e/.env', '/build/../../.env', '/index.php/config',
                 '/shell.php.jpg', '/storage/app/private/test.php', '/vendor/phpunit/phpunit/src/Util/PHP/eval-stdin.php'):
        check('edge private or executable path denied', path, [400, 403, 404])
    check('localhost hostname', '/fr', [200], base='http://localhost:8080')
    check('external Host denied', '/fr', [400, 403, 404, 421, 503],
          extra_headers={'Host': 'runtime-audit.invalid'},
          body_check=lambda body: '/Applications/XAMPP/' not in body and 'Stack trace:' not in body)
    check('untrusted forwarded Host ignored', '/fr', [200],
          extra_headers={'X-Forwarded-Host': 'runtime-audit.invalid', 'X-Forwarded-Proto': 'https'},
          body_check=lambda body: 'runtime-audit.invalid' not in body)
    check('not found without debug leak', '/runtime-security-missing-route', [404],
          body_check=lambda body: not any(token in body for token in (
              'Stack trace:', '/Applications/XAMPP/', 'Illuminate\\\\', 'Whoops\\\\')))
    marker = '<script>runtime_security_probe</script>'
    check('query reflected XSS', '/fr/catalogue?q=' + quote(marker), [200, 422],
          body_check=lambda body: marker not in body)
    for path in ('/fr/panier', '/admin/connexion'):
        check('empty POST without CSRF', path, [419, 403], 'POST')
    uuid = '00000000-0000-4000-8000-000000000000'
    for path in (f'/fr/commande/{uuid}', f'/fr/commande/{uuid}/document/summary',
                 f'/fr/commande/{uuid}/documents/{uuid}', f'/fr/retours/{uuid}/document',
                 f'/admin/documents/{uuid}/pdf'):
        check('anonymous nonexistent object denied', path, [302, 303, 401, 403, 404])
    report = {'generated_at': datetime.now(timezone.utc).isoformat(),
              'passed': all(row['passed'] for row in results), 'results': results,
              'limitations': ['No authenticated sessions, payment, email, upload, load or database mutation tested.',
                              'Bodies, cookie values and private contents are never persisted.',
                              'HSTS and Secure cookies require HTTPS production checks.',
                              'Random UUID checks do not prove protection of existing objects.']}
    output = Path(args.output)
    output.parent.mkdir(parents=True, exist_ok=True)
    output.write_text(json.dumps(report, indent=2, ensure_ascii=False) + '\n')
    print(json.dumps({'passed': report['passed'], 'checks': len(results),
                      'failures': [r for r in results if not r['passed']], 'report': str(output)}, indent=2))
    return 0 if report['passed'] else 1


if __name__ == '__main__':
    raise SystemExit(main())
