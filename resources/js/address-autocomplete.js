export function initializeAddressLookup(root = document) {
const minimumInterval = 3100;
let nextRequestAt = 0;
root.querySelectorAll('[data-address-lookup]').forEach((lookup) => {
    const form = lookup.closest('form');
    const prefix = lookup.dataset.fieldPrefix === 'billing_' ? 'billing_' : '';
    const address = form?.querySelector(`[name="${prefix}address"]`);
    const country = form?.querySelector(`[name="${prefix}country"]`);
    const postal = form?.querySelector(`[name="${prefix}postal_code"]`);
    const city = form?.querySelector(`[name="${prefix}city"]`);
    const selectedCountry = () => country.value.trim().toUpperCase();
    const supported = () => ['FR', 'BE', 'DE'].includes(selectedCountry());
    const search = lookup.querySelector('[data-address-search]');
    const automatic = lookup.querySelector('[data-address-auto]');
    const results = lookup.querySelector('[data-address-results]');
    const status = lookup.querySelector('[data-address-status]');
    if (!address || !country || !postal || !city || !search || !automatic || !results || !status) return;
    let request, timer, entries = [], activeIndex = -1, composing = false, choosing = false;
    address.setAttribute('autocomplete', 'off');
    address.setAttribute('role', 'combobox');
    address.setAttribute('aria-autocomplete', 'list');
    address.setAttribute('aria-controls', results.id);
    address.setAttribute('aria-expanded', 'false');
    address.setAttribute('aria-describedby', [address.getAttribute('aria-describedby'), `${results.id.replace(/-suggestions$/, '')}-help`].filter(Boolean).join(' '));
    const dismiss = () => {
        results.hidden = true;
        results.replaceChildren();
        entries = [];
        activeIndex = -1;
        address.setAttribute('aria-expanded', 'false');
        address.removeAttribute('aria-activedescendant');
    };
    const clear = () => {
        window.clearTimeout(timer);
        timer = undefined;
        request?.abort();
        request = undefined;
        dismiss();
        status.textContent = '';
        search.disabled = false;
        lookup.hidden = !supported();
    };
    const choose = (entry) => {
        clear();
        address.value = entry.address;
        postal.value = entry.postal_code;
        city.value = entry.city;
        status.textContent = lookup.dataset.selected;
        for (const field of [address, postal, city]) {
            field.dispatchEvent(new Event('change', {bubbles: true}));
        }
        choosing = true;
        address.focus();
        choosing = false;
    };
    const activate = (index) => {
        activeIndex = index;
        const options = results.querySelectorAll('[role="option"]');
        options.forEach((option, i) => option.setAttribute('aria-selected', String(i === index)));
        address.setAttribute('aria-activedescendant', options[index].id);
        options[index].scrollIntoView({ block: 'nearest' });
    };
    const lookupAddress = async () => {
        clear();
        const query = address.value.trim();
        const queryCountry = selectedCountry();
        if (!supported() || query.length < 3 || query.length > 200) return;
        // Manual searches, Enter and simultaneous timers must share the same slot.
        const remaining = nextRequestAt - Date.now();
        if (remaining > 0) {
            timer = window.setTimeout(lookupAddress, remaining);
            return;
        }
        nextRequestAt = Date.now() + minimumInterval;
        request = new AbortController();
        const activeRequest = request;
        search.disabled = true;
        status.textContent = lookup.dataset.loading;
        try {
            const response = await fetch(lookup.dataset.url, {
                method: 'POST', credentials: 'same-origin', signal: activeRequest.signal,
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': form.querySelector('[name="_token"]').value },
                body: JSON.stringify({ address: query, country: queryCountry }),
            });
            if (!response.ok) throw new Error('Address lookup unavailable');
            const data = await response.json();
            if (activeRequest !== request || !supported() || address.value.trim() !== query || selectedCountry() !== queryCountry) return;
            entries = (Array.isArray(data.suggestions) ? data.suggestions : []).slice(0, 6).filter(entry =>
                typeof entry.address === 'string' && entry.address.length <= 200 &&
                typeof entry.city === 'string' && entry.city.length <= 100 && (queryCountry === 'BE' ? /^\d{4}$/ : /^\d{5}$/).test(entry.postal_code));
            entries.forEach((entry, index) => {
                const item = document.createElement('li');
                item.setAttribute('role', 'presentation');
                const button = document.createElement('button');
                button.type = 'button';
                button.id = `${results.id}-${index}`;
                button.setAttribute('role', 'option');
                button.setAttribute('aria-selected', 'false');
                button.tabIndex = -1;
                button.textContent = `${entry.address} · ${entry.postal_code} ${entry.city}`;
                button.addEventListener('pointerdown', event => event.preventDefault());
                button.addEventListener('click', () => choose(entry));
                item.append(button);
                results.append(item);
            });
            results.hidden = entries.length === 0;
            address.setAttribute('aria-expanded', String(entries.length > 0));
            status.textContent = entries.length ? lookup.dataset.count.replace(':count', String(entries.length)) : lookup.dataset.empty;
        } catch (error) {
            if (activeRequest === request && error.name !== 'AbortError') status.textContent = lookup.dataset.failure;
        } finally {
            if (activeRequest === request) search.disabled = false;
        }
    };
    const schedule = () => {
        clear();
        if (!automatic.checked || composing || !supported() || address.value.trim().length < 3) return;
        // Debounce typing; delivery and billing share the server's twenty requests/minute budget.
        timer = window.setTimeout(lookupAddress, Math.max(400, nextRequestAt - Date.now()));
    };
    address.addEventListener('input', schedule);
    address.addEventListener('focus', () => { if (!choosing) schedule(); });
    address.addEventListener('compositionstart', () => { composing = true; clear(); });
    address.addEventListener('compositionend', () => { composing = false; schedule(); });
    address.addEventListener('keydown', event => {
        if (event.isComposing) return;
        if (event.key === 'Escape') { clear(); return; }
        if (event.key === 'Tab') { clear(); return; }
        if (event.key === 'Enter' && (timer !== undefined || search.disabled)) {
            event.preventDefault();
            if (!search.disabled) lookupAddress();
            return;
        }
        if (!entries.length || results.hidden) return;
        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault();
            activate(event.key === 'ArrowDown' ? (activeIndex + 1) % entries.length : (activeIndex <= 0 ? entries.length - 1 : activeIndex - 1));
        } else if (event.key === 'Enter') {
            // An open suggestion list must never submit the order form.
            event.preventDefault();
            choose(entries[activeIndex < 0 ? 0 : activeIndex]);
        }
    });
    address.addEventListener('blur', clear);
    country.addEventListener('change', schedule);
    country.addEventListener('input', schedule);
    automatic.addEventListener('change', () => { if (automatic.checked) schedule(); else clear(); });
    search.addEventListener('click', () => { address.focus(); lookupAddress(); });
    clear();
});

}
