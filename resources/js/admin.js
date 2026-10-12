const panel = document.querySelector('[data-admin-notifications]');
const soundToggle = document.querySelector('[data-admin-sound-toggle]');
const newOrderAlert = document.querySelector('[data-admin-new-order-alert]');
let busy = false;
let soundEnabled = false;
let audioContext = null;
let orderBaselineReady = false;
let latestOrderId = null;

const playOrderSound = () => {
    if (!soundEnabled || !audioContext || audioContext.state !== 'running') return;
    const now = audioContext.currentTime;
    const oscillator = audioContext.createOscillator();
    const volume = audioContext.createGain();
    oscillator.type = 'sine';
    oscillator.frequency.setValueAtTime(880, now);
    oscillator.frequency.setValueAtTime(1174, now + 0.14);
    volume.gain.setValueAtTime(0.001, now);
    volume.gain.exponentialRampToValueAtTime(0.12, now + 0.025);
    volume.gain.setValueAtTime(0.12, now + 0.14);
    volume.gain.exponentialRampToValueAtTime(0.001, now + 0.32);
    oscillator.connect(volume);
    volume.connect(audioContext.destination);
    oscillator.start(now);
    oscillator.stop(now + 0.33);
};

soundToggle?.addEventListener('click', async () => {
    if (soundEnabled) {
        soundEnabled = false;
        soundToggle.setAttribute('aria-pressed', 'false');
        soundToggle.textContent = 'Activer le son des commandes';
        return;
    }
    try {
        const AudioContextClass = window.AudioContext || window.webkitAudioContext;
        if (!AudioContextClass) throw new Error('audio unavailable');
        audioContext ??= new AudioContextClass();
        await audioContext.resume();
        soundEnabled = true;
        soundToggle.setAttribute('aria-pressed', 'true');
        soundToggle.textContent = 'Désactiver le son des commandes';
        playOrderSound();
        if ('Notification' in window && Notification.permission === 'default') {
            try { await Notification.requestPermission(); } catch { /* Browser notifications are optional; the sound remains enabled. */ }
        }
        if (panel) panel.textContent = 'Son des nouvelles commandes activé.';
    } catch {
        if (panel) panel.textContent = 'Le son est indisponible dans ce navigateur.';
    }
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
            playOrderSound();
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
