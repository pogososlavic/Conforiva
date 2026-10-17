import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';
const app = fs.readFileSync(new URL('../../resources/js/app.js', import.meta.url), 'utf8');
const source = app.slice(app.indexOf("document.querySelectorAll('[data-address-lookup]')"));
function element(value = '') {
    return {value, checked: false, attrs: {}, events: {}, children: [], dataset: {},
        setAttribute(k, v) {this.attrs[k] = v;}, getAttribute(k) {return this.attrs[k];}, removeAttribute(k) {delete this.attrs[k];},
        addEventListener(k, fn) {this.events[k] = fn;}, replaceChildren() {this.children = [];}, append(x) {this.children.push(x);}, focus() {}};
}
async function fixture(prefix, country = 'FR') {
    const fields = Object.fromEntries(['address', 'postal_code', 'city', 'country', 'billing_address', 'billing_postal_code', 'billing_city', 'billing_country', '_token'].map(k => [k, element(k.endsWith('country') ? country : k)]));
    fields[prefix + 'address'].value = 'Synthetic address';
    const nodes = Object.fromEntries(['search', 'auto', 'results', 'status'].map(k => [k, element()]));
    nodes.results.id = 'checkout-' + prefix + 'address-suggestions';
    const form = {querySelector: selector => fields[selector.match(/name="([^"]+)"/)[1]]};
    const lookup = {dataset: {fieldPrefix: prefix, url: '/synthetic-address-endpoint', selected: 'selected', count: ':count results'}, closest: () => form,
        querySelector: selector => nodes[selector.match(/data-address-(\w+)/)[1]]};
    const calls = [];
    const fetch = async (_url, options) => {calls.push(JSON.parse(options.body)); return {ok: true, json: async () => ({suggestions: [{address: 'Chosen synthetic address', postal_code: '75001', city: 'Paris'}]})};};
    vm.runInNewContext(source, {document: {querySelectorAll: () => [lookup], createElement: () => element()}, window: {clearTimeout() {}, setTimeout() {}}, fetch, AbortController, Date});
    nodes.search.events.click();
    await new Promise(resolve => setImmediate(resolve));
    return {fields, nodes, calls};
}
test('Delivery and billing lookups fill only their own address fields', async () => {
    for (const prefix of ['', 'billing_']) {
        const {fields, nodes, calls} = await fixture(prefix);
        assert.equal(calls.length, 1);
        nodes.results.children[0].children[0].events.click();
        assert.equal(fields[prefix + 'address'].value, 'Chosen synthetic address');
        assert.equal(fields[prefix + 'postal_code'].value, '75001');
        assert.equal(fields[prefix + 'city'].value, 'Paris');
        const other = prefix ? '' : 'billing_';
        assert.equal(fields[other + 'city'].value, other + 'city');
        assert.equal(fields[prefix + 'address'].attrs['aria-controls'], nodes.results.id);
    }
});
test('Foreign billing addresses stay manual and are not sent to IGN', async () => {
    const {nodes, calls} = await fixture('billing_', 'GB');
    assert.equal(calls.length, 0);
    assert.equal(nodes.results.children.length, 0);
});
