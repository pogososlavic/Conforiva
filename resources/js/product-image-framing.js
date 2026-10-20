/** Crop the white display canvas, never the source pixels. */
export function parseImageFrame(value, source) {
    try {
        if (typeof value !== 'string' || value.length > 1500) return null;
        const frame = JSON.parse(value);
        if (!frame || frame.src !== source || !/^\/media\/catalogue\/[a-f0-9]{24}\.(jpg|png|webp|gif)$/.test(source)) return null;
        if (![frame.width, frame.height].every(n => Number.isInteger(n) && n >= 500 && n <= 64000)
            || frame.width * frame.height > 64000000 || !Array.isArray(frame.bounds) || frame.bounds.length !== 4
            || !frame.bounds.every(Number.isInteger)) return null;
        const [left, top, right, bottom] = frame.bounds;
        return left >= 0 && top >= 0 && right <= frame.width && bottom <= frame.height
            && right - left >= 20 && bottom - top >= 20 ? frame : null;
    } catch { return null; }
}

export function imageFrameLayout(frame, width, height, padding = 12, density = 1) {
    if (![width, height, padding, density].every(Number.isFinite) || width <= 0 || height <= 0 || density <= 0 || padding < 0) return null;
    const [left, top, right, bottom] = frame.bounds;
    const cropWidth = right - left, cropHeight = bottom - top;
    const availableWidth = width - 2 * padding, availableHeight = height - 2 * padding;
    if (availableWidth <= 0 || availableHeight <= 0) return null;
    const scale = Math.min(availableWidth / cropWidth, availableHeight / cropHeight, 1 / Math.max(1, density));
    const wholeScale = Math.min(availableWidth / frame.width, availableHeight / frame.height);
    // Low-resolution photos must not become softer or smaller just to remove a margin.
    if (!Number.isFinite(scale) || scale <= wholeScale * 1.05) return null;
    return {
        width: frame.width * scale, height: frame.height * scale,
        left: (width - cropWidth * scale) / 2 - left * scale,
        top: (height - cropHeight * scale) / 2 - top * scale,
        scale,
    };
}

export function initializeImageFraming(media, environment = window) {
    const image = media.querySelector('[data-product-image]');
    if (!image) return;
    let state;
    const clearLayout = () => {
        media.classList.remove('is-image-framed');
        for (const name of ['--image-frame-width', '--image-frame-height', '--image-frame-left', '--image-frame-top']) media.style.removeProperty(name);
    };
    const writeAttribute = (name, value) => {
        if (value) { if (image.getAttribute(name) !== value) image.setAttribute(name, value); }
        else if (image.hasAttribute(name)) image.removeAttribute(name);
    };
    const refresh = () => {
        const source = image.getAttribute('src') || '';
        if (!state || state.source !== source) {
            clearLayout();
            state = {source, srcset: image.getAttribute('srcset') || '', sizes: image.getAttribute('sizes') || '', failedVariants: false};
        }
        const frame = parseImageFrame(media.dataset.imageFraming, source);
        if (!frame || image.hidden || !image.complete || !image.naturalWidth) {
            clearLayout();
            return;
        }
        const padding = parseFloat(environment.getComputedStyle(media).getPropertyValue('--image-frame-padding')) || 12;
        const layout = imageFrameLayout(frame, media.clientWidth, media.clientHeight, padding, environment.devicePixelRatio || 1);
        if (!layout) {
            clearLayout();
            if (!state.failedVariants) writeAttribute('srcset', state.srcset);
            writeAttribute('sizes', state.sizes);
            return;
        }
        const largestVariant = Math.max(0, ...state.srcset.split(',').map(candidate => Number(candidate.trim().match(/\s(\d+)w$/)?.[1]) || 0));
        // A 1200px display derivative cannot supply a larger cropped canvas on a dense screen.
        // Fall back to the untouched full-resolution source rather than magnifying that derivative.
        writeAttribute('srcset', !state.failedVariants && layout.width * (environment.devicePixelRatio || 1) <= largestVariant ? state.srcset : '');
        writeAttribute('sizes', Math.ceil(layout.width) + 'px');
        media.style.setProperty('--image-frame-width', layout.width + 'px');
        media.style.setProperty('--image-frame-height', layout.height + 'px');
        media.style.setProperty('--image-frame-left', layout.left + 'px');
        media.style.setProperty('--image-frame-top', layout.top + 'px');
        media.classList.add('is-image-framed');
    };
    image.addEventListener('load', refresh);
    image.addEventListener('error', () => { if (state) state.failedVariants = true; clearLayout(); });
    media.addEventListener('product-image-source-change', () => { state = undefined; refresh(); });
    if (environment.ResizeObserver) new environment.ResizeObserver(refresh).observe(media);
    else environment.addEventListener('resize', refresh, {passive: true});
    refresh();
}
