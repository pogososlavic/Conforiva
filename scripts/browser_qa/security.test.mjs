import assert from 'node:assert/strict';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import test from 'node:test';
import {
  assertSafeResult, createLocalContext, ensureArtifactDirectory, isLocalRequest,
  localRequest, readInvoiceFixturePath, readPrivateCredentials,
  readSyntheticUploadFixture, requireLocalProject, requireQuietRuntime,
} from './security.mjs';

const fixtureCredentials = { USERNAME: 'fixture-admin', PASSWORD: 'Synthetic-only-password-123!' };

function fixture(t) {
  const root = fs.realpathSync(fs.mkdtempSync(path.join(os.tmpdir(), 'homya-qa-security-')));
  t.after(() => fs.rmSync(root, { recursive: true, force: true }));
  const directory = path.join(root, 'storage/app/private/admin');
  fs.mkdirSync(directory, { recursive: true, mode: 0o700 });
  const filename = path.join(directory, 'access-20261013-fixture.txt');
  fs.writeFileSync(filename,
    `Identifiant : ${fixtureCredentials.USERNAME}\nMot de passe : ${fixtureCredentials.PASSWORD}\n`,
    { mode: 0o600 });
  return { root, directory, filename };
}

test('reads only a private fixture file from the specified project', t => {
  const { root } = fixture(t);
  assert.deepEqual(readPrivateCredentials(root), fixtureCredentials);
});

test('refuses missing credentials without using a neighbouring project', t => {
  const { root, filename } = fixture(t);
  fs.unlinkSync(filename);
  const neighbour = path.join(root, 'HOMYA/storage/app/private/admin');
  fs.mkdirSync(neighbour, { recursive: true, mode: 0o700 });
  fs.writeFileSync(path.join(neighbour, 'access-fixture.txt'), 'Synthetic only', { mode: 0o600 });
  assert.throws(() => readPrivateCredentials(root));
});

test('refuses readable credential files and non-private parents', t => {
  const { root, directory, filename } = fixture(t);
  fs.chmodSync(filename, 0o644);
  assert.throws(() => readPrivateCredentials(root), /LOCAL_QA_PRIVATE_INPUT_INVALID/);
  fs.chmodSync(filename, 0o600);
  fs.chmodSync(directory, 0o755);
  assert.throws(() => readPrivateCredentials(root), /LOCAL_QA_PRIVATE_INPUT_INVALID/);
});

test('refuses mutable or differently owned credential ancestors', t => {
  const { root } = fixture(t);
  const storage = path.join(root, 'storage');
  fs.chmodSync(storage, 0o777);
  assert.throws(() => readPrivateCredentials(root), /LOCAL_QA_PRIVATE_INPUT_INVALID/);
  fs.chmodSync(storage, 0o700);
  const original = fs.lstatSync;
  t.mock.method(fs, 'lstatSync', filename => {
    const metadata = original(filename);
    if (filename === storage) metadata.uid += 1;
    return metadata;
  });
  assert.throws(() => readPrivateCredentials(root), /LOCAL_QA_PRIVATE_INPUT_INVALID/);
});

test('refuses linked files, hard links and linked parent directories', t => {
  const { root, directory, filename } = fixture(t);
  const original = path.join(root, 'original-fixture.txt');
  fs.renameSync(filename, original);
  fs.symlinkSync(original, filename);
  assert.throws(() => readPrivateCredentials(root));
  fs.unlinkSync(filename);
  fs.linkSync(original, filename);
  assert.throws(() => readPrivateCredentials(root), /LOCAL_QA_PRIVATE_INPUT_INVALID/);
  fs.unlinkSync(filename);
  fs.renameSync(original, filename);
  const privateDirectory = path.join(root, 'linked-fixture-directory');
  fs.renameSync(directory, privateDirectory);
  fs.symlinkSync(privateDirectory, directory);
  assert.throws(() => readPrivateCredentials(root), /LOCAL_QA_PRIVATE_INPUT_INVALID/);
});

test('refuses ambiguous credentials and only selects named access files', t => {
  const { root, directory, filename } = fixture(t);
  fs.appendFileSync(filename, 'Mot de passe : another-synthetic-password\n');
  assert.throws(() => readPrivateCredentials(root), /LOCAL_QA_PRIVATE_INPUT_INVALID/);
  fs.unlinkSync(filename);
  fs.writeFileSync(path.join(directory, 'not-an-access-file.txt'), 'Synthetic only', { mode: 0o600 });
  assert.throws(() => readPrivateCredentials(root), /LOCAL_QA_PRIVATE_INPUT_INVALID/);
});

test('allows only the exact HTTP loopback origin and no URL credentials', () => {
  assert.equal(isLocalRequest('http://127.0.0.1:8080/admin'), true);
  for (const url of [
    'https://homya.eu/admin', 'http://127.0.0.1:8081/admin', 'http://localhost:8080/admin',
    'https://127.0.0.1:8080/admin', 'http://127.0.0.1:8080.example.invalid/admin',
    'http://fixture:synthetic@127.0.0.1:8080/admin', 'file:///private/tmp/fixture', 'data:text/plain,fixture',
  ]) assert.equal(isLocalRequest(url), false);
});

