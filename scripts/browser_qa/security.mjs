/** Private local QA boundaries. This module never starts a browser. */
import fs from 'node:fs';
import path from 'node:path';

export const LOCAL_ORIGIN = 'http://127.0.0.1:8080';

function reject() {
  throw new Error('LOCAL_QA_PRIVATE_INPUT_INVALID');
}

function ownedDirectory(directory, privateMode = false) {
  const metadata = fs.lstatSync(directory);
  if (!metadata.isDirectory() || metadata.isSymbolicLink() || metadata.uid !== process.getuid()
      || (metadata.mode & 0o022) || (privateMode && (metadata.mode & 0o777) !== 0o700)) reject();
  return metadata;
}

function trustedAncestors(directory) {
  for (let current = directory;; current = path.dirname(current)) {
    const metadata = fs.lstatSync(current);
    const rootTemporaryDirectory = metadata.uid === 0 && (metadata.mode & 0o1000)
      && ['/tmp', '/private/tmp'].includes(current);
    if (!metadata.isDirectory() || metadata.isSymbolicLink()
        || ![0, process.getuid()].includes(metadata.uid)
        || ((metadata.mode & 0o022) && !rootTemporaryDirectory)) reject();
    if (path.dirname(current) === current) break;
  }
}

export function requireLocalProject(projectRoot) {
  const root = path.resolve(projectRoot);
  if (fs.realpathSync(root) !== root) reject();
  trustedAncestors(root);
  ownedDirectory(root);
  let cacheDirectory = root;
  for (const segment of ['bootstrap', 'cache']) {
    cacheDirectory = path.join(cacheDirectory, segment);
    ownedDirectory(cacheDirectory);
  }
  try {
    fs.lstatSync(path.join(cacheDirectory, 'config.php'));
    throw new Error('LOCAL_QA_CONFIG_CACHE_REFUSED');
  } catch (error) {
    if (error.code !== 'ENOENT') throw error;
  }
  const descriptor = fs.openSync(path.join(root, '.env'), fs.constants.O_RDONLY | fs.constants.O_NOFOLLOW);
  let content;
  try {
    const metadata = fs.fstatSync(descriptor);
    if (!metadata.isFile() || metadata.uid !== process.getuid() || metadata.nlink !== 1
        || (metadata.mode & 0o777) !== 0o600 || metadata.size > 1048576) reject();
    content = fs.readFileSync(descriptor, 'utf8');
  } finally {
    fs.closeSync(descriptor);
  }
  const keys = new Set([
    'APP_ENV', 'APP_URL', 'APP_CONFIG_CACHE', 'DB_CONNECTION', 'DB_URL', 'DB_HOST', 'DB_PORT',
    'DB_SOCKET', 'DB_DATABASE', 'DB_USERNAME', 'HOMYA_COMMERCE_DB_HOST', 'HOMYA_COMMERCE_DB_PORT',
    'HOMYA_COMMERCE_DB_SOCKET', 'HOMYA_COMMERCE_DB_DATABASE', 'HOMYA_COMMERCE_DB_USERNAME', 'MAIL_MAILER',
  ]);
  const values = Object.create(null);
  for (const line of content.split(/\r?\n/)) {
    const match = /^\s*(?:export\s+)?([A-Z][A-Z0-9_]*)\s*=/.exec(line);
    if (!match || !keys.has(match[1])) continue;
    const key = match[1];
    if (!line.startsWith(key + '=') || Object.hasOwn(values, key)) reject();
    let value = line.slice(key.length + 1).trim();
    if (value.startsWith('"') || value.startsWith("'")) {
      const quote = value[0];
      const closing = value.indexOf(quote, 1);
      if (closing < 1 || (value.slice(closing + 1).trim() && !value.slice(closing + 1).trim().startsWith('#'))) reject();
      value = value.slice(1, closing);
    } else value = value.split(/\s+#/, 1)[0].trim();
    if (/[$\\\r\n]/.test(value)) reject();
    values[key] = value;
  }
  if (values.APP_ENV !== 'local' || values.APP_URL !== LOCAL_ORIGIN || values.APP_CONFIG_CACHE
      || values.DB_URL || !['log', 'array'].includes(values.MAIL_MAILER ?? 'log')) {
    throw new Error('LOCAL_QA_LOCAL_ENV_REQUIRED');
  }
  const localBackend = (prefix, account) => values[prefix + 'DATABASE'] === 'homya_local'
    && values[prefix + 'USERNAME'] === account && ['127.0.0.1', 'localhost'].includes(values[prefix + 'HOST'])
    && values[prefix + 'PORT'] === '3307'
    && values[prefix + 'SOCKET'] === '/Applications/XAMPP/xamppfiles/var/mysql/mysql.sock';
  if (!localBackend('HOMYA_COMMERCE_DB_', 'homya_local_commerce')) {
    throw new Error('LOCAL_QA_XAMPP_BACKEND_REQUIRED');
  }
  const connection = values.DB_CONNECTION ?? 'mariadb';
  if (!['mariadb', 'commerce', 'homya_local_reader'].includes(connection)
      || (connection === 'mariadb' && !localBackend('DB_', 'homya_local_reader'))) {
    throw new Error('LOCAL_QA_XAMPP_BACKEND_REQUIRED');
  }
}

export function requireQuietRuntime(environment) {
  for (const key of ['DEBUG', 'PWDEBUG', 'NODE_OPTIONS', 'NODE_V8_COVERAGE', 'PLAYWRIGHT_DEBUG_LOG_FILE']) {
    if (environment[key]) throw new Error('LOCAL_QA_INSTRUMENTATION_REFUSED');
  }
}

export function readSyntheticUploadFixture(filename = '/private/tmp/homya-admin-upload-fixture.png') {
  if (fs.realpathSync(filename) !== path.resolve(filename)) reject();
  const descriptor = fs.openSync(filename, fs.constants.O_RDONLY | fs.constants.O_NOFOLLOW);
  try {
    const metadata = fs.fstatSync(descriptor);
    if (!metadata.isFile() || metadata.uid !== process.getuid() || metadata.nlink !== 1
        || (metadata.mode & 0o022) || metadata.size < 24 || metadata.size > 5242880) reject();
    const buffer = fs.readFileSync(descriptor);
    if (!buffer.subarray(0, 8).equals(Buffer.from([137, 80, 78, 71, 13, 10, 26, 10]))
        || buffer.subarray(12, 16).toString('ascii') !== 'IHDR'
        || buffer.readUInt32BE(16) < 1 || buffer.readUInt32BE(16) > 4096
        || buffer.readUInt32BE(20) < 1 || buffer.readUInt32BE(20) > 4096) reject();
    return { name: 'homya-admin-upload-fixture.png', mimeType: 'image/png', buffer };
  } finally {
    fs.closeSync(descriptor);
  }
}

export function isLocalRequest(url) {
  try {
    const parsed = new URL(url);
    return parsed.origin === LOCAL_ORIGIN && !parsed.username && !parsed.password;
  } catch {
    return false;
  }
}

export function readPrivateCredentials(projectRoot) {
  const root = path.resolve(projectRoot);
  if (fs.realpathSync(root) !== root) reject();
  trustedAncestors(root);
  ownedDirectory(root);
  let directory = root;
  for (const segment of ['storage', 'app', 'private', 'admin']) {
    directory = path.join(directory, segment);
    ownedDirectory(directory);
  }
  const parent = ownedDirectory(directory, true);
  const files = fs.readdirSync(directory).filter(name => /^access-[A-Za-z0-9-]+\.txt$/.test(name)).sort();
  if (!files.length) reject();
  const filename = path.join(directory, files.at(-1));
  const descriptor = fs.openSync(filename, fs.constants.O_RDONLY | fs.constants.O_NOFOLLOW);
  try {
    const metadata = fs.fstatSync(descriptor);
    if (!metadata.isFile() || metadata.uid !== process.getuid() || metadata.nlink !== 1
        || (metadata.mode & 0o777) !== 0o600 || metadata.size < 1 || metadata.size > 65536) reject();
    const content = fs.readFileSync(descriptor, 'utf8');
    const currentParent = fs.lstatSync(directory);
    if (currentParent.dev !== parent.dev || currentParent.ino !== parent.ino
        || currentParent.isSymbolicLink() || fs.realpathSync(filename) !== filename) reject();
    const username = [...content.matchAll(/^Identifiant : (.+)$/gm)];
    const password = [...content.matchAll(/^Mot de passe : (.+)$/gm)];
    if (username.length !== 1 || password.length !== 1) reject();
    const USERNAME = username[0][1].replace(/\r$/, '');
    const PASSWORD = password[0][1].replace(/\r$/, '');
    if (!/^[a-zA-Z0-9._-]{3,80}$/.test(USERNAME) || PASSWORD.length < 16 || PASSWORD.length > 72) reject();
    return { USERNAME, PASSWORD };
  } finally {
    fs.closeSync(descriptor);
  }
}

export function localRequest(api) {
  const request = async (method, url, options = {}) => {
    if (!isLocalRequest(url)) throw new Error('LOCAL_QA_EXTERNAL_REQUEST_REFUSED');
    // APIRequestContext does not use browser route interception. Never follow redirects.
    return api[method](url, { ...options, maxRedirects: 0 });
  };
  return {
    get: (url, options) => request('get', url, options),
    post: (url, options) => request('post', url, options),
  };
}

export async function createLocalContext(browser, blockedRequests, options = {}) {
  const context = await browser.newContext({ ...options, serviceWorkers: 'block', acceptDownloads: false });
  await context.route('**/*', async route => {
    if (isLocalRequest(route.request().url())) await route.continue();
    else {
      blockedRequests.push('blocked-external-request');
      await route.abort('blockedbyclient');
    }
  });
  // WebSockets bypass ordinary route interception; the local recipes do not need them.
  await context.routeWebSocket('**/*', socket => {
    blockedRequests.push('blocked-websocket');
    socket.close();
  });
  return context;
}

export function ensureArtifactDirectory(projectRoot, relativeDirectory) {
  let current = projectRoot;
  for (const segment of relativeDirectory.split('/')) {
    current = path.join(current, segment);
    if (!fs.existsSync(current)) fs.mkdirSync(current, { mode: 0o700 });
    const metadata = fs.lstatSync(current);
    if (!metadata.isDirectory() || metadata.isSymbolicLink() || metadata.uid !== process.getuid()) reject();
    fs.chmodSync(current, 0o700);
  }
  return current;
}

export function readInvoiceFixturePath(projectRoot) {
  const filename = path.join(projectRoot, 'output/qa-commerce/documents-browser.json');
  let directory = projectRoot;
  for (const segment of ['output', 'qa-commerce']) {
    directory = path.join(directory, segment);
    const metadata = fs.lstatSync(directory);
    if (!metadata.isDirectory() || metadata.isSymbolicLink() || metadata.uid !== process.getuid()) reject();
  }
  const descriptor = fs.openSync(filename, fs.constants.O_RDONLY | fs.constants.O_NOFOLLOW);
  try {
    const metadata = fs.fstatSync(descriptor);
    if (!metadata.isFile() || metadata.uid !== process.getuid() || metadata.nlink !== 1
        || (metadata.mode & 0o777) !== 0o600 || metadata.size > 1048576) reject();
    const data = JSON.parse(fs.readFileSync(descriptor, 'utf8'));
    const invoicePath = data.drafts?.find(draft => draft.type === 'invoice')?.path;
    if (!/^\/admin\/documents\/[0-9a-f-]{36}$/.test(invoicePath)) reject();
    return invoicePath;
  } finally {
    fs.closeSync(descriptor);
  }
}

export function assertSafeResult(result, { USERNAME, PASSWORD }) {
  const visit = value => {
    if (typeof value === 'string' && (value.includes(PASSWORD) || value === USERNAME)) {
      throw new Error('LOCAL_QA_SENSITIVE_RESULT_REFUSED');
    }
    if (value && typeof value === 'object') Object.values(value).forEach(visit);
  };
  visit(result);
  return result;
}
