const panel = document.querySelector('[data-admin-notifications]');
const soundToggle = document.querySelector('[data-admin-sound-toggle]');
const soundTest = document.querySelector('[data-admin-sound-test]');
const newOrderAlert = document.querySelector('[data-admin-new-order-alert]');
let busy = false;
const soundPreferenceKey = 'homya-admin-order-sound';
let soundEnabled = (() => { try { return window.localStorage.getItem(soundPreferenceKey) === 'enabled'; } catch { return false; } })();
let audioContext = null;
let orderBaselineReady = false;
let latestOrderId = null;

const AudioContextClass = window.AudioContext || window.webkitAudioContext;
const updateSoundControls = () => {
    if (!soundToggle) return;
    soundToggle.setAttribute('aria-pressed', soundEnabled ? 'true' : 'false');
    soundToggle.textContent = soundEnabled ? 'Désactiver le son des commandes' : 'Activer le son des commandes';
    if (soundTest) soundTest.hidden = !soundEnabled;
};
const ensureAudioContext = () => {
    if (!AudioContextClass) throw new Error('audio unavailable');
    audioContext ??= new AudioContextClass();
    return audioContext;
};
const playOrderSound = async () => {
    if (!soundEnabled) return false;
    try {
        const context = ensureAudioContext();
        if (context.state !== 'running') await context.resume();
        if (context.state !== 'running') return false;
        const now = context.currentTime;
        const oscillator = context.createOscillator();
        const volume = context.createGain();
        oscillator.type = 'sine';
        volume.gain.setValueAtTime(0.0001, now);
        [[0, 880], [0.22, 1047], [0.44, 1318]].forEach(([offset, frequency]) => {
            oscillator.frequency.setValueAtTime(frequency, now + offset);
            volume.gain.setValueAtTime(0.0001, now + offset);
            volume.gain.exponentialRampToValueAtTime(0.28, now + offset + 0.025);
            volume.gain.setValueAtTime(0.28, now + offset + 0.12);
            volume.gain.exponentialRampToValueAtTime(0.0001, now + offset + 0.18);
        });
        oscillator.connect(volume);
        volume.connect(context.destination);
        oscillator.start(now);
        oscillator.stop(now + 0.66);
        return true;
    } catch {
        return false;
    }
};

updateSoundControls();
if (soundEnabled && AudioContextClass) {
    try { ensureAudioContext().resume().catch(() => {}); } catch { /* The next dashboard gesture can unlock audio. */ }
}
const wakeAudio = () => {
    if (!soundEnabled || !AudioContextClass) return;
    try { if (ensureAudioContext().state !== 'running') ensureAudioContext().resume().catch(() => {}); } catch { /* Browser audio remains unavailable. */ }
};
document.addEventListener('pointerdown', wakeAudio, {passive: true});
document.addEventListener('keydown', wakeAudio);

soundToggle?.addEventListener('click', async () => {
    if (soundEnabled) {
        soundEnabled = false;
        try { window.localStorage.removeItem(soundPreferenceKey); } catch { /* Audio remains disabled for this page. */ }
        updateSoundControls();
        if (panel) panel.textContent = 'Son des nouvelles commandes désactivé.';
        return;
    }
    try {
        ensureAudioContext();
        await audioContext.resume();
        if (audioContext.state !== 'running') throw new Error('audio blocked');
        soundEnabled = true;
        try { window.localStorage.setItem(soundPreferenceKey, 'enabled'); } catch { /* The preference lasts for this page. */ }
        updateSoundControls();
        const played = await playOrderSound();
        if ('Notification' in window && Notification.permission === 'default') {
            try { await Notification.requestPermission(); } catch { /* Browser notifications are optional; the sound remains enabled. */ }
        }
        if (panel) panel.textContent = played ? 'Son activé. Vous pouvez aussi le tester avec le bouton dédié.' : 'Le navigateur bloque le son. Utilisez « Tester le son » après une interaction avec la page.';
    } catch {
        if (panel) panel.textContent = 'Le son est indisponible dans ce navigateur.';
    }
});
soundTest?.addEventListener('click', async () => {
    const played = await playOrderSound();
    if (panel) panel.textContent = played ? 'Test sonore joué.' : 'Le navigateur bloque encore le son. Vérifiez le volume et les réglages audio du navigateur.';
});

