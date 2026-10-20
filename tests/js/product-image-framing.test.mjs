import assert from 'node:assert/strict';
import test from 'node:test';
import {parseImageFrame, imageFrameLayout} from '../../resources/js/product-image-framing.js';

const src = '/media/catalogue/' + 'a'.repeat(24) + '.jpg';
const frame = {src, width: 1200, height: 1200, bounds: [430, 120, 770, 1080]};

test('a reviewed frame cannot be reused for the next gallery source', () => {
    assert.deepEqual(parseImageFrame(JSON.stringify(frame), src), frame);
    assert.equal(parseImageFrame(JSON.stringify(frame), src.replace('aaa', 'bbb')), null);
    for (const invalid of ['{', '{}', JSON.stringify({...frame, bounds: [0, 0, 1300, 1000]}), JSON.stringify({...frame, bounds: [100, 100, 50, 500]})]) {
        assert.equal(parseImageFrame(invalid, src), null);
    }
});

test('portrait, landscape and off-centre content remain fully visible without distortion', () => {
    for (const candidate of [frame, {...frame, width: 2000, height: 1000, bounds: [1200, 300, 1700, 700]}]) {
        for (const [width, height] of [[273, 280], [585, 420], [130, 140]]) {
            const layout = imageFrameLayout(candidate, width, height, 12, 2);
            assert.ok(layout);
            assert.ok(Math.abs(layout.width / layout.height - candidate.width / candidate.height) < 1e-9);
            const [left, top, right, bottom] = candidate.bounds;
            assert.ok(layout.left + left * layout.scale >= 11.999);
            assert.ok(layout.top + top * layout.scale >= 11.999);
            assert.ok(layout.left + right * layout.scale <= width - 11.999);
            assert.ok(layout.top + bottom * layout.scale <= height - 11.999);
            assert.ok(layout.scale * 2 <= 1);
        }
    }
});

test('insufficient pixels or invalid space safely retain the whole photograph', () => {
    const small = {...frame, width: 500, height: 500, bounds: [180, 190, 320, 310]};
    assert.equal(imageFrameLayout(small, 585, 420, 16, 2), null);
    assert.equal(imageFrameLayout(frame, 10, 10, 12, 1), null);
    assert.equal(imageFrameLayout(frame, 300, 400, 12, NaN), null);
});
