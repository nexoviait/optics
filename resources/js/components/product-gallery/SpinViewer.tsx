import { useCallback, useEffect, useRef, useState, type KeyboardEvent, type PointerEvent } from 'react';
import { Loader2, Pause, Play } from 'lucide-react';
import type { ProductImage } from '@/types/product';
import { useTranslate } from '@/lib/i18n';
import { largestSrc, srcSet } from './GalleryImage';

interface SpinViewerProps {
    /** Turntable photos, in order. Used when there are 2+. */
    frames?: string[];
    /** Product photo turned in 3D when there are no turntable photos. */
    image?: ProductImage | null;
    productName: string;
    /** Keep turning on its own (paused while interacting; off for reduced motion). */
    autoRotate?: boolean;
}

/** Load every 4th frame first (usable quickly), then fill in the gaps. */
export function preloadOrder(count: number): number[] {
    const coarse = Array.from({ length: count }, (_, i) => i).filter((i) => i % 4 === 0);
    const fine = Array.from({ length: count }, (_, i) => i).filter((i) => i % 4 !== 0);
    return [...coarse, ...fine];
}

/** Nearest frame to `target` that has finished loading (so rotation never shows a blank). */
export function nearestLoaded(target: number, loaded: ReadonlySet<number>, count: number): number {
    if (loaded.has(target) || loaded.size === 0) return target;
    for (let d = 1; d < count; d++) {
        const a = (target + d) % count;
        const b = (target - d + count) % count;
        if (loaded.has(b)) return b;
        if (loaded.has(a)) return a;
    }
    return target;
}

const mod = (n: number, m: number) => ((n % m) + m) % m;
const clamp = (n: number, limit: number) => Math.max(-limit, Math.min(limit, n));
const rad = (deg: number) => (deg * Math.PI) / 180;
const deg = (r: number) => (r * 180) / Math.PI;

const SECONDS_PER_TURN = 12; // turntable photos: one full turn
const SECONDS_PER_SWAY = 9; // single photo: one swing left and right
const MAX_SWAY = 28; // degrees a single photo turns either way
const RESUME_AFTER_MS = 2500; // auto-rotation resumes this long after the user lets go
const DRAG_FULL_TURN = 0.9; // fraction of the viewer width that rotates one full turn
const FRICTION = 0.92;

interface Drag {
    x: number;
    angle: number;
    lastX: number;
    lastT: number;
    velocity: number; // degrees per animation frame
}

/**
 * 360° viewer, always showing the real product. With turntable photos it plays
 * them as a full turn; with a single photo it turns that photo in 3D, swinging
 * left and right. Auto-rotates, drag to spin (with inertia), arrow keys to
 * step, Space to pause. Interaction pauses auto-rotation, which resumes shortly after.
 *
 * The rotation is written straight to the DOM every animation frame (no React
 * re-render per frame), so it stays smooth on slow devices.
 */
