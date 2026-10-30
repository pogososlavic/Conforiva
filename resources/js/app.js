import {initializeAddressLookup} from './address-autocomplete';
import {initializeImageFraming} from './product-image-framing';
import './catalogue-variants';
import './analytics-consent';

document.querySelectorAll('[data-language-link]').forEach((link) => {
    link.addEventListener('click', () => {
        // Keep the same section or catalogue results when switching language.
        if (/^#(?:universes|universe-[a-z-]+|results-heading|featured|discoveries|choosing|questions|product-description|product-specifications|product-variants|related-products|department-(?:washing|coffee)|legal-[a-z-]+)$/.test(window.location.hash)
            && document.getElementById(window.location.hash.slice(1))) {
            link.hash = window.location.hash;
        }
    });
});

document.querySelectorAll('[data-product-media]').forEach((media) => {
    const image = media.querySelector('[data-product-image]');
    const fallback = media.querySelector('[data-image-fallback]');
    if (!image || !fallback) return;
    const showFailure = () => {
        if (image.hasAttribute('srcset')) {
            image.removeAttribute('srcset');
            image.src = image.getAttribute('src');
            return;
        }
        image.hidden = true;
        fallback.hidden = false;
    };
    image.addEventListener('error', showFailure);
    image.addEventListener('load', () => { image.hidden = false; fallback.hidden = true; });
    if (image.complete && image.naturalWidth === 0) showFailure();
});

document.querySelectorAll('[data-product-media][data-image-framing]').forEach(media => initializeImageFraming(media));

document.querySelectorAll('[data-gallery]').forEach((gallery) => {
    const image = gallery.querySelector('[data-product-image]');
    const caption = gallery.querySelector('[data-gallery-caption]');
    const buttons = gallery.querySelectorAll('[data-gallery-src]');
    if (!image || !caption) return;
    buttons.forEach((button) => button.addEventListener('click', () => {
        buttons.forEach((other) => other.setAttribute('aria-pressed', String(other === button)));
        image.hidden = false;
        gallery.querySelector('[data-image-fallback]').hidden = true;
        if (button.dataset.galleryWidth && button.dataset.galleryHeight) {
            image.setAttribute('width', button.dataset.galleryWidth);
            image.setAttribute('height', button.dataset.galleryHeight);
        } else {
            image.removeAttribute('width');
            image.removeAttribute('height');
        }
        const srcset = button.dataset.gallerySrcset;
        if (srcset) image.setAttribute('srcset', srcset);
        else image.removeAttribute('srcset');
        const media = image.closest('[data-product-media]');
        if (media.hasAttribute('data-image-framing')) {
            media.dataset.imageFraming = button.dataset.galleryFraming || 'null';
            image.setAttribute('sizes', image.dataset.framingSizes);
        }
        image.src = button.dataset.gallerySrc;
        media.dispatchEvent(new Event('product-image-source-change'));
        caption.textContent = caption.dataset.template.replace(':current', button.dataset.galleryNumber).replace(':total', caption.dataset.total);
    }));
    gallery.querySelectorAll('[data-gallery-step]').forEach((arrow) => {
        arrow.hidden = false;
        arrow.addEventListener('click', () => {
            const list = [...buttons];
            const current = list.findIndex((button) => button.getAttribute('aria-pressed') === 'true');
            list[(current + Number(arrow.dataset.galleryStep) + list.length) % list.length]?.click();
        });
    });
});

document.querySelectorAll('[data-gallery-thumbnail]').forEach((image) => {
    image.addEventListener('error', () => {
        if (image.hasAttribute('srcset')) {
            image.removeAttribute('srcset');
            image.src = image.getAttribute('src');
        } else image.hidden = true;
    });
    if (image.complete && image.naturalWidth === 0) image.hidden = true;
});

const filters = document.querySelector('[data-filter-panel]');
if (filters) {
    const compact = window.matchMedia('(max-width: 959px)');
    const adaptFilters = () => {
        // A validation error keeps the form visible. No-JS users retain the native disclosure.
        const focused = filters.contains(document.activeElement);
        const resetNotice = document.getElementById('category-reset');
        filters.open = !compact.matches || Boolean(document.querySelector('.form-error'))
            || Boolean(resetNotice && !resetNotice.hidden) || focused;
    };
    compact.addEventListener('change', adaptFilters);
    adaptFilters();
}

