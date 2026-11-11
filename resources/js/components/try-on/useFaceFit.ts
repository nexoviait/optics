import { useCallback, useEffect, useRef, useState, type CSSProperties, type KeyboardEvent, type PointerEvent, type RefObject } from 'react';
import type { FaceFit } from '@/face-fit';
import { GLASSES } from './FrameOverlay';

export type FitStatus = 'loading' | 'fitted' | 'manual';

interface Box {
    x: number;
    y: number;
    width: number;
    angle: number;
}

// Roughly where eyes sit in a centred portrait; used until (or unless) a face is found.
const FALLBACK: Box = { x: 0.5, y: 0.41, width: 0.56, angle: 0 };
const TIMEOUT_MS = 20000;

/** Map a detected face from image pixels into an object-cover stage (fractions of the stage). */
export function fitToStage(face: FaceFit, image: { naturalWidth: number; naturalHeight: number }, stage: { width: number; height: number }): Box {
    const scale = Math.max(stage.width / image.naturalWidth, stage.height / image.naturalHeight);
    const ox = (stage.width - image.naturalWidth * scale) / 2;
    const oy = (stage.height - image.naturalHeight * scale) / 2;
    const l = { x: face.left.x * scale + ox, y: face.left.y * scale + oy };
    const r = { x: face.right.x * scale + ox, y: face.right.y * scale + oy };
    const eyeDist = Math.hypot(r.x - l.x, r.y - l.y);
    // Lenses span ~97% of the face; bounded by the pupil distance so a turned head can't produce a silly size.
    const ideal = (face.faceWidth * scale * 0.97 * GLASSES.width) / GLASSES.lensSpan;
    const width = Math.min(Math.max(ideal, eyeDist * 2.1), eyeDist * 3);
    return { x: (l.x + r.x) / 2 / stage.width, y: (l.y + r.y) / 2 / stage.height, width: width / stage.width, angle: Math.atan2(r.y - l.y, r.x - l.x) };
}

/**
 * Places the glasses on the pupils of the photo in `photo` inside `stage`
 * (an object-cover box), and lets the shopper drag / resize to fine-tune.
 */
export function useFaceFit(stage: RefObject<HTMLDivElement | null>, photo: RefObject<HTMLImageElement | null>, mediapipeBase: string) {
    const [status, setStatus] = useState<FitStatus>('loading');
    const [box, setBox] = useState<Box>(FALLBACK);
    const [nudge, setNudge] = useState({ x: 0, y: 0, scale: 1 });
    const face = useRef<FaceFit | null>(null);
    const drag = useRef<{ x: number; y: number; start: typeof nudge; w: number; h: number } | null>(null);

    const layout = useCallback(() => {
        const s = stage.current;
        const img = photo.current;
        if (face.current && s && img?.naturalWidth) setBox(fitToStage(face.current, img, { width: s.clientWidth, height: s.clientHeight }));
    }, [stage, photo]);

    const detect = useCallback(async () => {
        const img = photo.current;
        if (!img?.naturalWidth) return;
        setStatus('loading');
        setNudge({ x: 0, y: 0, scale: 1 });
        try {
            const { detectFace } = await import('@/face-fit');
            const timeout = new Promise<never>((_, reject) => setTimeout(() => reject(new Error('Face fitting timed out')), TIMEOUT_MS));
            face.current = await Promise.race([detectFace(img, mediapipeBase), timeout]);
        } catch (e) {
            console.warn('Face fitting unavailable:', e);
            face.current = null;
        }
        setStatus(face.current ? 'fitted' : 'manual');
        if (face.current) layout();
        else setBox(FALLBACK);
    }, [photo, mediapipeBase, layout]);

    useEffect(() => {
        const s = stage.current;
        if (!s) return;
        const observer = new ResizeObserver(layout);
        observer.observe(s);
        return () => observer.disconnect();
    }, [stage, layout]);

    const style: CSSProperties = {
        left: `${(box.x + nudge.x) * 100}%`,
        top: `${(box.y + nudge.y) * 100}%`,
        width: `${box.width * nudge.scale * 100}%`,
        aspectRatio: `${GLASSES.width} / ${GLASSES.height}`,
        // Shift so the SVG's pupil line, not its centre, sits on the eyes.
        transform: `translate(-50%, -${(GLASSES.pupilY / GLASSES.height) * 100}%) rotate(${box.angle}rad)`,
        transformOrigin: `50% ${GLASSES.pupilY}%`,
    };

    const move = (dx: number, dy: number) => setNudge((v) => ({ ...v, x: v.x + dx, y: v.y + dy }));
    const resize = (by: number) => setNudge((v) => ({ ...v, scale: Math.min(1.6, Math.max(0.6, v.scale + by)) }));
    const reset = () => setNudge({ x: 0, y: 0, scale: 1 });

    const handlers = {
        onPointerDown(e: PointerEvent<HTMLElement>) {
            if (e.button) return;
            e.currentTarget.setPointerCapture(e.pointerId);
            const s = stage.current;
            drag.current = { x: e.clientX, y: e.clientY, start: nudge, w: s?.clientWidth || 1, h: s?.clientHeight || 1 };
        },
        onPointerMove(e: PointerEvent<HTMLElement>) {
            const d = drag.current;
            if (d) setNudge({ ...d.start, x: d.start.x + (e.clientX - d.x) / d.w, y: d.start.y + (e.clientY - d.y) / d.h });
        },
        onPointerUp() {
            drag.current = null;
        },
        onKeyDown(e: KeyboardEvent<HTMLElement>) {
            const step = 0.005;
            const actions: Record<string, () => void> = {
                ArrowLeft: () => move(-step, 0), ArrowRight: () => move(step, 0), ArrowUp: () => move(0, -step), ArrowDown: () => move(0, step),
                '+': () => resize(0.03), '=': () => resize(0.03), '-': () => resize(-0.03),
            };
            const action = actions[e.key];
            if (!action) return;
            e.preventDefault();
            action();
        },
    };

    return { status, style, detect, resize, reset, handlers };
}
