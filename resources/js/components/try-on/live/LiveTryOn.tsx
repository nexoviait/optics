import { useCallback, useEffect, useRef, useState } from 'react';
import * as THREE from 'three';
import { Camera, Download, Loader2, Plus, RotateCcw, Ruler, VideoOff } from 'lucide-react';
import type { FrameModelData } from '@/lib/frameModel';
import { CompareLooks, saveLook, type Look } from './CompareLooks';
import { loadFaceTracker } from './faceTracker';
import { createGlassesStage } from '../stage3d';
import { buildTryOnGlasses, disposeObject, loadModelGlasses, PUPIL_ABOVE_CENTRE_MM } from './glasses3d';
import { faceWidthMm, median, pixelsPerMm, PUPIL_A, PUPIL_B, recommendSize, type SizeOption } from './measure';
import { OneEuroFilter, QuaternionFilter } from './oneEuro';

export interface LiveTryOnSettings {
    /** Product .glb, or null to draw the frame from its shape. */
    model: string | null;
    frame: FrameModelData;
    /** The model faces backwards: turn it 180°. */
    flip: boolean;
    /** × the frame's real width. */
    scale: number;
    /** mm, + moves the frame up. */
    offsetY: number;
}

interface LiveTryOnProps {
    settings: LiveTryOnSettings;
    mediapipeBase: string;
    product: { id: number; name: string; url: string };
    /** Finish colour for the drawn frame. */
    color: string;
    /** Total frame width in mm for the selected size (true-to-size on the face). */
    frameWidthMm: number;
    sizes: SizeOption[];
    onUseSize?: (code: string) => void;
}

type Status = 'idle' | 'starting' | 'running' | 'error';

const SIZE_SAMPLES = 30;
const MAX_YAW_FOR_MEASURING = 0.17; // ~10°: only measure the face width while looking at the camera

/**
 * Live camera try-on: the frame in 3D on the shopper's face, following head
 * turns, nods and tilts. True to size (the iris is used as a ruler), with the
 * far temple hidden behind the head, steady thanks to One Euro smoothing.
 * Also measures face width to suggest a frame size, and saves snapshots for
 * Compare Looks. The video never leaves the device.
 */
