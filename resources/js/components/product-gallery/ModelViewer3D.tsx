import { useEffect, useRef, useState, type DetailedHTMLProps, type HTMLAttributes, type ReactNode } from 'react';
import { Box, Loader2, Pause, Play } from 'lucide-react';
import type { ProductImage } from '@/types/product';
import { useTranslate } from '@/lib/i18n';
import { largestSrc } from './GalleryImage';

type ModelViewerAttributes = DetailedHTMLProps<HTMLAttributes<HTMLElement>, HTMLElement> & Record<string, unknown>;

declare module 'react' {
    namespace JSX {
        interface IntrinsicElements {
            'model-viewer': ModelViewerAttributes;
        }
    }
}

interface ModelViewer3DProps {
    /** URL of the binary glTF (.glb) model. */
    src: string;
    /** Optional Apple USDZ for AR Quick Look; without it the .glb is converted on the iPhone. */
    iosSrc?: string | null;
    productName: string;
    /** Shown while the model downloads. */
    poster?: ProductImage | null;
    /** Rendered instead if the model (or WebGL) fails. */
    fallback: ReactNode;
}

/**
 * Real 3D view of the frame from an uploaded .glb model, rendered with
 * Google's <model-viewer> (WebGL). Turn it any way, pinch or scroll to zoom.
 * Auto-rotates until the shopper interacts (resuming a second after they let
 * go), with a pause button; off for reduced motion. On phones and tablets
 * that support it, "View in your space" places the frame in the room (AR:
 * WebXR / Android Scene Viewer / iOS Quick Look).
 * The 3D library (~1 MB) is only downloaded when this view is opened.
 */
export function ModelViewer3D({ src, iosSrc = null, productName, poster = null, fallback }: ModelViewer3DProps) {
    const t = useTranslate();
    const viewer = useRef<HTMLElement>(null);
    const [libraryReady, setLibraryReady] = useState(false);
    const [progress, setProgress] = useState(0);
    const [loaded, setLoaded] = useState(false);
    const [failed, setFailed] = useState(false);
    const [reducedMotion] = useState(() => window.matchMedia?.('(prefers-reduced-motion: reduce)').matches ?? false);
    const [playing, setPlaying] = useState(!reducedMotion);

    useEffect(() => {
        let cancelled = false;
        import('@google/model-viewer')
            .then(() => !cancelled && setLibraryReady(true))
            .catch(() => !cancelled && setFailed(true));
        return () => {
            cancelled = true;
        };
    }, []);

    useEffect(() => {
        const el = viewer.current;
        if (!el) return;
        setLoaded(false);
        setProgress(0);
        const onProgress = (e: Event) => setProgress(Math.round(((e as CustomEvent<{ totalProgress: number }>).detail.totalProgress ?? 0) * 100));
        const onLoad = () => setLoaded(true);
        const onError = () => setFailed(true);
        el.addEventListener('progress', onProgress);
        el.addEventListener('load', onLoad);
        el.addEventListener('error', onError);
        return () => {
            el.removeEventListener('progress', onProgress);
            el.removeEventListener('load', onLoad);
            el.removeEventListener('error', onError);
        };
    }, [libraryReady, src]);

    if (failed) return <>{fallback}</>;

    return (
        <div className="relative h-full w-full overflow-hidden bg-white">
            {libraryReady && (
                // React 19 sets props that the element defines (src, ar, exposure…) as JS properties, so pass
                // real booleans/numbers there; hyphenated names stay HTML attributes.
                <model-viewer
                    ref={viewer}
                    src={src}
                    ios-src={iosSrc ?? undefined}
                    ar={true}
                    ar-modes="webxr scene-viewer quick-look"
                    alt={t('gallery.spin_label', { name: productName })}
                    poster={poster ? largestSrc(poster) : undefined}
                    camera-controls=""
                    auto-rotate={playing ? '' : undefined}
                    auto-rotate-delay="1000"
                    rotation-per-second="30deg"
                    camera-orbit="30deg 80deg auto"
                    interaction-prompt="none"
                    touch-action="pan-y"
                    shadow-intensity="1"
                    shadow-softness="1"
                    environment-image="neutral"
                    exposure={1.1}
                    data-model-src={src}
                    style={{ width: '100%', height: '100%', backgroundColor: '#fff', ['--poster-color' as string]: '#fff' }}
                >
                    {/* model-viewer only shows this slot on devices that can do AR. */}
                    <button slot="ar-button" type="button" className="absolute bottom-3 left-3 flex items-center gap-2 rounded-full border border-border bg-card px-4 py-2 text-xs font-medium shadow-sm hover:bg-secondary">
                        <Box className="h-4 w-4" />{t('gallery.view_in_ar')}
                    </button>
                </model-viewer>
            )}

            {!loaded && (
                <div className="pointer-events-none absolute inset-0 grid place-items-center">
                    <span className="flex items-center gap-2 rounded-full bg-card/90 px-3 py-1.5 text-sm text-muted-foreground shadow-sm">
                        <Loader2 className="h-4 w-4 animate-spin" aria-hidden="true" />{t('gallery.spin_loading', { percent: progress })}
                    </span>
                </div>
            )}

            <button
                type="button"
                onClick={() => setPlaying((p) => !p)}
                aria-label={playing ? t('gallery.pause_rotation') : t('gallery.play_rotation')}
                className="absolute bottom-3 right-3 rounded-full border border-border bg-card/90 p-2 text-muted-foreground shadow-sm hover:text-foreground"
            >
                {playing ? <Pause className="h-3.5 w-3.5" /> : <Play className="h-3.5 w-3.5" />}
            </button>
            <p className="pointer-events-none absolute bottom-4 left-1/2 hidden -translate-x-1/2 rounded-full bg-card/90 px-3 py-1 text-[0.65rem] text-muted-foreground sm:block">{t('gallery.model_hint')}</p>
        </div>
    );
}
