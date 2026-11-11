import { useRef, type PointerEvent } from 'react';

interface SwipeOptions {
    onLeft: () => void;
    onRight: () => void;
    /** Minimum horizontal travel in px. */
    threshold?: number;
    disabled?: boolean;
}

/**
 * Horizontal swipe detection with pointer events (touch, pen and mouse).
 * Mostly-vertical gestures are ignored so the page can still scroll.
 */
export function useSwipe({ onLeft, onRight, threshold = 50, disabled = false }: SwipeOptions) {
    const start = useRef<{ x: number; y: number; id: number } | null>(null);

    return {
        onPointerDown(event: PointerEvent) {
            if (disabled || (event.pointerType === 'mouse' && event.button !== 0)) return;
            start.current = { x: event.clientX, y: event.clientY, id: event.pointerId };
        },
        onPointerUp(event: PointerEvent) {
            const s = start.current;
            start.current = null;
            if (disabled || !s || s.id !== event.pointerId) return;
            const dx = event.clientX - s.x;
            const dy = event.clientY - s.y;
            if (Math.abs(dx) < threshold || Math.abs(dx) < Math.abs(dy) * 1.5) return;
            if (dx < 0) onLeft();
            else onRight();
        },
        onPointerCancel() {
            start.current = null;
        },
    };
}
