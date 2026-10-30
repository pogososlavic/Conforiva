<script data-storefront-navigation nonce="{{ request()->attributes->get('security.csp_nonce') }}">
(() => {
    const root = document.documentElement;
    if (root.hasAttribute('data-navigation-ready')) return;
    const mobile = window.matchMedia('(max-width: 959px)');
    const elements = () => ({
        toggle: document.querySelector('[data-menu-toggle]'),
        navigation: document.getElementById('primary-navigation'),
    });
    const setExpanded = (expanded) => {
        const open = mobile.matches && expanded;
        root.toggleAttribute('data-navigation-expanded', open);
        const {toggle, navigation} = elements();
        if (toggle) {
            toggle.hidden = !mobile.matches;
            toggle.setAttribute('aria-expanded', String(open));
            const label = toggle.querySelector('[data-menu-label]');
            if (label) label.textContent = open ? toggle.dataset.closeLabel : toggle.dataset.openLabel;
        }
        if (navigation) navigation.hidden = mobile.matches && !open;
    };
    const adaptNavigation = () => {
        const {toggle, navigation} = elements();
        const focusInside = navigation?.contains(document.activeElement);
        const focusOnToggle = document.activeElement === toggle;
        setExpanded(false);
        if (mobile.matches && focusInside) toggle?.focus();
        if (!mobile.matches && focusOnToggle) navigation?.querySelector('a')?.focus();
    };

    document.addEventListener('click', (event) => {
        const {toggle, navigation} = elements();
        const clickedToggle = event.target?.closest?.('[data-menu-toggle]');
        if (clickedToggle && clickedToggle === toggle && !event.defaultPrevented) {
            setExpanded(!root.hasAttribute('data-navigation-expanded'));
            return;
        }
        const link = event.target?.closest?.('a');
        if (!link || !navigation?.contains(link) || !mobile.matches || event.defaultPrevented || event.button !== 0
            || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
        const target = document.getElementById(new URL(link.href).hash.slice(1));
        // Move focus before hiding the activated link, including same-page anchors.
        if (target) target.focus({preventScroll: true});
        else toggle?.focus();
        setExpanded(false);
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && mobile.matches && root.hasAttribute('data-navigation-expanded')) {
            setExpanded(false);
            elements().toggle?.focus();
        }
    });
    mobile.addEventListener('change', adaptNavigation);

    // CSS consumes this marker before the first paint; no module download is needed.
    root.setAttribute('data-navigation-ready', '');
    const initialize = () => setExpanded(root.hasAttribute('data-navigation-expanded'));
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initialize, {once: true});
    else initialize();
})();
</script>