// Category membership comes from the same real products used by the server.
const universeSelect = document.getElementById('filter-universe');
const categorySelect = document.getElementById('filter-category');
const categoryOptions = document.getElementById('category-options');
const categoryReset = document.getElementById('category-reset');
if (universeSelect && categorySelect && categoryOptions) {
    const allOption = categorySelect.options[0].cloneNode(true);
    const options = Array.from(categoryOptions.content.querySelectorAll('option'));
    const adaptCategories = () => {
        const previous = categorySelect.value;
        const available = options.filter((option) => !universeSelect.value
            || option.dataset.universes.split(',').includes(universeSelect.value));
        categorySelect.replaceChildren(allOption.cloneNode(true), ...available.map((option) => option.cloneNode(true)));
        const preserved = available.some((option) => option.value === previous);
        categorySelect.value = preserved ? previous : '';
        if (previous && !preserved && categoryReset) {
            categoryReset.hidden = false;
            if (filters) filters.open = true;
        }
    };
    universeSelect.addEventListener('change', adaptCategories);
    categorySelect.addEventListener('change', () => { if (categoryReset) categoryReset.hidden = true; });
    // Reconcile values restored by the browser's back/forward cache as well.
    window.addEventListener('pageshow', adaptCategories);
    adaptCategories();
}

// Native fragment navigation scrolls to the results; restore useful keyboard focus too.
if (window.location.hash === '#results-heading') document.getElementById('results-heading')?.focus({ preventScroll: true });
document.querySelector('.form-error')?.focus();

// Measured sticky header offset also protects anchors with long translated labels.
const stickyHeader = document.querySelector('.home-header-wrap');
if (stickyHeader && 'ResizeObserver' in window) {
    const updateAnchorOffset = () => {
        const height = getComputedStyle(stickyHeader).position === 'sticky' ? stickyHeader.getBoundingClientRect().height : 0;
        document.documentElement.style.setProperty('--anchor-offset', `${Math.ceil(height) + 20}px`);
    };
    const observer = new ResizeObserver(updateAnchorOffset);
    observer.observe(stickyHeader);
    window.addEventListener('resize', updateAnchorOffset);
}

document.querySelector('[data-open-category-filter]')?.addEventListener('click', () => {
    if (!filters) return;
    filters.open = true;
    document.getElementById('filter-category')?.focus({ preventScroll: true });
});

document.querySelectorAll('[data-quantity-control]').forEach((control) => {
    const input = control.querySelector('input');
    const buttons = control.querySelectorAll('[data-quantity-step]');
    if (!input) return;
    const minimum = Number(input.min) || 1;
    const maximum = Number(input.max) || 99;
    const update = () => buttons.forEach((button) => {
        button.hidden = false;
        button.disabled = Number(button.dataset.quantityStep) < 0 ? Number(input.value) <= minimum : Number(input.value) >= maximum;
    });
    buttons.forEach((button) => button.addEventListener('click', () => {
        const current = Number(input.value);
        input.value = String(Math.min(maximum, Math.max(minimum, (Number.isFinite(current) ? current : minimum) + Number(button.dataset.quantityStep))));
        input.dispatchEvent(new Event('input', { bubbles: true }));
        input.dispatchEvent(new Event('change', { bubbles: true }));
    }));
    input.addEventListener('input', update);
    update();
});

const submitButtons = (form) => [...form.elements].filter((element) => element.tagName === 'BUTTON' && element.type === 'submit');
document.querySelectorAll('.checkout-form, .product-cart-form, .product-card-purchase form, [data-commerce-action], [data-cart-quantity], [data-cart-remove]').forEach((form) => {
    form.addEventListener('submit', (event) => {
        if (form.dataset.submitting === 'true') {
            event.preventDefault();
            return;
        }
        form.dataset.submitting = 'true';
        form.setAttribute('aria-busy', 'true');
        if (form.hasAttribute('data-cart-quantity')) {
            form.querySelectorAll('[data-quantity-step]').forEach((button) => { button.disabled = true; });
            form.querySelector('input[name="quantity"]').readOnly = true;
        }
        // Keep the chosen button's intent in the submitted data; do not disable it before serialization.
        submitButtons(form).forEach((button) => {
            button.setAttribute('aria-disabled', 'true');
            button.querySelectorAll('[data-submit-label]').forEach((label) => {
                if (!button.dataset.busyLabel) return;
                label.dataset.originalLabel = label.textContent;
                label.textContent = button.dataset.busyLabel;
            });
        });
    });
});
window.addEventListener('pageshow', () => {
    document.querySelectorAll('form[data-submitting]').forEach((form) => {
        delete form.dataset.submitting;
        form.removeAttribute('aria-busy');
        if (form.hasAttribute('data-cart-quantity')) {
            const input = form.querySelector('input[name="quantity"]');
            input.readOnly = false;
            input.dispatchEvent(new Event('input', { bubbles: true }));
        }
        submitButtons(form).forEach((button) => {
            button.removeAttribute('aria-disabled');
            button.querySelectorAll('[data-submit-label]').forEach((label) => {
                if (!label.dataset.originalLabel) return;
                label.textContent = label.dataset.originalLabel;
                delete label.dataset.originalLabel;
            });
        });
    });
});
document.querySelector('.commerce-success, .commerce-errors')?.focus({ preventScroll: true });