export function SpinViewer({ frames = [], image = null, productName, autoRotate = true }: SpinViewerProps) {
    const t = useTranslate();
    const count = frames.length;
    const hasFrames = count > 1;
    const [reducedMotion] = useState(() => window.matchMedia?.('(prefers-reduced-motion: reduce)').matches ?? false);
    const [playing, setPlaying] = useState(autoRotate && !reducedMotion);
    const [loaded, setLoaded] = useState<ReadonlySet<number>>(() => new Set());
    const stage = useRef<HTMLDivElement>(null);
    const frameImg = useRef<HTMLImageElement>(null);
    const photo = useRef<HTMLDivElement>(null);
    // Photos: degrees around the turntable. Single photo: phase of the swing (yaw = MAX_SWAY · sin(angle)).
    const angle = useRef(0);
    const loadedRef = useRef(loaded);
    loadedRef.current = loaded;
    const drag = useRef<Drag | null>(null);
    const resumeAt = useRef(0);
    const glide = useRef(0);

    const usable = !hasFrames || loaded.size >= Math.ceil(count / 4);
    const ready = !hasFrames || loaded.size >= count;
    const percent = Math.round((loaded.size / Math.max(1, count)) * 100);

    /** Show the current angle: swap the turntable frame, or turn the single photo. */
    const apply = useCallback(() => {
        const el = stage.current;
        if (!el) return;
        if (hasFrames) {
            const index = nearestLoaded(mod(Math.round((angle.current / 360) * count), count), loadedRef.current, count);
            el.dataset.frame = String(index);
            const src = frames[index] ?? '';
            if (frameImg.current && frameImg.current.getAttribute('src') !== src) frameImg.current.src = src;
        } else {
            const yaw = MAX_SWAY * Math.sin(rad(angle.current));
            el.dataset.yaw = String(Math.round(yaw));
            if (photo.current) photo.current.style.transform = `rotateY(${yaw.toFixed(2)}deg)`;
        }
    }, [hasFrames, count, frames]);

    const setAngle = useCallback((next: number) => {
        angle.current = next;
        apply();
    }, [apply]);

    useEffect(apply, [apply, usable, loaded]);

    // Progressive preload of the photo sequence.
    useEffect(() => {
        if (!hasFrames) return;
        let cancelled = false;
        setLoaded(new Set());
        for (const i of preloadOrder(count)) {
            const img = new Image();
            img.decoding = 'async';
            img.onload = img.onerror = () => !cancelled && setLoaded((prev) => new Set(prev).add(i));
            img.src = frames[i] ?? '';
        }
        return () => {
            cancelled = true;
        };
    }, [frames, count, hasFrames]);

    // Continuous auto-rotation (time based, so speed is the same at any frame rate).
    useEffect(() => {
        if (!playing || !ready) return;
        const seconds = hasFrames ? SECONDS_PER_TURN : SECONDS_PER_SWAY;
        let last = 0;
        let id = 0;
        const tick = (now: number) => {
            const dt = last ? Math.min(now - last, 100) / 1000 : 0;
            last = now;
            if (!drag.current && now >= resumeAt.current) setAngle(mod(angle.current + (dt * 360) / seconds, 360));
            id = requestAnimationFrame(tick);
        };
        id = requestAnimationFrame(tick);
        return () => cancelAnimationFrame(id);
    }, [playing, ready, hasFrames, setAngle]);

    const stopGlide = useCallback(() => cancelAnimationFrame(glide.current), []);
    useEffect(() => stopGlide, [stopGlide]);

    const pause = () => {
        resumeAt.current = performance.now() + RESUME_AFTER_MS;
    };

    /** Turn by `delta` degrees: around the turntable, or (single photo) along the swing, clamped at its ends. */
    const turn = (from: number, delta: number) => {
        if (hasFrames) return from + delta;
        const nextYaw = clamp(MAX_SWAY * Math.sin(rad(from)) + delta, MAX_SWAY);
        return deg(Math.asin(nextYaw / MAX_SWAY));
    };

    const degreesPerPixel = () => 360 / ((stage.current?.clientWidth || 400) * DRAG_FULL_TURN);
    // Photos: dragging right turns the frame towards the viewer's right, like grabbing it.
    const dragSign = hasFrames ? -1 : 1;

    const onPointerDown = (e: PointerEvent<HTMLDivElement>) => {
        if (e.pointerType === 'mouse' && e.button !== 0) return;
        stopGlide();
        e.currentTarget.setPointerCapture(e.pointerId);
        drag.current = { x: e.clientX, angle: angle.current, lastX: e.clientX, lastT: performance.now(), velocity: 0 };
    };

    const onPointerMove = (e: PointerEvent<HTMLDivElement>) => {
        const d = drag.current;
        if (!d) return;
        const now = performance.now();
        d.velocity = dragSign * ((e.clientX - d.lastX) / Math.max(1, now - d.lastT)) * 16 * degreesPerPixel();
        d.lastX = e.clientX;
        d.lastT = now;
        setAngle(turn(d.angle, dragSign * (e.clientX - d.x) * degreesPerPixel()));
    };

    const onPointerUp = () => {
        const d = drag.current;
        drag.current = null;
        if (!d) return;
        pause();
        let velocity = d.velocity;
        const step = () => {
            velocity *= FRICTION;
            if (Math.abs(velocity) < 0.05) return;
            setAngle(turn(angle.current, velocity));
            pause();
            glide.current = requestAnimationFrame(step);
        };
        glide.current = requestAnimationFrame(step);
    };

    const step = (dir: number) => {
        stopGlide();
        pause();
        if (hasFrames) {
            const perFrame = 360 / count;
            setAngle((Math.round(angle.current / perFrame) + dir) * perFrame);
        } else {
            setAngle(turn(angle.current, dir * 7));
        }
    };

    const togglePlay = () => {
        resumeAt.current = 0;
        setPlaying((p) => !p);
    };

    const onKeyDown = (e: KeyboardEvent<HTMLDivElement>) => {
        if (e.key === 'ArrowLeft' || e.key === 'ArrowRight') {
            e.preventDefault();
            step(e.key === 'ArrowRight' ? 1 : -1);
        } else if (e.key === ' ') {
            e.preventDefault();
            togglePlay();
        }
    };

    return (
        <div className="relative h-full w-full overflow-hidden">
            <div
                ref={stage}
                role="img"
                aria-roledescription="360 degree viewer"
                aria-label={t('gallery.spin_label', { name: productName })}
                aria-describedby="spin-hint"
                tabIndex={0}
                onKeyDown={onKeyDown}
                onPointerDown={onPointerDown}
                onPointerMove={onPointerMove}
                onPointerUp={onPointerUp}
                onPointerCancel={onPointerUp}
                onDragStart={(e) => e.preventDefault()}
                className="grid h-full w-full cursor-grab touch-pan-y select-none place-items-center bg-white focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-ring active:cursor-grabbing"
            >
                {hasFrames && usable && <img ref={frameImg} src={frames[0]} alt="" draggable={false} className="h-full w-full object-contain" />}

                {!hasFrames && image && (
                    <div className="h-full w-full [perspective:1600px]">
                        <div ref={photo} className="relative h-full w-full [backface-visibility:hidden] [transform-style:preserve-3d] will-change-transform">
                            <picture>
                                {srcSet(image.sources.webp) && <source type="image/webp" srcSet={srcSet(image.sources.webp)} sizes="(min-width: 1024px) 1100px, 100vw" />}
                                <img src={largestSrc(image)} srcSet={srcSet(image.sources.jpg) || undefined} sizes="(min-width: 1024px) 1100px, 100vw"
                                     alt="" draggable={false} decoding="async" className="absolute inset-0 h-full w-full object-contain" />
                            </picture>
                        </div>
                    </div>
                )}

                {!usable && (
                    <span className="flex items-center gap-2 text-sm text-muted-foreground">
                        <Loader2 className="h-4 w-4 animate-spin" aria-hidden="true" />{t('gallery.spin_loading', { percent })}
                    </span>
                )}
                {usable && !ready && (
                    <div className="pointer-events-none absolute inset-x-6 bottom-3 h-0.5 overflow-hidden rounded bg-border" aria-hidden="true">
                        <div className="h-full bg-bronze transition-all" style={{ width: `${percent}%` }} />
                    </div>
                )}
            </div>
            <button
                type="button"
                onClick={togglePlay}
                aria-label={playing ? t('gallery.pause_rotation') : t('gallery.play_rotation')}
                className="absolute bottom-3 right-3 rounded-full border border-border bg-card/90 p-2 text-muted-foreground shadow-sm hover:text-foreground"
            >
                {playing ? <Pause className="h-3.5 w-3.5" /> : <Play className="h-3.5 w-3.5" />}
            </button>
            <p id="spin-hint" className="sr-only">{t('gallery.spin_hint')}</p>
        </div>
    );
}
