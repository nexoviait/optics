import '@testing-library/jest-dom/vitest';
import { cleanup } from '@testing-library/react';
import { afterEach } from 'vitest';

afterEach(() => {
    cleanup();
    window.location.hash = '';
    document.body.style.overflow = '';
});

// jsdom doesn't implement these browser APIs the gallery relies on.
class ResizeObserverStub {
    observe(): void {}
    unobserve(): void {}
    disconnect(): void {}
}
globalThis.ResizeObserver ??= ResizeObserverStub as unknown as typeof ResizeObserver;

class IntersectionObserverStub {
    readonly root = null;
    readonly rootMargin = '';
    readonly thresholds = [];
    observe(): void {}
    unobserve(): void {}
    disconnect(): void {}
    takeRecords(): IntersectionObserverEntry[] {
        return [];
    }
}
globalThis.IntersectionObserver ??= IntersectionObserverStub as unknown as typeof IntersectionObserver;

Element.prototype.scrollIntoView ??= function scrollIntoView(): void {};
Element.prototype.scrollTo ??= function scrollTo(): void {};
Element.prototype.setPointerCapture ??= function setPointerCapture(): void {};
Element.prototype.releasePointerCapture ??= function releasePointerCapture(): void {};