const refresh = async () => {
    if (!panel || busy) return;
    busy = true;
    try {
        const response = await fetch(panel.dataset.adminNotifications, {headers: {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest'}, cache: 'no-store', credentials: 'same-origin', redirect: 'error'});
        if (!response.ok) throw new Error('unavailable');
        const counts = await response.json();
        for (const key of ['messages', 'notifications', 'orders']) {
            const count = counts[key];
            if (!Number.isInteger(count) || count < 0) throw new Error('invalid');
            const badge = document.querySelector(`[data-admin-count="${key}"]`);
            if (badge) {
                badge.textContent = count > 0 ? String(count) : '';
                if (key === 'orders') {
                    badge.setAttribute('aria-label', `${count} commande(s) non consultée(s)`);
                    badge.title = `${count} commande(s) non consultée(s)`;
                }
            }
        }
        const currentLatestOrderId = typeof counts.latest_order_id === 'string' ? counts.latest_order_id : null;
        const newOrderArrived = orderBaselineReady && currentLatestOrderId && currentLatestOrderId !== latestOrderId;
        latestOrderId = currentLatestOrderId;
        orderBaselineReady = true;
        panel.textContent = newOrderArrived ? 'Nouvelle commande reçue.' : 'Notifications actualisées';
        if (newOrderArrived) {
            const played = await playOrderSound();
            if (!played && soundEnabled) panel.textContent = 'Nouvelle commande reçue. Son bloqué : cliquez sur « Tester le son ».';
            if (newOrderAlert) newOrderAlert.hidden = false;
            if ('Notification' in window && Notification.permission === 'granted') {
                try {
                    const notification = new Notification('Nouvelle commande HOMYA', {body: 'Une commande vient d’être enregistrée. Consultez le menu Commandes.'});
                    notification.onclick = () => { window.focus(); window.location.href = panel.dataset.adminOrdersUrl; };
                } catch { /* Keep the in-page alert and sound if the browser blocks system notifications. */ }
            }
        }
    } catch { panel.textContent = 'Actualisation indisponible. Rechargez la page.'; }
    finally { busy = false; }
};
if (panel) { refresh(); setInterval(refresh, 10000); document.addEventListener('visibilitychange', refresh); }
newOrderAlert?.querySelector('a')?.addEventListener('click', () => { newOrderAlert.hidden = true; });
document.querySelectorAll('.admin-form').forEach(form => form.addEventListener('submit', () => {
    if (!form.checkValidity()) return;
    const button = form.querySelector('button[type="submit"], button:not([type])');
    if (button) { button.disabled = true; button.textContent = 'Enregistrement…'; }
}));

// Explicit row actions only. Fiscal amounts are always recalculated by the server.
const documentEditor = document.querySelector('[data-document-editor]');
if (documentEditor) {
    const lines = documentEditor.querySelector('[data-document-lines]');
    const template = documentEditor.querySelector('[data-document-template]');
    documentEditor.querySelector('[data-document-add]')?.addEventListener('click', () => {
        if (!template || lines.children.length >= 50) return;
        const index = Number(lines.dataset.nextLine);
        const fragment = template.content.cloneNode(true);
        fragment.querySelectorAll('[name]').forEach(input => { input.name = input.name.replace('__INDEX__', String(index)); });
        lines.append(fragment);
        lines.dataset.nextLine = String(index + 1);
        lines.lastElementChild.querySelector('input')?.focus();
    });
    lines.addEventListener('click', event => {
        const button = event.target.closest('[data-document-remove]');
        if (button && lines.children.length > 1) button.closest('[data-document-line]').remove();
    });
}
