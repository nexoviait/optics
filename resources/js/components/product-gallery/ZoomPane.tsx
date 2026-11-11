import { forwardRef, useCallback, useEffect, useImperativeHandle, useRef, useState, type ReactNode, type PointerEvent as ReactPointerEvent } from 'react';

export const ZOOM_SCALE = 2.5;
const MAX_SCALE = 4;

export interface ZoomPaneHandle {
    toggle: () => void;
    reset: () => void;
}

interface ZoomPaneProps {
    children: ReactNode;
    /** Reported whenever zoom turns on/off, so the viewer can disable swiping while zoomed. */
    onZoomChange?: (zoomed: boolean) => void;
    className?: string;
}

interface Transform {
    scale: number;
    x: number;
    y: number;
}

const IDENTITY: Transform = { scale: 1, x: 0, y: 0 };

/**
 * Zoom and pan for one image using pointer events only:
 *  - double-click / double-tap toggles ZOOM_SCALE at the pointer,
 *  - when zoomed, dragging pans (clamped so the image never leaves the frame),
 *  - two-finger pinch zooms between 1× and MAX_SCALE.
 * Children are transformed with CSS (GPU-composited), never re-rendered.
 */
export const ZoomPane = forwardRef<ZoomPaneHandle, ZoomPaneProps>(function ZoomPane({ children, onZoomChange, className = '' }, ref) {
    const frame = useRef<HTMLDivElement>(null);
    const [t, setT] = useState<Transform>(IDENTITY);
    const pointers = useRef(new Map<number, { x: number; y: number }>());
    const gesture = useRef<{ startT: Transform; startX: number; startY: number; startDist?: number } | null>(null);
    const lastTap = useRef(0);

    const clamp = useCallback((next: Transform): Transform => {
        const el = frame.current;
        if (!el || next.scale <= 1) return IDENTITY;
        const maxX = (el.clientWidth * (next.scale - 1)) / 2;
        const maxY = (el.clientHeight * (next.scale - 1)) / 2;
        return { scale: next.scale, x: Math.max(-maxX, Math.min(maxX, next.x)), y: Math.max(-maxY, Math.min(maxY, next.y)) };
    }, []);

    /** Zoom so the point under (clientX, clientY) stays put. */
    const zoomAt = useCallback((scale: number, clientX?: number, clientY?: number) => {
        setT((current) => {
            const el = frame.current;
            if (!el || scale <= 1) return IDENTITY;
            const rect = el.getBoundingClientRect();
            const px = (clientX ?? rect.left + rect.width / 2) - rect.left - rect.width / 2;
            const py = (clientY ?? rect.top + rect.height / 2) - rect.top - rect.height / 2;
            const ratio = scale / current.scale;
            return clamp({ scale, x: px - (px - current.x) * ratio, y: py - (py - current.y) * ratio });
        });
    }, [clamp]);

    useImperativeHandle(ref, () => ({
        toggle: () => zoomAt(t.scale > 1 ? 1 : ZOOM_SCALE),
        reset: () => setT(IDENTITY),
    }), [t.scale, zoomAt]);

    const zoomed = t.scale > 1;
    useEffect(() => onZoomChange?.(zoomed), [zoomed, onZoomChange]);

    const onPointerDown = (e: ReactPointerEvent<HTMLDivElement>) => {
        pointers.current.set(e.pointerId, { x: e.clientX, y: e.clientY });
        e.currentTarget.setPointerCapture(e.pointerId);
        const pts = [...pointers.current.values()];
        if (pts.length === 2 && pts[0] && pts[1]) {
            gesture.current = { startT: t, startX: 0, startY: 0, startDist: Math.hypot(pts[0].x - pts[1].x, pts[0].y - pts[1].y) };
        } else if (pts.length === 1) {
            gesture.current = { startT: t, startX: e.clientX, startY: e.clientY };
        }
    };

    const onPointerMove = (e: ReactPointerEvent<HTMLDivElement>) => {
        if (!pointers.current.has(e.pointerId)) return;
        pointers.current.set(e.pointerId, { x: e.clientX, y: e.clientY });
        const g = gesture.current;
        if (!g) return;
        const pts = [...pointers.current.values()];
        if (g.startDist && pts.length === 2 && pts[0] && pts[1]) {
            const dist = Math.hypot(pts[0].x - pts[1].x, pts[0].y - pts[1].y);
            const scale = Math.max(1, Math.min(MAX_SCALE, g.startT.scale * (dist / g.startDist)));
            zoomAt(scale, (pts[0].x + pts[1].x) / 2, (pts[0].y + pts[1].y) / 2);
        } else if (!g.startDist && g.startT.scale > 1) {
            setT(clamp({ ...g.startT, x: g.startT.x + e.clientX - g.startX, y: g.startT.y + e.clientY - g.startY }));
        }
    };

    const onPointerUp = (e: ReactPointerEvent<HTMLDivElement>) => {
        const g = gesture.current;
        const moved = g && !g.startDist ? Math.hypot(e.clientX - g.startX, e.clientY - g.startY) : Infinity;
        pointers.current.delete(e.pointerId);
        gesture.current = pointers.current.size === 1 ? { startT: t, startX: [...pointers.current.values()][0]!.x, startY: [...pointers.current.values()][0]!.y } : null;

        // Double-tap on touch (dblclick doesn't fire reliably on mobile browsers).
        if (e.pointerType !== 'mouse' && moved < 10) {
            const now = performance.now();
            if (now - lastTap.current < 300) {
                zoomAt(t.scale > 1 ? 1 : ZOOM_SCALE, e.clientX, e.clientY);
                lastTap.current = 0;
            } else {
                lastTap.current = now;
            }
        }
    };

    return (
        <div
            ref={frame}
            className={`relative h-full w-full overflow-hidden ${zoomed ? 'cursor-grab touch-none active:cursor-grabbing' : 'cursor-zoom-in touch-pan-y'} ${className}`}
            onDoubleClick={(e) => zoomAt(t.scale > 1 ? 1 : ZOOM_SCALE, e.clientX, e.clientY)}
            onPointerDown={onPointerDown}
            onPointerMove={onPointerMove}
            onPointerUp={onPointerUp}
            onPointerCancel={onPointerUp}
            data-zoomed={zoomed || undefined}
        >
            <div
                className="h-full w-full will-change-transform"
                style={{ transform: `translate3d(${t.x}px, ${t.y}px, 0) scale(${t.scale})`, transition: gesture.current ? 'none' : 'transform 200ms ease-out' }}
            >
                {children}
            </div>
        </div>
    );
});