export function LiveTryOn({ settings, mediapipeBase, product, color, frameWidthMm, sizes, onUseSize }: LiveTryOnProps) {
    const video = useRef<HTMLVideoElement>(null);
    const canvas = useRef<HTMLCanvasElement>(null);
    const [status, setStatus] = useState<Status>('idle');
    const [message, setMessage] = useState('');
    const [faceFound, setFaceFound] = useState(false);
    const [sizeAdjust, setSizeAdjust] = useState(1);
    const [heightAdjust, setHeightAdjust] = useState(0);
    const [measured, setMeasured] = useState<{ faceMm: number; size: SizeOption | null } | null>(null);
    const [lookCount, setLookCount] = useState(0);

    // Values the render loop reads every frame without restarting.
    const live = useRef({ sizeAdjust, heightAdjust, frameWidthMm, settings, color });
    live.current = { sizeAdjust, heightAdjust, frameWidthMm, settings, color };
    const samples = useRef<number[]>([]);
    const setFrameColor = useRef<((c: string) => void) | null>(null);
    const stopRef = useRef<() => void>(() => {});

    useEffect(() => setFrameColor.current?.(color), [color]);

    const stop = useCallback(() => {
        stopRef.current();
        stopRef.current = () => {};
        setStatus('idle');
        setFaceFound(false);
    }, []);
    useEffect(() => stop, [stop]);

    const start = async () => {
        if (!navigator.mediaDevices?.getUserMedia) {
            setStatus('error');
            setMessage(window.isSecureContext ? 'This browser cannot use the camera. Try the model photo instead.' : 'The camera needs a secure (HTTPS) connection.');
            return;
        }
        setStatus('starting');
        setMessage('Starting the camera…');
        let stream: MediaStream | null = null;
        let raf = 0;
        let disposed = false;
        const cleanups: (() => void)[] = [];
        stopRef.current = () => {
            disposed = true;
            cancelAnimationFrame(raf);
            stream?.getTracks().forEach((t) => t.stop());
            if (video.current) video.current.srcObject = null;
            cleanups.forEach((c) => c());
        };

        try {
            stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user', width: { ideal: 1280 }, height: { ideal: 720 } }, audio: false });
            const v = video.current!;
            v.srcObject = stream;
            await v.play();
            setMessage('Loading face tracking… (first time only)');
            const tracker = await loadFaceTracker(mediapipeBase);
            if (disposed) return;

            const W = v.videoWidth;
            const H = v.videoHeight;
            // Video pixels, origin at the centre, y up; the canvas is mirrored together with the video.
            const stage = createGlassesStage(canvas.current!, { preserveDrawingBuffer: true });
            stage.resize(W, H);
            cleanups.push(() => stage.dispose());
            const { anchor, holder } = stage;

            const { settings: s } = live.current;
            const show = (object: THREE.Object3D) => {
                holder.children.slice().forEach((child) => {
                    holder.remove(child);
                    disposeObject(child);
                });
                holder.add(object);
            };
            // The same solid frame as the 360° view, in the chosen finish.
            show(buildTryOnGlasses(s.frame, live.current.color));
            setFrameColor.current = (c) => show(buildTryOnGlasses(s.frame, c));
            if (s.model) {
                // Swap in the real 3D model once it has loaded; keep the drawn frame if it fails.
                loadModelGlasses(s.model, s.flip).then((model) => {
                    if (disposed) return disposeObject(model);
                    show(model);
                    setFrameColor.current = null;
                }).catch((e) => console.warn('3D model unavailable for try-on, using the drawn frame:', e));
            }

            const fx = new OneEuroFilter();
            const fy = new OneEuroFilter();
            const fs = new OneEuroFilter(0.8, 0.005);
            const fq = new QuaternionFilter();
            const matrix = new THREE.Matrix4();
            const pos = new THREE.Vector3();
            const quat = new THREE.Quaternion();
            const scl = new THREE.Vector3();
            const euler = new THREE.Euler();
            let lastVideoTime = -1;
            let seen = false;

            const loop = () => {
                if (disposed) return;
                raf = requestAnimationFrame(loop);
                if (v.readyState < 2 || v.currentTime === lastVideoTime) return;
                lastVideoTime = v.currentTime;
                const now = performance.now();
                const result = tracker.detectForVideo(v, now);
                const lm = result.faceLandmarks?.[0];
                const pose = result.facialTransformationMatrixes?.[0];
                const ppm = lm ? pixelsPerMm(lm, W, H) : null;
                if (!lm || !pose || !ppm || !lm[PUPIL_A] || !lm[PUPIL_B]) {
                    if (seen) {
                        seen = false;
                        anchor.visible = false;
                        [fx, fy, fs, fq].forEach((f) => f.reset());
                        setFaceFound(false);
                    }
                    stage.render();
                    return;
                }
                const t = now / 1000;
                const a = lm[PUPIL_A]!;
                const b = lm[PUPIL_B]!;
                const { sizeAdjust: adj, heightAdjust: lift, frameWidthMm: widthMm, settings: cfg } = live.current;

                matrix.fromArray(pose.data);
                matrix.decompose(pos, quat, scl);
                const q = fq.filter([quat.x, quat.y, quat.z, quat.w], t);
                anchor.quaternion.set(q[0], q[1], q[2], q[3]);
                anchor.position.set(fx.filter(((a.x + b.x) / 2 - 0.5) * W, t), fy.filter(-((a.y + b.y) / 2 - 0.5) * H, t), 0);
                const width = fs.filter(widthMm * ppm * cfg.scale * adj, t);
                anchor.scale.setScalar(width);
                stage.setScale(width / (widthMm * cfg.scale * adj));
                holder.position.y = (cfg.offsetY - PUPIL_ABOVE_CENTRE_MM + lift) / widthMm; // mm → frame-width units
                anchor.visible = true;
                if (!seen) {
                    seen = true;
                    setFaceFound(true);
                }

                // Face width for the size suggestion, only while looking at the camera.
                euler.setFromQuaternion(quat, 'YXZ');
                if (samples.current.length < SIZE_SAMPLES && Math.abs(euler.y) < MAX_YAW_FOR_MEASURING) {
                    const width = faceWidthMm(lm, W, H);
                    if (width) samples.current.push(width);
                    if (samples.current.length === SIZE_SAMPLES) {
                        const faceMm = median(samples.current);
                        setMeasured({ faceMm, size: recommendSize(faceMm, sizes) });
                    }
                }
                stage.render();
            };
            setStatus('running');
            setMessage('');
            loop();
        } catch (e) {
            stopRef.current();
            setStatus('error');
            const name = (e as { name?: string })?.name;
            setMessage(name === 'NotAllowedError'
                ? 'Camera permission was blocked. Allow it in your browser settings, or use the model photo.'
                : name === 'NotFoundError' ? 'No camera was found on this device.' : 'The live try-on could not start on this device. Try the model photo instead.');
            console.warn('Live try-on failed:', e);
        }
    };

    /** The mirrored camera frame with the glasses, as the shopper sees it. */
    const capture = (maxWidth?: number): string | null => {
        const v = video.current;
        const c = canvas.current;
        if (!v?.videoWidth || !c) return null;
        const scale = maxWidth ? Math.min(1, maxWidth / v.videoWidth) : 1;
        const out = document.createElement('canvas');
        out.width = Math.round(v.videoWidth * scale);
        out.height = Math.round(v.videoHeight * scale);
        const ctx = out.getContext('2d')!;
        ctx.translate(out.width, 0);
        ctx.scale(-1, 1);
        ctx.drawImage(v, 0, 0, out.width, out.height);
        ctx.drawImage(c, 0, 0, out.width, out.height);
        return out.toDataURL('image/jpeg', 0.88);
    };

    const download = () => {
        const image = capture();
        if (!image) return;
        const link = document.createElement('a');
        link.download = `${product.name.replace(/[^\w-]+/g, '-').toLowerCase()}-try-on.jpg`;
        link.href = image;
        link.click();
    };

    const addLook = () => {
        const image = capture(480);
        if (!image) return;
        const look: Look = { id: `${product.id}-${Date.now()}`, productId: product.id, name: product.name, url: product.url, color, image };
        saveLook(look);
        setLookCount((n) => n + 1);
    };

    const remeasure = () => {
        samples.current = [];
        setMeasured(null);
    };

    const running = status === 'running';

    return (
        <div className="flex flex-col gap-3">
            <div className="relative overflow-hidden rounded-2xl bg-obsidian">
                {/* Video and 3D overlay mirrored together, like a mirror. */}
                <div className="relative -scale-x-100">
                    <video ref={video} playsInline muted className={`block w-full ${running ? '' : 'aspect-[4/3] object-cover opacity-0'}`} aria-label="Your camera" />
                    <canvas ref={canvas} aria-hidden="true" className="pointer-events-none absolute inset-0 h-full w-full drop-shadow-[0_3px_3px_rgba(0,0,0,0.3)]" />
                </div>

                {status !== 'running' && (
                    <div className="absolute inset-0 grid place-items-center p-6 text-center text-on-dark">
                        {status === 'starting' ? (
                            <span className="flex items-center gap-2 text-sm"><Loader2 className="h-4 w-4 animate-spin" />{message}</span>
                        ) : (
                            <div className="max-w-xs space-y-3">
                                {status === 'error' && <p role="alert" className="text-sm text-on-dark/90">{message}</p>}
                                <button type="button" onClick={() => void start()} className="btn mx-auto bg-on-dark text-obsidian hover:bg-on-dark/90"><Camera className="h-4 w-4" />{status === 'error' ? 'Try again' : 'Start camera'}</button>
                                <p className="text-[0.7rem] text-on-dark/70">See the frame on your face in 3D. Your video stays on this device and is never uploaded.</p>
                            </div>
                        )}
                    </div>
                )}

                {running && (
                    <>
                        <p aria-live="polite" className="pointer-events-none absolute left-1/2 top-3 -translate-x-1/2 whitespace-nowrap rounded-full bg-obsidian/70 px-3 py-1 text-[0.65rem] text-on-dark">
                            {faceFound ? 'Turn your head to see every angle' : 'Look at the camera'}
                        </p>
                        <button type="button" onClick={stop} aria-label="Stop camera" className="absolute right-3 top-3 rounded-full bg-obsidian/70 p-2 text-on-dark hover:bg-obsidian"><VideoOff className="h-4 w-4" /></button>
                    </>
                )}
            </div>

            {running && (
                <>
                    <div className="grid gap-3 text-xs sm:grid-cols-2">
                        <label className="flex items-center gap-2"><span className="w-12 text-muted-foreground">Size</span>
                            <input type="range" min={0.85} max={1.15} step={0.01} value={sizeAdjust} onChange={(e) => setSizeAdjust(+e.target.value)} className="flex-1" aria-label="Glasses size" />
                        </label>
                        <label className="flex items-center gap-2"><span className="w-12 text-muted-foreground">Height</span>
                            <input type="range" min={-10} max={10} step={0.5} value={heightAdjust} onChange={(e) => setHeightAdjust(+e.target.value)} className="flex-1" aria-label="Glasses height" />
                        </label>
                    </div>
                    <div className="flex flex-wrap items-center gap-2 text-xs">
                        <button type="button" onClick={addLook} disabled={!faceFound} className="btn-ghost px-3 py-1.5 disabled:opacity-50"><Plus className="h-3.5 w-3.5" />Add to compare</button>
                        <button type="button" onClick={download} disabled={!faceFound} className="btn-ghost px-3 py-1.5 disabled:opacity-50"><Download className="h-3.5 w-3.5" />Save photo</button>
                        <button type="button" onClick={() => { setSizeAdjust(1); setHeightAdjust(0); }} className="btn-ghost px-3 py-1.5"><RotateCcw className="h-3.5 w-3.5" />Reset fit</button>
                    </div>

                    <div className="rounded-xl border border-border p-3 text-xs" aria-live="polite">
                        <p className="flex items-center gap-2 font-medium"><Ruler className="h-4 w-4" />Your frame size</p>
                        {!measured && <p className="mt-1 text-muted-foreground">Measuring… look straight at the camera for a moment.</p>}
                        {measured?.size && (
                            <div className="mt-1 flex flex-wrap items-center gap-2">
                                <span>Face width ≈ <strong>{Math.round(measured.faceMm)} mm</strong> → size <strong>{measured.size.code}</strong></span>
                                {onUseSize && <button type="button" onClick={() => onUseSize(measured.size!.code)} className="rounded-full bg-primary px-3 py-1 text-primary-foreground">Use {measured.size.code}</button>}
                                <button type="button" onClick={remeasure} className="underline">Measure again</button>
                            </div>
                        )}
                        {measured && !measured.size && (
                            <p className="mt-1 text-muted-foreground">Couldn't measure reliably. <button type="button" onClick={remeasure} className="underline">Try again</button> in good light, facing the camera.</p>
                        )}
                        <p className="mt-1 text-[0.65rem] text-muted-foreground">An estimate from your camera; for a perfect fit compare with a frame you own (size printed inside the temple).</p>
                    </div>
                </>
            )}

            <CompareLooks refreshKey={lookCount} />
        </div>
    );
}
