import { useCallback, useEffect, useRef, useState, type KeyboardEvent, type PointerEvent, type ReactNode } from 'react';
import * as THREE from 'three';
import { Loader2, Minus, Plus, RotateCcw } from 'lucide-react';
import type { FaceFit } from '@/face-fit';
import type { LiveTryOnSettings } from './live/LiveTryOn';
import { buildTryOnGlasses, disposeObject, loadModelGlasses, PUPIL_ABOVE_CENTRE_MM } from './live/glasses3d';
import { createGlassesStage, fallbackPlacement, placeOnPhoto, type GlassesStage } from './stage3d';

type Status = 'loading' | 'fitted' | 'manual';

interface PhotoTryOn3DProps {
    portrait: string;
    glasses: LiveTryOnSettings;
    /** Finish colour for the drawn frame. */
    color: string;
    /** Frame width of the selected size, in mm. */
    frameWidthMm: number;
    mediapipeBase: string;
    /** Shown instead when the browser can't do WebGL. */
    fallback: ReactNode;
}

const TIMEOUT_MS = 20000;
const STATUS_TEXT = { fitted: 'Fitted to the eyes · drag or pinch to adjust', manual: 'Drag the glasses onto the eyes' } as const;
const clampScale = (s: number) => Math.min(1.6, Math.max(0.6, s));

/**
 * Model-photo try-on: the product's 3D frame (the same model as the 360° view)
 * on a portrait, placed between the pupils, true to size for the selected frame
 * size (the iris is the ruler) and turned with the head. Drag or pinch to adjust;
 * other changes glide into place. Runs entirely in the browser.
 */
