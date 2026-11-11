import { act, fireEvent, screen } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { makeImages, renderWithI18n } from '@/test/utils';
import { nearestLoaded, preloadOrder, SpinViewer } from './SpinViewer';

const frames = Array.from({ length: 24 }, (_, i) => `/spin/${String(i + 1).padStart(3, '0')}.webp`);

// jsdom never loads images; fire onload as soon as a src is set.
class InstantImage {
    onload: (() => void) | null = null;
    onerror: (() => void) | null = null;
    decoding = 'auto';
    set src(_value: string) {
        queueMicrotask(() => this.onload?.());
    }
}

describe('SpinViewer helpers', () => {
    it('preloads every 4th frame first, then the rest', () => {
        expect(preloadOrder(10)).toEqual([0, 4, 8, 1, 2, 3, 5, 6, 7, 9]);
    });

    it('falls back to the nearest loaded frame', () => {
        expect(nearestLoaded(5, new Set([0, 4, 8]), 12)).toBe(4);
        expect(nearestLoaded(11, new Set([0, 4, 8]), 12)).toBe(0);
        expect(nearestLoaded(4, new Set([4]), 12)).toBe(4);
    });
});

describe('SpinViewer', () => {
    beforeEach(() => vi.stubGlobal('Image', InstantImage));
    afterEach(() => vi.unstubAllGlobals());

    it('rotates to another frame when dragged horizontally', async () => {
        renderWithI18n(<SpinViewer frames={frames} productName="Havana Keyhole" autoRotate={false} />);
        const stage = screen.getByRole('img', { name: '360° view of Havana Keyhole' });
        await act(async () => {}); // let the preloads resolve

        expect(stage).toHaveAttribute('data-frame', '0');
        Object.defineProperty(stage, 'clientWidth', { value: 400, configurable: true });

        fireEvent.pointerDown(stage, { clientX: 300, pointerId: 1, pointerType: 'mouse', button: 0 });
        fireEvent.pointerMove(stage, { clientX: 200, pointerId: 1 });
        expect(stage.getAttribute('data-frame')).not.toBe('0');
        fireEvent.pointerUp(stage, { clientX: 200, pointerId: 1 });
    });

    it('steps with the arrow keys', async () => {
        renderWithI18n(<SpinViewer frames={frames} productName="Frame" autoRotate={false} />);
        const stage = screen.getByRole('img', { name: '360° view of Frame' });
        await act(async () => {});

        fireEvent.keyDown(stage, { key: 'ArrowRight' });
        expect(stage).toHaveAttribute('data-frame', '1');
        fireEvent.keyDown(stage, { key: 'ArrowLeft' });
        fireEvent.keyDown(stage, { key: 'ArrowLeft' });
        expect(stage).toHaveAttribute('data-frame', '23');

        fireEvent.keyDown(stage, { key: 'ArrowRight' });
        expect(stage).toHaveAttribute('data-frame', '0');
    });

    it('auto-rotates until paused', async () => {
        vi.useFakeTimers({ toFake: ['requestAnimationFrame', 'cancelAnimationFrame', 'performance'] });
        try {
            renderWithI18n(<SpinViewer frames={frames} productName="Frame" />);
            const stage = screen.getByRole('img', { name: '360° view of Frame' });
            await act(async () => {});
            await act(async () => vi.advanceTimersByTime(1000));
            const turned = stage.getAttribute('data-frame');
            expect(turned).not.toBe('0');

            fireEvent.click(screen.getByRole('button', { name: 'Pause rotation' }));
            await act(async () => vi.advanceTimersByTime(1000));
            expect(stage).toHaveAttribute('data-frame', turned);
            expect(screen.getByRole('button', { name: 'Play rotation' })).toBeInTheDocument();
        } finally {
            vi.useRealTimers();
        }
    });

    it('turns a single product photo within its swing when dragged', () => {
        renderWithI18n(<SpinViewer image={makeImages(1)[0]} productName="Frame" autoRotate={false} />);
        const stage = screen.getByRole('img', { name: '360° view of Frame' });
        expect(stage).toHaveAttribute('data-yaw', '0');
        expect(stage.querySelector('img')).toBeInTheDocument();
        Object.defineProperty(stage, 'clientWidth', { value: 400, configurable: true });

        fireEvent.pointerDown(stage, { clientX: 100, pointerId: 1, pointerType: 'mouse', button: 0 });
        fireEvent.pointerMove(stage, { clientX: 120, pointerId: 1 });
        expect(Number(stage.getAttribute('data-yaw'))).toBeGreaterThan(0);
        fireEvent.pointerMove(stage, { clientX: 900, pointerId: 1 });
        expect(stage).toHaveAttribute('data-yaw', '28'); // never turns past the edge of the photo
        fireEvent.pointerUp(stage, { clientX: 900, pointerId: 1 });
    });
});