test('API calls reject external destinations and cannot follow redirects', async () => {
  const calls = [];
  const api = { get: async (...args) => calls.push(args), post: async (...args) => calls.push(args) };
  const request = localRequest(api);
  await request.get('http://127.0.0.1:8080/admin', { maxRedirects: 10 });
  await request.post('http://127.0.0.1:8080/admin', { maxRedirects: 10, data: { fixture: true } });
  assert.equal(calls.length, 2);
  assert.equal(calls[0][1].maxRedirects, 0);
  assert.equal(calls[1][1].maxRedirects, 0);
  await assert.rejects(request.get('https://homya.eu/admin'), /LOCAL_QA_EXTERNAL_REQUEST_REFUSED/);
  assert.equal(calls.length, 2);
});

test('every browser context blocks external requests, service workers and WebSockets', async () => {
  let routeHandler;
  let websocketHandler;
  let contextOptions;
  const blocked = [];
  const context = {
    route: async (pattern, handler) => { assert.equal(pattern, '**/*'); routeHandler = handler; },
    routeWebSocket: async (pattern, handler) => { assert.equal(pattern, '**/*'); websocketHandler = handler; },
  };
  const browser = { newContext: async options => { contextOptions = options; return context; } };
  assert.equal(await createLocalContext(browser, blocked, { javaScriptEnabled: false }), context);
  assert.equal(contextOptions.serviceWorkers, 'block');
  assert.equal(contextOptions.acceptDownloads, false);
  assert.equal(contextOptions.javaScriptEnabled, false);
  let continued = 0;
  let aborted = 0;
  const route = url => ({ request: () => ({ url: () => url }), continue: async () => continued++, abort: async () => aborted++ });
  await routeHandler(route('http://127.0.0.1:8080/fr'));
  await routeHandler(route('https://example.invalid/fixture'));
  let closed = 0;
  websocketHandler({ close: () => closed++ });
  assert.equal(continued, 1);
  assert.equal(aborted, 1);
  assert.equal(closed, 1);
  assert.deepEqual(blocked, ['blocked-external-request', 'blocked-websocket']);
});

test('refuses to serialize fixture credentials and rejects linked artifact directories', t => {
  assert.throws(() => assertSafeResult({ diagnostic: fixtureCredentials.PASSWORD }, fixtureCredentials), /LOCAL_QA_SENSITIVE_RESULT_REFUSED/);
  assert.throws(() => assertSafeResult({ diagnostic: fixtureCredentials.USERNAME }, fixtureCredentials), /LOCAL_QA_SENSITIVE_RESULT_REFUSED/);
  assert.deepEqual(assertSafeResult({ status: 'done', login: true }, fixtureCredentials), { status: 'done', login: true });
  const { root } = fixture(t);
  fs.mkdirSync(path.join(root, 'elsewhere'), { mode: 0o700 });
  fs.symlinkSync(path.join(root, 'elsewhere'), path.join(root, 'output'));
  assert.throws(() => ensureArtifactDirectory(root, 'output/playwright'), /LOCAL_QA_PRIVATE_INPUT_INVALID/);
});