document.querySelectorAll('[data-cart-quantity]').forEach((form) => {
    form.addEventListener('click', (event) => {
        if (!event.target.closest('[data-quantity-step]') || form.dataset.submitting === 'true') return;
        // Quantity buttons have already updated the native input; the server recalculates all amounts.
        form.requestSubmit();
    });
});

document.querySelectorAll('[data-copy-target]').forEach((button) => {
    const value = document.getElementById(button.dataset.copyTarget);
    const feedback = document.getElementById('bank-copy-feedback');
    if (!value || !feedback) return;
    button.hidden = false;
    button.addEventListener('click', async () => {
        try {
            await navigator.clipboard.writeText(value.textContent.trim());
            feedback.textContent = button.dataset.copySuccess;
        } catch {
            feedback.textContent = button.dataset.copyFailure;
        }
    });
});

initializeAddressLookup();

// Make horizontally scrollable product sections discoverable on small screens.
const sectionLinks = document.querySelector('.product-section-navigation');
const sectionNext = document.querySelector('[data-section-next]');
const sectionPrevious = document.querySelector('[data-section-previous]');
if (sectionLinks && sectionNext && sectionPrevious) {
    const sectionMobile = window.matchMedia('(max-width: 599px)');
    const updateSectionHint = () => {
        sectionNext.hidden = !sectionMobile.matches || sectionLinks.scrollWidth <= sectionLinks.clientWidth + 2;
        sectionPrevious.hidden = sectionNext.hidden || sectionLinks.scrollLeft <= 2;
        sectionNext.disabled = sectionLinks.scrollLeft + sectionLinks.clientWidth >= sectionLinks.scrollWidth - 2;
    };
    sectionNext.addEventListener('click', () => {
        sectionLinks.scrollBy({ left: sectionLinks.clientWidth * .7, behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'instant' : 'smooth' });
    });
    sectionPrevious.addEventListener('click', () => {
        sectionLinks.scrollBy({ left: -sectionLinks.clientWidth * .7, behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'instant' : 'smooth' });
    });
    sectionLinks.addEventListener('scroll', updateSectionHint, { passive: true });
    new ResizeObserver(updateSectionHint).observe(sectionLinks);
    sectionMobile.addEventListener('change', updateSectionHint);
    updateSectionHint();
}

// Native category disclosures also close with Escape or a click outside navigation.
const categoryDisclosures = document.querySelectorAll('.category-dropdown');
let categoryEscapeClosing = false;
document.addEventListener('keydown', event => {
    if (event.key !== 'Escape') return;
    categoryEscapeClosing = true;
    const active = Array.from(categoryDisclosures).find(detail => detail.open && detail.contains(document.activeElement));
    categoryDisclosures.forEach(detail => { detail.open = false; });
    active?.querySelector('summary')?.focus();
    categoryEscapeClosing = false;
});
document.addEventListener('click', event => {
    if (event.target.closest('#primary-navigation')) return;
    categoryDisclosures.forEach(detail => { detail.open = false; });
});

const desktopCategoryHover = window.matchMedia('(min-width: 960px) and (hover: hover) and (pointer: fine)');
categoryDisclosures.forEach(detail => {
    let opening;
    let closing;
    const cancelTimers = () => { clearTimeout(opening); clearTimeout(closing); };
    const openCategory = () => {
        categoryDisclosures.forEach(other => { if (other !== detail) other.open = false; });
        detail.open = true;
    };
    detail.addEventListener('pointerenter', () => {
        if (!desktopCategoryHover.matches) return;
        cancelTimers();
        opening = setTimeout(openCategory, 100);
    });
    detail.addEventListener('pointerleave', () => {
        if (!desktopCategoryHover.matches) return;
        cancelTimers();
        closing = setTimeout(() => {
            if (!detail.contains(document.activeElement)) detail.open = false;
        }, 180);
    });
    detail.addEventListener('focusin', () => {
        if (categoryEscapeClosing || !desktopCategoryHover.matches) return;
        cancelTimers();
        openCategory();
    });
    detail.addEventListener('focusout', event => {
        if (!desktopCategoryHover.matches || detail.contains(event.relatedTarget)) return;
        cancelTimers();
        detail.open = false;
    });
    detail.querySelector('summary').addEventListener('click', event => {
        if (!desktopCategoryHover.matches || event.detail === 0) return;
        event.preventDefault();
        cancelTimers();
        openCategory();
    });
    desktopCategoryHover.addEventListener('change', () => { cancelTimers(); detail.open = false; });
    document.addEventListener('keydown', event => { if (event.key === 'Escape') cancelTimers(); });
});
