/** Static local QA runner: secrets are read here, in memory, never passed to a CLI. */
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { createRequire } from 'node:module';
import admin from './admin.mjs';
import management from './management.mjs';
import upload from './upload.mjs';
import documents from './documents.mjs';
import {
  assertSafeResult, createLocalContext, ensureArtifactDirectory, localRequest, readPrivateCredentials,
  requireLocalProject, requireQuietRuntime,
} from './security.mjs';

const ROOT = fs.realpathSync(path.join(path.dirname(fileURLToPath(import.meta.url)), '../..'));
const scenarios = { admin, management, upload, documents };

async function main() {
  process.umask(0o077);
  requireQuietRuntime(process.env);
  const [scenarioName, modulePath, mode] = process.argv.slice(2);
  if (!Object.hasOwn(scenarios, scenarioName) || !modulePath || !path.isAbsolute(modulePath)
      || (mode && (scenarioName !== 'documents' || mode !== '--screenshots-only'))
      || process.argv.length > 5) throw new Error('LOCAL_QA_ARGUMENTS_INVALID');
  requireLocalProject(ROOT);
  const credentials = readPrivateCredentials(ROOT);
  ensureArtifactDirectory(ROOT, 'output/playwright');
  const require = createRequire(import.meta.url);
  const { chromium } = require(modulePath);
  const browserEnvironment = { HOME: process.env.HOME, PATH: process.env.PATH, LANG: 'en_US.UTF-8' };
  if (process.env.PLAYWRIGHT_BROWSERS_PATH) browserEnvironment.PLAYWRIGHT_BROWSERS_PATH = process.env.PLAYWRIGHT_BROWSERS_PATH;
  const browser = await chromium.launch({ headless: true, env: browserEnvironment });
  // Close the ephemeral browser before the Python timeout can terminate this runner.
  const deadline = setTimeout(() => browser.close().catch(() => {}), 140000);
  deadline.unref();
  const blockedRequests = [];
  const contexts = [];
  const newContext = async options => {
    const context = await createLocalContext(browser, blockedRequests, options);
    contexts.push(context);
    return context;
  };
  const screenshot = async (page, options) => {
    const target = path.resolve(ROOT, options.path);
    const artifactRoot = path.join(ROOT, 'output/playwright') + path.sep;
    if (!target.startsWith(artifactRoot)) throw new Error('LOCAL_QA_ARTIFACT_PATH_INVALID');
    if (fs.existsSync(target)) {
      const metadata = fs.lstatSync(target);
      if (!metadata.isFile() || metadata.isSymbolicLink() || metadata.uid !== process.getuid()
          || metadata.nlink !== 1) throw new Error('LOCAL_QA_ARTIFACT_PATH_INVALID');
      fs.chmodSync(target, 0o600);
    }
    await page.screenshot({ ...options, path: target,
      mask: [page.locator('input[type=password], [name=code], .admin-reference')],
    });
    fs.chmodSync(target, 0o600);
  };
  try {
    const context = await newContext();
    const page = await context.newPage();
    const result = await scenarios[scenarioName](page, {
      ...credentials, ROOT, newContext, request: localRequest(context.request),
      requestFor: context => localRequest(context.request), screenshot,
      screenshotsOnly: mode === '--screenshots-only',
    });
    if (blockedRequests.length) {
      result.failure = true;
      result.blockedRequests = blockedRequests;
    }
    process.stdout.write(JSON.stringify(assertSafeResult(result, credentials)) + '\n');
  } finally {
    clearTimeout(deadline);
    for (const context of contexts.reverse()) await context.close().catch(() => {});
    await browser.close();
  }
}

main().catch(() => {
  // Never print exception text, browser logs, request bodies or submitted credentials.
  process.stdout.write('{"result":"failed","reason":"BROWSER_CHECK_FAILED"}\n');
  process.exitCode = 1;
});