test('the driver imports static scenarios without eval or credential arguments', () => {
  const directory = path.dirname(new URL(import.meta.url).pathname);
  const runner = fs.readFileSync(path.join(directory, 'runner.mjs'), 'utf8');
  for (const name of ['admin', 'management', 'upload', 'documents']) {
    assert.match(runner, new RegExp(`import ${name} from './${name}\\.mjs'`));
    const scenario = fs.readFileSync(path.join(directory, `${name}.mjs`), 'utf8');
    assert.doesNotMatch(scenario, /\beval\s*\(|new Function\s*\(|storageState\s*[:(]/);
  }
  assert.doesNotMatch(runner, /\beval\s*\(|new Function\s*\(|trace\.|storageState\s*[:(]/);
  assert.match(runner, /readPrivateCredentials\(ROOT\)/);
  assert.match(runner, /requireQuietRuntime\(process\.env\)/);
  assert.ok(runner.indexOf('requireLocalProject(ROOT)') < runner.indexOf('readPrivateCredentials(ROOT)'));
  assert.ok(runner.indexOf('readPrivateCredentials(ROOT)') < runner.indexOf('chromium.launch('));
});

test('screenshot mode reads a private report and accepts only a document fixture path', t => {
  const { root } = fixture(t);
  const directory = ensureArtifactDirectory(root, 'output/qa-commerce');
  const filename = path.join(directory, 'documents-browser.json');
  const invoice = '/admin/documents/aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee';
  fs.writeFileSync(filename, JSON.stringify({ drafts: [{ type: 'invoice', path: invoice }] }), { mode: 0o600 });
  assert.equal(readInvoiceFixturePath(root), invoice);
  fs.writeFileSync(filename, JSON.stringify({ drafts: [{ type: 'invoice', path: 'https://example.invalid/admin' }] }));
  assert.throws(() => readInvoiceFixturePath(root), /LOCAL_QA_PRIVATE_INPUT_INVALID/);
  fs.unlinkSync(filename);
  fs.symlinkSync(path.join(root, 'missing-fixture'), filename);
  assert.throws(() => readInvoiceFixturePath(root));
});

function localEnvironmentFixture(root) {
  fs.mkdirSync(path.join(root, 'bootstrap/cache'), { recursive: true, mode: 0o700 });
  const content = [
    'APP_ENV=local', 'APP_URL=http://127.0.0.1:8080', 'DB_CONNECTION=homya_local_reader',
    'HOMYA_COMMERCE_DB_DATABASE=homya_local', 'HOMYA_COMMERCE_DB_USERNAME=homya_local_commerce',
    'HOMYA_COMMERCE_DB_HOST=127.0.0.1', 'HOMYA_COMMERCE_DB_PORT=3307',
    'HOMYA_COMMERCE_DB_SOCKET=/Applications/XAMPP/xamppfiles/var/mysql/mysql.sock', 'MAIL_MAILER=log',
  ].join('\n') + '\n';
  fs.writeFileSync(path.join(root, '.env'), content, { mode: 0o600 });
  return content;
}

test('allows a local fixture config, rejects production/remote DB/mail and cached config', t => {
  const { root } = fixture(t);
  const content = localEnvironmentFixture(root);
  assert.doesNotThrow(() => requireLocalProject(root));
  for (const [before, after] of [
    ['APP_ENV=local', 'APP_ENV=production'], ['APP_URL=http://127.0.0.1:8080', 'APP_URL=https://homya.eu'],
    ['HOMYA_COMMERCE_DB_HOST=127.0.0.1', 'HOMYA_COMMERCE_DB_HOST=example.invalid'],
    ['HOMYA_COMMERCE_DB_PORT=3307', 'HOMYA_COMMERCE_DB_PORT=3306'],
    ['HOMYA_COMMERCE_DB_SOCKET=/Applications/XAMPP/xamppfiles/var/mysql/mysql.sock', 'HOMYA_COMMERCE_DB_SOCKET='],
    ['HOMYA_COMMERCE_DB_DATABASE=homya_local', 'HOMYA_COMMERCE_DB_DATABASE=fixture_production'],
    ['HOMYA_COMMERCE_DB_USERNAME=homya_local_commerce', 'HOMYA_COMMERCE_DB_USERNAME=root'],
    ['MAIL_MAILER=log', 'MAIL_MAILER=smtp'], ['DB_CONNECTION=homya_local_reader', 'DB_CONNECTION=mysql'],
  ]) {
    fs.writeFileSync(path.join(root, '.env'), content.replace(before, after));
    assert.throws(() => requireLocalProject(root));
  }
  for (const extra of ['DB_URL=mysql://example.invalid/fixture', 'APP_CONFIG_CACHE=/private/tmp/fixture', 'APP_ENV=local']) {
    fs.writeFileSync(path.join(root, '.env'), content + extra + '\n');
    assert.throws(() => requireLocalProject(root));
  }
  fs.writeFileSync(path.join(root, '.env'), content);
  fs.writeFileSync(path.join(root, 'bootstrap/cache/config.php'), '<?php /* synthetic only */', { mode: 0o600 });
  assert.throws(() => requireLocalProject(root), /LOCAL_QA_CONFIG_CACHE_REFUSED/);
});

test('refuses inherited debugging and instrumentation before Playwright is imported', () => {
  assert.doesNotThrow(() => requireQuietRuntime({}));
  for (const key of ['DEBUG', 'PWDEBUG', 'NODE_OPTIONS', 'NODE_V8_COVERAGE', 'PLAYWRIGHT_DEBUG_LOG_FILE']) {
    assert.throws(() => requireQuietRuntime({ [key]: 'synthetic-only' }), /LOCAL_QA_INSTRUMENTATION_REFUSED/);
  }
});

test('uploads only an owned bounded PNG fixture through memory, refusing other files and links', t => {
  const { root } = fixture(t);
  const filename = path.join(root, 'image-fixture.png');
  const png = Buffer.alloc(24);
  Buffer.from([137, 80, 78, 71, 13, 10, 26, 10]).copy(png);
  png.write('IHDR', 12);
  png.writeUInt32BE(600, 16);
  png.writeUInt32BE(600, 20);
  fs.writeFileSync(filename, png, { mode: 0o600 });
  const upload = readSyntheticUploadFixture(filename);
  assert.equal(upload.mimeType, 'image/png');
  assert.deepEqual(upload.buffer, png);
  fs.writeFileSync(filename, Buffer.from('Synthetic non-image fixture, not a secret'));
  assert.throws(() => readSyntheticUploadFixture(filename), /LOCAL_QA_PRIVATE_INPUT_INVALID/);
  fs.unlinkSync(filename);
  fs.symlinkSync(path.join(root, 'missing-fixture'), filename);
  assert.throws(() => readSyntheticUploadFixture(filename));
});
