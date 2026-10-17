import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';

const source = fs.readFileSync(new URL('../../resources/js/analytics-consent.js', import.meta.url), 'utf8');
async function run(choice) {
    const events = {};
    const buttons = ['true', 'false'].map(value => ({dataset: {analyticsChoice: value}, addEventListener: (_name, fn) => {events[value] = fn;}}));
    const status = {dataset: {success: 'saved', error: 'error'}};
    const panel = {hidden: true};
    const scripts = [];
    const calls = [];
    const root = {dataset: {measurementId: 'G-FIXTURE123'}, querySelectorAll: () => buttons,
        querySelector: selector => selector === '[data-analytics-panel]' ? panel : selector === '[data-analytics-status]' ? status : {addEventListener() {}}};
    const window = {};
    let reloads = 0;
    const document = {cookie: '_ga=123.456', querySelector: () => root, head: {append: s => {scripts.push(s); s.onload();}}, createElement: () => ({})};
    const fetch = async (url, options) => {calls.push({url, options}); return {ok: true, json: async () => ({choice, csrf: 'synthetic'})};};
    vm.runInNewContext(source, {window, document, fetch, location: {origin: 'https://homya.eu', hostname: 'homya.eu', pathname: '/fr', reload: () => {reloads++;}}, encodeURIComponent});
    await new Promise(resolve => setImmediate(resolve));
    return {events, scripts, calls, window, panel, reloads: () => reloads};
}

test('No Google script loads without an explicit choice or after refusal', async () => {
    for (const choice of [null, false]) {
        const state = await run(choice);
        assert.equal(state.scripts.length, 0);
        assert.equal(state.window['ga-disable-G-FIXTURE123'], true);
        assert.equal(state.calls.length, 1);
    }
});
test('Consent loads one tag with no automatic page view or advertising consent', async () => {
    const state = await run(true);
    assert.equal(state.scripts.length, 1);
    assert.match(state.scripts[0].src, /^https:\/\/www.googletagmanager.com\/gtag\/js\?id=G-FIXTURE123$/);
    const commands = state.window.dataLayer.map(args => Array.from(args));
    assert.equal(commands.find(c => c[0] === 'config')[2].send_page_view, false);
    assert.equal(commands.find(c => c[0] === 'consent')[2].ad_storage, 'denied');
    assert.equal(commands.filter(c => c[0] === 'event' && c[1] === 'purchase').length, 0);
});
test('Withdrawal disables collection before saving and reloads to stop the library', async () => {
    const state = await run(true);
    await state.events.false();
    assert.equal(state.window['ga-disable-G-FIXTURE123'], true);
    assert.equal(JSON.parse(state.calls.at(-1).options.body).granted, false);
    assert.equal(state.reloads(), 1);
});
