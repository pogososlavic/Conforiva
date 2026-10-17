const root = document.querySelector('[data-analytics-preferences]');
if (root) {
    const id = root.dataset.measurementId;
    const panel = root.querySelector('[data-analytics-panel]');
    const status = root.querySelector('[data-analytics-status]');
    let token;
    let granted = false;
    let loading;
    window[`ga-disable-${id}`] = true;
    const eraseCookies = () => {
        for (const entry of document.cookie.split(';')) {
            const name = entry.trim().split('=')[0];
            if (!/^_ga(?:_|$)/.test(name)) continue;
            for (const domain of ['', `;domain=${location.hostname}`, `;domain=.${location.hostname}`]) {
                document.cookie = `${name}=;Max-Age=0;path=/;SameSite=Lax${domain}`;
            }
        }
    };
    const post = async (path, body) => {
        const response = await fetch(path, {method: 'POST', credentials: 'same-origin', headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': token}, body: JSON.stringify(body)});
        if (!response.ok) throw new Error('analytics_unavailable');
    };
    const start = async () => {
        if (!granted || loading) return;
        window[`ga-disable-${id}`] = false;
        window.dataLayer = window.dataLayer || [];
        window.gtag = function () { window.dataLayer.push(arguments); };
        window.gtag('consent', 'default', {analytics_storage: 'granted', ad_storage: 'denied', ad_user_data: 'denied', ad_personalization: 'denied'});
        window.gtag('js', new Date());
        const publicPath = /^\/(fr|en|de)(?:$|\/(?:catalogue|produit|promotions|a-propos|provenance|conseiller|livraison|paiement|cookies|confidentialite|mentions-legales|cgv|retractation)(?:\/|$))/.test(location.pathname);
        // Preserve only Google's click identifiers after consent, never arbitrary query data.
        const pageLocation = new URL(publicPath ? location.origin + location.pathname : location.origin);
        if (publicPath) {
            const query = new URLSearchParams(location.search || '');
            for (const key of ['gclid', 'gbraid', 'wbraid']) {
                const values = query.getAll(key);
                if (values.length === 1 && /^[A-Za-z0-9_-]{1,512}$/.test(values[0])) pageLocation.searchParams.set(key, values[0]);
            }
        }
        window.gtag('config', id, {send_page_view: false, page_location: pageLocation.href, page_referrer: '', page_title: 'HOMYA', allow_google_signals: false, allow_ad_personalization_signals: false, cookie_expires: 15552000, cookie_update: false});
        if (publicPath) window.gtag('event', 'page_view', {page_location: pageLocation.href, page_referrer: '', page_title: 'HOMYA'});
        window.gtag('get', id, 'client_id', (clientId) => {
            if (granted && /^\d{1,20}\.\d{1,20}$/.test(clientId || '')) post('/mesure/identifiant', {client_id: clientId}).catch(() => {});
        });
        loading = new Promise((resolve, reject) => {
            const script = document.createElement('script');
            script.async = true;
            script.src = `https://www.googletagmanager.com/gtag/js?id=${encodeURIComponent(id)}`;
            script.onload = resolve;
            script.onerror = reject;
            document.head.append(script);
        });
        try { await loading; } catch { window[`ga-disable-${id}`] = true; loading = null; }
    };
    root.querySelector('[data-analytics-open]').addEventListener('click', () => { panel.hidden = !panel.hidden; });
    root.querySelectorAll('[data-analytics-choice]').forEach(button => button.addEventListener('click', async () => {
        window[`ga-disable-${id}`] = true;
        granted = false;
        root.querySelectorAll('[data-analytics-choice]').forEach(b => { b.disabled = true; });
        try {
            const choice = button.dataset.analyticsChoice === 'true';
            await post('/mesure/preferences', {granted: choice});
            eraseCookies();
            status.textContent = status.dataset.success;
            // Reload stops an already loaded Google library after withdrawal and renews the identity on re-consent.
            location.reload();
        } catch { status.textContent = status.dataset.error; }
        finally { root.querySelectorAll('[data-analytics-choice]').forEach(b => { b.disabled = false; }); }
    }));
    fetch('/mesure/preferences', {credentials: 'same-origin', cache: 'no-store', headers: {'Accept': 'application/json'}})
        .then(r => { if (!r.ok) throw new Error(); return r.json(); })
        .then(state => { token = state.csrf; granted = state.choice === true; panel.hidden = state.choice !== null; if (granted) start(); else eraseCookies(); })
        .catch(() => { status.textContent = status.dataset.error; });
}
