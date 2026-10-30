import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';

const moduleSource = fs.readFileSync(new URL('../../resources/js/address-autocomplete.js', import.meta.url), 'utf8');
assert.match(moduleSource, /^export function initializeAddressLookup\(/);
// Evaluate the actual module body against an isolated DOM and fetch double.
const source = moduleSource.replace(/^export /, '') + '\ninitializeAddressLookup(document);';
const settle = () => new Promise(resolve => setImmediate(resolve));

function element(value = '') {
    return {value, checked: false, attrs: {}, events: {}, children: [], dataset: {}, changes: [],
        setAttribute(k, v) {this.attrs[k] = v;}, getAttribute(k) {return this.attrs[k];}, removeAttribute(k) {delete this.attrs[k];},
        addEventListener(k, fn) {this.events[k] = fn;}, replaceChildren() {this.children = [];}, append(x) {this.children.push(x);},
        focus() {this.events.focus?.({});}, scrollIntoView() {},
        dispatchEvent(event) {this.changes.push(event); this.events[event.type]?.(event); return true;},
        querySelectorAll() {return this.children.flatMap(child => child.children);},
    };
}

function suggestion(country, address = 'Chosen synthetic address') {
    return {address, postal_code: country === 'BE' ? '1000' : country === 'DE' ? '10115' : '75001', city: 'Synthetic city'};
}

function fixture(prefix = '', country = 'FR', {automatic = false, deferred = false, suggestions, both = false} = {}) {
    const fields = Object.fromEntries(['address', 'postal_code', 'city', 'country', 'billing_address', 'billing_postal_code', 'billing_city', 'billing_country', '_token'].map(k => [k, element(k.endsWith('country') ? country : k)]));
    fields[prefix + 'address'].value = 'Synthetic address';
    fields._token.value = 'synthetic-csrf';
    const form = {querySelector: selector => fields[selector.match(/name="([^"]+)"/)[1]]};
    const blocks = Object.fromEntries((both ? ['', 'billing_'] : [prefix]).map(fieldPrefix => {
        const nodes = Object.fromEntries(['search', 'auto', 'results', 'status'].map(k => [k, element()]));
        nodes.auto.checked = automatic;
        nodes.results.id = 'checkout-' + fieldPrefix + 'address-suggestions';
        const lookup = {dataset: {fieldPrefix, url: '/synthetic-address-endpoint', selected: 'selected', count: ':count results', loading: 'loading', empty: 'empty', failure: 'failure'}, closest: () => form,
            querySelector: selector => nodes[selector.match(/data-address-(\w+)/)[1]]};
        return [fieldPrefix, {nodes, lookup}];
    }));
    const {nodes, lookup} = blocks[prefix];
    const calls = [], pending = [], timers = new Map();
    let timerId = 0, now = 100000;
    const fetch = (url, options) => {
        const body = JSON.parse(options.body);
        calls.push({url, options, body, startedAt: now});
        const response = entries => ({ok: true, json: async () => ({suggestions: entries})});
        if (deferred) return new Promise(resolve => pending.push(entries => resolve(response(entries))));
        return Promise.resolve(response(suggestions ?? [suggestion(body.country)]));
    };
    const window = {clearTimeout: id => timers.delete(id), setTimeout: (callback, delay) => {
        timers.set(++timerId, {callback, at: now + delay});
        return timerId;
    }};
    const advance = async milliseconds => {
        const target = now + milliseconds;
        while (true) {
            const next = [...timers.entries()].filter(([, timer]) => timer.at <= target).sort((a, b) => a[1].at - b[1].at)[0];
            if (!next) break;
            const [id, timer] = next;
            now = timer.at;
            timers.delete(id);
            timer.callback();
            await settle();
        }
        now = target;
        await settle();
    };
    class EventDouble {
        constructor(type, options) {this.type = type; this.bubbles = options?.bubbles === true;}
    }
    vm.runInNewContext(source, {document: {querySelectorAll: () => Object.values(blocks).map(block => block.lookup), createElement: () => element()}, window, fetch, AbortController, Date: {now: () => now}, Event: EventDouble});
    const key = (value, fieldPrefix = prefix) => {
        const event = {key: value, prevented: false, preventDefault() {this.prevented = true;}};
        fields[fieldPrefix + 'address'].events.keydown(event);
        return event;
    };
    return {fields, nodes, lookup, blocks, calls, pending, timers, key, advance,
        search: async (fieldPrefix = prefix) => {blocks[fieldPrefix].nodes.search.events.click(); await settle();},
        runTimers: async () => advance(Math.max(now, ...[...timers.values()].map(timer => timer.at)) - now),
    };
}

test('Delivery and billing stay isolated for France, Belgium and Germany and publish field changes', async () => {
    for (const country of ['FR', 'BE', 'DE']) {
        for (const prefix of ['', 'billing_']) {
            const state = fixture(prefix, country);
            await state.search();
            assert.equal(state.calls.length, 1);
            assert.equal(state.calls[0].url, '/synthetic-address-endpoint');
            assert.deepEqual(state.calls[0].body, {address: 'Synthetic address', country});
            assert.equal(state.calls[0].options.credentials, 'same-origin');
            assert.equal(state.calls[0].options.headers['X-CSRF-TOKEN'], 'synthetic-csrf');
            state.nodes.results.children[0].children[0].events.click();
            const chosen = suggestion(country);
            for (const field of ['address', 'postal_code', 'city']) {
                assert.equal(state.fields[prefix + field].value, chosen[field]);
                assert.deepEqual(state.fields[prefix + field].changes.map(event => [event.type, event.bubbles]), [['change', true]]);
            }
            const other = prefix ? '' : 'billing_';
            for (const field of ['address', 'postal_code', 'city']) assert.equal(state.fields[other + field].value, other + field);
            assert.equal(state.fields[prefix + 'address'].attrs['aria-controls'], state.nodes.results.id);
            assert.equal(state.fields[prefix + 'address'].attrs['aria-expanded'], 'false');
        }
    }
});

test('Unsupported countries remain manual and never issue address requests', async () => {
    const state = fixture('billing_', 'GB', {automatic: true});
    await state.search();
    state.fields.billing_address.events.input();
    await state.runTimers();
    assert.equal(state.calls.length, 0);
    assert.equal(state.lookup.hidden, true);
    assert.equal(state.nodes.results.children.length, 0);
    assert.equal(state.fields.billing_address.value, 'Synthetic address');
});

test('Country-specific postcodes and malformed results are checked before displaying suggestions', async () => {
    for (const country of ['FR', 'BE', 'DE']) {
        const valid = suggestion(country);
        const state = fixture('', country, {suggestions: [
            {...valid, postal_code: country === 'BE' ? '75001' : '1000'},
            {...valid, address: 'x'.repeat(201)}, {...valid, city: null}, valid,
        ]});
        await state.search();
        assert.equal(state.nodes.results.children.length, 1);
        assert.equal(state.nodes.results.children[0].children[0].textContent,
            `${valid.address} · ${valid.postal_code} ${valid.city}`);
    }
});

test('Changing country aborts the previous request and a late response cannot replace newer suggestions', async () => {
    const state = fixture('', 'FR', {deferred: true});
    await state.search();
    state.fields.country.value = 'BE';
    state.fields.country.events.change();
    assert.equal(state.calls[0].options.signal.aborted, true);
    await state.search();
    assert.equal(state.calls.length, 1);
    await state.advance(3100);
    state.pending[1]([suggestion('BE', 'Current Belgian address')]);
    await settle();
    state.pending[0]([suggestion('FR', 'Stale French address')]);
    await settle();
    assert.equal(state.nodes.results.children.length, 1);
    assert.match(state.nodes.results.children[0].children[0].textContent, /^Current Belgian address/);
    assert.deepEqual(state.calls.map(call => call.body.country), ['FR', 'BE']);
});

test('Editing an address dismisses suggestions and rejects the stale result even when country is unchanged', async () => {
    const state = fixture('', 'FR', {deferred: true});
    await state.search();
    state.fields.address.value = 'New synthetic query';
    state.fields.address.events.input();
    state.pending[0]([suggestion('FR')]);
    await settle();
    assert.equal(state.calls[0].options.signal.aborted, true);
    assert.equal(state.nodes.results.children.length, 0);
    assert.equal(state.fields.address.value, 'New synthetic query');
});

test('Enter during debounce, loading and an open suggestion list never submits the checkout', async () => {
    const state = fixture('', 'FR', {automatic: true, deferred: true});
    state.fields.address.events.input();
    assert.equal(state.timers.size, 1);
    assert.equal(state.key('Enter').prevented, true);
    assert.equal(state.calls.length, 1);
    assert.equal(state.key('Enter').prevented, true);
    assert.equal(state.calls.length, 1);
    state.pending[0]([suggestion('FR')]);
    await settle();
    assert.equal(state.key('Enter').prevented, true);
    assert.equal(state.fields.address.value, 'Chosen synthetic address');
    await state.runTimers();
    assert.equal(state.calls.length, 1);
});

test('Manual search clears focus debounce and Escape restores a closed accessible combobox', async () => {
    const state = fixture('', 'FR', {automatic: true});
    await state.search();
    await state.runTimers();
    assert.equal(state.calls.length, 1);
    assert.equal(state.fields.address.attrs['aria-expanded'], 'true');
    state.key('ArrowDown');
    assert.equal(state.fields.address.attrs['aria-activedescendant'], state.nodes.results.id + '-0');
    state.key('Escape');
    assert.equal(state.nodes.results.children.length, 0);
    assert.equal(state.fields.address.attrs['aria-expanded'], 'false');
    assert.equal(state.fields.address.attrs['aria-activedescendant'], undefined);
});

test('Delivery and billing coalesce pending input and share a minimum 3100 ms request interval', async () => {
    const state = fixture('', 'FR', {automatic: true, both: true});
    await state.search();
    state.fields.address.value = 'Latest delivery query';
    state.fields.address.events.input();
    for (const address of ['Earlier billing query', 'Latest billing query']) {
        state.fields.billing_address.value = address;
        state.fields.billing_address.events.input();
    }
    assert.equal(state.timers.size, 2, 'Only one pending timer is retained per address block');
    assert.deepEqual([...state.timers.values()].map(timer => timer.at), [103100, 103100]);
    assert.equal(state.key('Enter', 'billing_').prevented, true);
    await state.search('billing_');
    assert.equal(state.calls.length, 1, 'Enter and manual search cannot bypass the shared cooldown');
    assert.equal(state.timers.size, 2);
    await state.advance(3099);
    assert.equal(state.calls.length, 1);
    await state.advance(1);
    assert.equal(state.calls.length, 2, 'Simultaneous timers cannot issue simultaneous requests');
    assert.equal(state.timers.size, 1);
    await state.advance(3100);
    assert.equal(state.calls.length, 3);
    assert.deepEqual(state.calls.map(call => call.startedAt), [100000, 103100, 106200]);
    assert.deepEqual(state.calls.map(call => call.body.address), ['Synthetic address', 'Latest delivery query', 'Latest billing query']);
    assert.equal(state.timers.size, 0);
});
