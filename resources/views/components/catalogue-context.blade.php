<script data-catalogue-context nonce="{{ request()->attributes->get('security.csp_nonce') }}">
(() => {
    const root = document.documentElement;
    if (root.hasAttribute('data-catalogue-context-ready')) return;
    root.setAttribute('data-catalogue-context-ready', '');
    const storageKey = 'home-confort.catalogue-context.v1';
    const lifetime = 30 * 60 * 1000;
    const limits = {q: 120, universe: 24, category: 160, brand: 200, condition: 40, min: 11, max: 11, sort: 20, page: 6, discount: 2};
    const product = (value) => {
        if (typeof value !== 'string' || value === '') return null;
        try {
            const url = new URL(value, window.location.href);
            return url.origin === window.location.origin && !url.username && !url.password ? url.pathname.match(/^\/(fr|en|de)\/produit\/([a-z0-9][a-z0-9-]{0,239})$/) : null;
        } catch { return null; }
    };
    const listing = (value) => {
        try {
            if (typeof value !== 'string' || value.length > 6000) return null;
            const url = new URL(value, window.location.href);
            const path = url.pathname.match(/^\/(fr|en|de)\/(catalogue|promotions)$/);
            if (url.origin !== window.location.origin || url.username || url.password || !path) return null;
            const allowed = path[2] === 'promotions' ? ['q', 'discount', 'page'] : Object.keys(limits).filter(key => key !== 'discount');
            const query = new URLSearchParams();
            for (const [key, value] of url.searchParams) {
                if (!allowed.includes(key)) continue;
                if (query.has(key) || Array.from(value).length > limits[key] || /[\u0000-\u001f\u007f]/.test(value)) return null;
                if (value !== '') query.set(key, value);
            }
            if (query.has('page') && !/^[1-9]\d{0,5}$/.test(query.get('page'))) return null;
            if (Number(query.get('page')) > 100000) return null;
            if (query.has('category') && !/^[a-z0-9][a-z0-9-]{0,159}$/.test(query.get('category'))) return null;
            if (query.has('universe') && !['firewood', 'heating', 'appliances', 'furniture', 'televisions', 'other'].includes(query.get('universe'))) return null;
            if (query.has('sort') && !['name', 'price_asc', 'price_desc'].includes(query.get('sort'))) return null;
            if (query.has('discount') && !['5', '15', '25'].includes(query.get('discount'))) return null;
            for (const key of ['min', 'max']) if (query.has(key) && !/^\d{1,8}([.,]\d{1,2})?$/.test(query.get(key))) return null;
            if (query.has('min') && query.has('max') && Number(query.get('min').replace(',', '.')) > Number(query.get('max').replace(',', '.'))) return null;
            url.search = query.toString();
            url.hash = 'results-heading';
            return url;
        } catch { return null; }
    };
    const read = () => {
        try {
            const raw = window.sessionStorage.getItem(storageKey);
            if (!raw || raw.length > 16000) return [];
            const entries = JSON.parse(raw);
            if (!Array.isArray(entries)) return [];
            return entries.filter(entry => entry && typeof entry.slug === 'string' && /^[a-z0-9][a-z0-9-]{0,239}$/.test(entry.slug)
                && Number.isFinite(entry.savedAt) && entry.savedAt <= Date.now() && Date.now() - entry.savedAt < lifetime && listing(entry.returnTo)).slice(-8);
        } catch { return []; }
    };
    const save = (slug, source) => {
        const entries = read().filter(entry => entry.slug !== slug);
        entries.push({slug, returnTo: source.pathname + source.search + source.hash, savedAt: Date.now()});
        const bounded = entries.slice(-8);
        while (bounded.length && JSON.stringify(bounded).length > 16000) bounded.shift();
        try { window.sessionStorage.setItem(storageKey, JSON.stringify(bounded)); } catch { /* Native links remain usable when storage is unavailable. */ }
    };
    const remember = (event) => {
        if (event.defaultPrevented || (![0, 1].includes(event.button) && !(event.type === 'contextmenu' && event.button === 2))) return;
        const link = event.target?.closest?.('a');
        if (!link?.closest('[data-catalogue-source]')) return;
        const target = product(link.href);
        const source = listing(window.location.href);
        if (target && source) save(target[2], source);
    };
    const restore = () => {
        const page = document.querySelector('[data-catalogue-product]');
        const current = product(window.location.href);
        if (!page || !current || page.hasAttribute('data-catalogue-context-explicit')) return;
        if (current[2] !== page.dataset.catalogueProduct) return;
        const referrerProduct = product(document.referrer);
        const referrerListing = listing(document.referrer);
        // A direct visit must not inherit an unrelated earlier search in this tab.
        if (!referrerListing && referrerProduct?.[2] !== current[2]) return;
        const entry = read().find(entry => entry.slug === current[2]);
        // A new tab may not inherit sessionStorage. Its validated same-origin
        // referrer also reflects a newer listing than any previous saved visit.
        const source = referrerListing || (entry && listing(entry.returnTo));
        if (!source) return;
        if (referrerListing) save(current[2], source);
        source.pathname = source.pathname.replace(/^\/(fr|en|de)\//, '/' + current[1] + '/');
        const back = page.querySelector('[data-catalogue-back]');
        if (back) back.href = source.pathname + source.search + source.hash;
        const search = document.getElementById('catalogue-search');
        if (search && search.value === '') search.value = source.searchParams.get('q') || '';
    };
    document.addEventListener('click', remember, true);
    document.addEventListener('auxclick', remember, true);
    document.addEventListener('contextmenu', remember, true);
    window.addEventListener('pageshow', restore);
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', restore, {once: true});
    else restore();
})();
</script>