export function PhotoTryOn3D({ portrait, glasses, color, frameWidthMm, mediapipeBase, fallback }: PhotoTryOn3DProps) {
    const stage = useRef<HTMLDivElement>(null);
    const photo = useRef<HTMLImageElement>(null);
    const canvas = useRef<HTMLCanvasElement>(null);
    const handle = useRef<HTMLDivElement>(null);
    const three = useRef<GlassesStage | null>(null);
    const fit = useRef<FaceFit | null>(null);
    const nudge = useRef({ x: 0, y: 0, scale: 1 });
    const shown = useRef<{ x: number; y: number; width: number } | null>(null);
    const pointers = useRef(new Map<number, { x: number; y: number }>());
    const gesture = useRef<{ start: { x: number; y: number; scale: number }; cx: number; cy: number; dist: number } | null>(null);
    const raf = useRef(0);
    const [failed, setFailed] = useState(false);
    const [status, setStatus] = useState<Status>('loading');
    const [built, setBuilt] = useState(false);

    const draw = useRef(() => {});
    const requestDraw = useCallback(() => {
        if (raf.current) return;
        raf.current = requestAnimationFrame(() => {
            raf.current = 0;
            draw.current();
        });
    }, []);

    draw.current = () => {
        const t = three.current;
        const s = stage.current;
        const img = photo.current;
        if (!t || !s || !img?.naturalWidth || !s.clientWidth) return;
        const box = { width: s.clientWidth, height: s.clientHeight };
        const p = fit.current ? placeOnPhoto(fit.current, img, box, frameWidthMm) : fallbackPlacement(box, frameWidthMm);
        const n = nudge.current;
        const target = { x: p.x + n.x * box.width, y: p.y - n.y * box.height, width: p.width * glasses.scale * n.scale };

        // Follow the finger exactly; glide for everything else.
        const prev = shown.current;
        const follow = !prev || gesture.current !== null;
        const now = follow ? target : {
            x: prev.x + (target.x - prev.x) * 0.3,
            y: prev.y + (target.y - prev.y) * 0.3,
            width: prev.width + (target.width - prev.width) * 0.3,
        };
        shown.current = now;
        if (!follow && Math.max(Math.abs(target.x - now.x), Math.abs(target.y - now.y), Math.abs(target.width - now.width)) > 0.25) requestDraw();

        t.resize(box.width, box.height);
        t.setScale(p.pxPerMm);
        t.anchor.position.set(now.x, now.y, 0);
        t.anchor.quaternion.copy(p.rotation);
        t.anchor.scale.setScalar(now.width);
        t.holder.position.y = (glasses.offsetY - PUPIL_ABOVE_CENTRE_MM) / frameWidthMm;
        t.anchor.visible = true;
        t.render();

        // The drag handle sits over the glasses.
        const roll = new THREE.Euler().setFromQuaternion(p.rotation, 'YXZ').z;
        Object.assign(handle.current?.style ?? {}, {
            left: `${box.width / 2 + now.x}px`,
            top: `${box.height / 2 - now.y}px`,
            width: `${now.width * 1.05}px`,
            height: `${now.width * 0.42}px`,
            transform: `translate(-50%, -50%) rotate(${-roll}rad)`,
        });
    };

    useEffect(() => {
        const c = canvas.current;
        const s = stage.current;
        if (!c || !s) return;
        try {
            three.current = createGlassesStage(c);
        } catch (e) {
            console.warn('3D try-on unavailable, showing the drawn frame:', e);
            setFailed(true);
            return;
        }
        const observer = new ResizeObserver(requestDraw);
        observer.observe(s);
        return () => {
            observer.disconnect();
            cancelAnimationFrame(raf.current);
            raf.current = 0;
            three.current?.dispose();
            three.current = null;
        };
    }, [requestDraw]);

    // The glasses: the frame built from the product's shape at once, its .glb when that has loaded.
    const frameKey = JSON.stringify(glasses.frame);
    useEffect(() => {
        const t = three.current;
        if (!t) return;
        let cancelled = false;
        const show = (object: THREE.Object3D) => {
            t.holder.children.slice().forEach((child) => {
                t.holder.remove(child);
                disposeObject(child);
            });
            t.holder.add(object);
            setBuilt(true);
            requestDraw();
        };
        show(buildTryOnGlasses(JSON.parse(frameKey), color));
        if (glasses.model) {
            loadModelGlasses(glasses.model, glasses.flip)
                .then((model) => (cancelled ? disposeObject(model) : show(model)))
                .catch((e) => console.warn('3D model unavailable for try-on, using the drawn frame:', e));
        }
        return () => {
            cancelled = true;
        };
    }, [frameKey, color, glasses.model, glasses.flip, requestDraw]);

    useEffect(requestDraw, [frameWidthMm, glasses.scale, glasses.offsetY, requestDraw]);

    const detect = async () => {
        const img = photo.current;
        if (!img?.naturalWidth) return;
        setStatus('loading');
        try {
            const { detectFace } = await import('@/face-fit');
            const timeout = new Promise<never>((_, reject) => setTimeout(() => reject(new Error('Face fitting timed out')), TIMEOUT_MS));
            fit.current = await Promise.race([detectFace(img, mediapipeBase), timeout]);
        } catch (e) {
            console.warn('Face fitting unavailable:', e);
            fit.current = null;
        }
        nudge.current = { x: 0, y: 0, scale: 1 };
        shown.current = null;
        setStatus(fit.current ? 'fitted' : 'manual');
        requestDraw();
    };

    const change = (next: Partial<typeof nudge.current>) => {
        nudge.current = { ...nudge.current, ...next };
        requestDraw();
    };
    const resize = (by: number) => change({ scale: clampScale(nudge.current.scale + by) });
    const reset = () => change({ x: 0, y: 0, scale: 1 });

    const startGesture = () => {
        const pts = [...pointers.current.values()];
        if (!pts.length) {
            gesture.current = null;
            return;
        }
        gesture.current = {
            start: { ...nudge.current },
            cx: pts.reduce((sum, p) => sum + p.x, 0) / pts.length,
            cy: pts.reduce((sum, p) => sum + p.y, 0) / pts.length,
            dist: pts.length > 1 ? Math.hypot(pts[0]!.x - pts[1]!.x, pts[0]!.y - pts[1]!.y) : 0,
        };
    };
    const endPointer = (e: PointerEvent<HTMLElement>) => {
        pointers.current.delete(e.pointerId);
        startGesture();
    };

    const handlers = {
        onPointerDown(e: PointerEvent<HTMLElement>) {
            if (e.button) return;
            e.currentTarget.setPointerCapture(e.pointerId);
            pointers.current.set(e.pointerId, { x: e.clientX, y: e.clientY });
            startGesture();
        },
        onPointerMove(e: PointerEvent<HTMLElement>) {
            const g = gesture.current;
            const s = stage.current;
            if (!g || !s || !pointers.current.has(e.pointerId)) return;
            pointers.current.set(e.pointerId, { x: e.clientX, y: e.clientY });
            const pts = [...pointers.current.values()];
            const cx = pts.reduce((sum, p) => sum + p.x, 0) / pts.length;
            const cy = pts.reduce((sum, p) => sum + p.y, 0) / pts.length;
            const pinch = pts.length > 1 && g.dist ? Math.hypot(pts[0]!.x - pts[1]!.x, pts[0]!.y - pts[1]!.y) / g.dist : 1;
            nudge.current = { x: g.start.x + (cx - g.cx) / s.clientWidth, y: g.start.y + (cy - g.cy) / s.clientHeight, scale: clampScale(g.start.scale * pinch) };
            requestDraw();
        },
        onPointerUp: endPointer,
        onPointerCancel: endPointer,
        onKeyDown(e: KeyboardEvent<HTMLElement>) {
            const step = 0.005;
            const { x, y } = nudge.current;
            const actions: Record<string, () => void> = {
                ArrowLeft: () => change({ x: x - step }), ArrowRight: () => change({ x: x + step }),
                ArrowUp: () => change({ y: y - step }), ArrowDown: () => change({ y: y + step }),
                '+': () => resize(0.03), '=': () => resize(0.03), '-': () => resize(-0.03), '0': reset,
            };
            const action = actions[e.key];
            if (!action) return;
            e.preventDefault();
            action();
        },
    };

    if (failed) return <>{fallback}</>;
    const ready = status !== 'loading' && built;

    return (
        <div ref={stage} className="relative aspect-[4/5] select-none overflow-hidden rounded-2xl bg-obsidian">
            <img ref={photo} src={portrait} onLoad={() => void detect()} alt="Virtual try-on portrait" draggable={false} className="h-full w-full object-cover" />
            <canvas
                ref={canvas}
                aria-hidden="true"
                className={`pointer-events-none absolute inset-0 h-full w-full drop-shadow-[0_3px_3px_rgba(0,0,0,0.3)] transition-[opacity,translate] duration-500 ease-out ${ready ? 'translate-y-0 opacity-100' : '-translate-y-1.5 opacity-0'}`}
            />
            <div
                ref={handle}
                role="application"
                tabIndex={0}
                aria-label="Glasses position. Drag or pinch, or use the arrow keys to move and plus or minus to resize."
                {...handlers}
                className={`absolute cursor-grab touch-none rounded-full focus:outline-none focus-visible:ring-2 focus-visible:ring-bronze active:cursor-grabbing ${ready ? '' : 'pointer-events-none'}`}
            />

            {!ready && (
                <div className="pointer-events-none absolute inset-0 grid place-items-center">
                    <span className="flex items-center gap-2 rounded-full bg-obsidian/75 px-3 py-1.5 text-xs text-on-dark backdrop-blur"><Loader2 className="h-3.5 w-3.5 animate-spin" />Fitting the frame…</span>
                </div>
            )}
            {ready && (
                <>
                    <div className="absolute right-3 top-3 flex items-center gap-1 rounded-full bg-obsidian/75 p-1 text-on-dark backdrop-blur">
                        <button type="button" onClick={() => resize(-0.05)} className="rounded-full p-1.5 hover:bg-on-dark/15" aria-label="Make glasses smaller"><Minus className="h-3.5 w-3.5" /></button>
                        <button type="button" onClick={() => resize(0.05)} className="rounded-full p-1.5 hover:bg-on-dark/15" aria-label="Make glasses larger"><Plus className="h-3.5 w-3.5" /></button>
                        <button type="button" onClick={reset} className="rounded-full p-1.5 hover:bg-on-dark/15" aria-label="Reset glasses position"><RotateCcw className="h-3.5 w-3.5" /></button>
                    </div>
                    <p aria-live="polite" className="pointer-events-none absolute left-1/2 top-14 -translate-x-1/2 whitespace-nowrap rounded-full bg-obsidian/60 px-2.5 py-1 text-[0.6rem] text-on-dark backdrop-blur">{STATUS_TEXT[status === 'manual' ? 'manual' : 'fitted']}</p>
                </>
            )}
        </div>
    );
}
