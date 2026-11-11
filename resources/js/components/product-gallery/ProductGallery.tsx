import { useCallback, useEffect, useRef, useState, type KeyboardEvent } from 'react';
import { RotateCw } from 'lucide-react';
import type { GalleryLayout, ProductImage, SpinSet } from '@/types/product';
import { useTranslate } from '@/lib/i18n';
import { GalleryImage } from './GalleryImage';
import { ImageViewerModal, PHOTOS_MODE, type ViewerMode } from './ImageViewerModal';
import type { FrameModelData } from '@/lib/frameModel';
import { FrameModel3D } from './FrameModel3D';
import { ModelViewer3D } from './ModelViewer3D';
import { SpinViewer } from './SpinViewer';
import { useGalleryIndex } from './hooks/useGalleryIndex';
import { useMediaQuery } from './hooks/useMediaQuery';

export interface ProductGalleryProps {
    images: ProductImage[];
    productName: string;
    spinSet?: SpinSet | null;
    /** URL of a 3D model (.glb); the 360° view shows it in preference to photos. */
    model3d?: string | null;
    /** Optional Apple USDZ of the same model, for AR on iPhone. */
    model3dIos?: string | null;
    /** Product shape; without a .glb or 360° photos, a 3D frame is built from it. */
    frameModel?: FrameModelData | null;
    /** Desktop arrangement; mobile is always a swipeable carousel. */
    layout?: GalleryLayout;
    /** Extra viewer modes (e.g. virtual try-on), shown as buttons over the main photo, before 360°. */
    extraModes?: ViewerMode[];
}

const SPIN_MODE = 'spin';
const HASH = /^#view-(.+)$/;

interface ViewerState {
    mode: string;
    /** True when opening pushed a history entry, so closing should go back. */
    pushed: boolean;
}

/**
 * Product photo gallery (Lenskart-style): a scroll-snap carousel on mobile, a
 * grid or main-image + thumbnails on desktop, and a fullscreen viewer with zoom.
 * The open viewer is reflected in the URL hash (#view-3, #view-360) so the back
 * button closes it and the view can be linked to.
 */
export function ProductGallery({ images, productName, spinSet = null, model3d = null, model3dIos = null, frameModel = null, layout = 'grid', extraModes = [] }: ProductGalleryProps) {
    const t = useTranslate();
    const total = images.length;
    const { index, setIndex } = useGalleryIndex(total, images);
    const [viewer, setViewer] = useState<ViewerState | null>(null);
    const list = useRef<HTMLUListElement>(null);
    const items = useRef<(HTMLButtonElement | null)[]>([]);
    const programmaticScroll = useRef(false);

    const spinFrames = spinSet && spinSet.frames.length > 1 ? spinSet.frames : [];
    const photoSpin = <SpinViewer frames={spinFrames} image={images[0]} productName={productName} />;
    const spinView = model3d ? (
        <ModelViewer3D src={model3d} iosSrc={model3dIos} poster={images[0]} productName={productName} fallback={photoSpin} />
    ) : spinFrames.length || !frameModel ? (
        photoSpin
    ) : (
        <FrameModel3D frame={frameModel} productName={productName} fallback={photoSpin} />
    );
    const modes: ViewerMode[] = [
        ...extraModes,
        // An uploaded 3D model, then turntable photos, then a 3D frame built from the product's shape.
        ...(model3d || spinFrames.length || frameModel || images[0]
            ? [{ id: SPIN_MODE, label: t('gallery.spin'), icon: <RotateCw className="h-3.5 w-3.5" />, render: () => spinView }]
            : []),
    ];
    // Desktop grid: the buttons sit on the first (full-width) photo, not under the whole grid.
    const desktopGrid = useMediaQuery('(min-width: 1024px)') && layout === 'grid';
    const modeIds = [PHOTOS_MODE, ...modes.map((m) => m.id)];

    const hashFor = (mode: string, i: number) => `#view-${mode === PHOTOS_MODE ? i + 1 : mode === SPIN_MODE ? '360' : mode}`;

    // modeIds is rebuilt every render; key the hash parser on its contents instead.
    const modeKey = modeIds.join(',');

    /** Parse "#view-3" / "#view-360" / "#view-try-on" into a viewer state. */
    const fromHash = useCallback((): { mode: string; index: number } | null => {
        const match = HASH.exec(window.location.hash)?.[1];
        if (!match) return null;
        if (/^\d+$/.test(match) && match !== '360') return { mode: PHOTOS_MODE, index: Math.min(Math.max(Number(match) - 1, 0), Math.max(total - 1, 0)) };
        const mode = match === '360' ? SPIN_MODE : match;
        return modeKey.split(',').includes(mode) ? { mode, index: 0 } : null;
    }, [total, modeKey]);

    const open = (mode: string, i = index) => {
        setIndex(i);
        window.history.pushState({ ...window.history.state, galleryViewer: true }, '', hashFor(mode, i));
        setViewer({ mode, pushed: true });
    };

    const viewerRef = useRef(viewer);
    viewerRef.current = viewer;

    const close = useCallback(() => {
        // Opening pushed a history entry: going back both closes the viewer and keeps history tidy.
        if (viewerRef.current?.pushed && window.history.state?.galleryViewer) window.history.back();
        else if (HASH.test(window.location.hash)) window.history.replaceState(window.history.state, '', window.location.pathname + window.location.search);
        setViewer(null);
    }, []);

    // Deep link on load, and back/forward buttons.
    useEffect(() => {
        const initial = fromHash();
        if (initial) {
            setIndex(initial.index);
            setViewer({ mode: initial.mode, pushed: false });
        }
        const onPop = () => {
            const state = fromHash();
            if (state) {
                setIndex(state.index);
                setViewer((v) => ({ mode: state.mode, pushed: v?.pushed ?? false }));
            } else {
                setViewer(null);
            }
        };
        window.addEventListener('popstate', onPop);
        return () => window.removeEventListener('popstate', onPop);
    }, [fromHash, setIndex]);

    // Keep the hash in step with the slide / mode while the viewer is open.
    const openMode = viewer?.mode;
    useEffect(() => {
        if (openMode) window.history.replaceState(window.history.state, '', hashFor(openMode, index));
    }, [openMode, index]);

    // Carousel (mobile): bring the active slide into view when the index changes programmatically.
    useEffect(() => {
        const el = list.current;
        if (!el || el.scrollWidth <= el.clientWidth + 1) return;
        const target = el.clientWidth * index;
        if (Math.abs(el.scrollLeft - target) < 2) return;
        programmaticScroll.current = true;
        el.scrollTo({ left: target, behavior: 'smooth' });
    }, [index]);

    const onScroll = () => {
        const el = list.current;
        if (!el || el.scrollWidth <= el.clientWidth + 1) return;
        const i = Math.round(el.scrollLeft / el.clientWidth);
        if (programmaticScroll.current) {
            if (i === index) programmaticScroll.current = false;
            return;
        }
        if (i !== index) setIndex(i);
    };

    const onListKeyDown = (e: KeyboardEvent<HTMLUListElement>) => {
        if (e.key === 'ArrowRight' || e.key === 'ArrowLeft') {
            e.preventDefault();
            setIndex(index + (e.key === 'ArrowRight' ? 1 : -1));
        } else if (e.key === 'Home' || e.key === 'End') {
            e.preventDefault();
            setIndex(e.key === 'Home' ? 0 : total - 1);
        } else if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            open(PHOTOS_MODE, index);
        }
    };

    const thumbnails = layout === 'thumbnails';

    const modeBar = modes.length > 0 && (
        <div className="absolute bottom-4 left-4 z-10 flex divide-x divide-border overflow-hidden rounded-md border border-foreground/30 bg-card/95 text-sm shadow-sm backdrop-blur">
            {modes.map((m) => (
                <button key={m.id} type="button" onClick={() => open(m.id)} className="flex items-center gap-2 px-4 py-2.5 hover:bg-secondary [&_svg]:h-4 [&_svg]:w-4">
                    {m.icon}{m.label}
                </button>
            ))}
        </div>
    );

    return (
        <section aria-label={t('gallery.region', { total })} className="relative">
            <div className="relative">
                <ul
                    ref={list}
                    role="list"
                    tabIndex={0}
                    aria-describedby="gallery-status"
                    onKeyDown={onListKeyDown}
                    onScroll={onScroll}
                    className={`group/gallery flex snap-x snap-mandatory overflow-x-auto rounded-2xl border border-border bg-white [scrollbar-width:none] focus:outline-none focus-visible:ring-2 focus-visible:ring-ring lg:snap-none lg:overflow-visible ${
                        thumbnails ? 'lg:block' : 'lg:grid lg:grid-cols-2 lg:gap-2 lg:border-0 lg:bg-transparent'
                    }`}
                >
                    {images.map((image, i) => (
                        <li
                            key={image.id ?? i}
                            role="listitem"
                            data-gallery-index={i}
                            className={`relative w-full shrink-0 snap-center ${
                                thumbnails
                                    ? i === index ? 'lg:block' : 'lg:hidden'
                                    : `lg:overflow-hidden lg:rounded-2xl lg:border lg:border-border ${i === 0 ? 'lg:col-span-2' : ''} ${i === index ? 'group-focus-visible/gallery:lg:ring-2 group-focus-visible/gallery:lg:ring-ring' : ''}`
                            }`}
                        >
                            <button
                                ref={(el) => { items.current[i] = el; }}
                                type="button"
                                tabIndex={-1}
                                onClick={() => open(PHOTOS_MODE, i)}
                                aria-label={t('gallery.open_viewer', { n: i + 1 })}
                                className="block w-full cursor-zoom-in"
                            >
                                <GalleryImage image={image} alt={image.alt || t('gallery.image_alt', { name: productName, n: i + 1 })} priority={i === 0} />
                            </button>
                            {i === 0 && desktopGrid && modeBar}
                        </li>
                    ))}
                </ul>
                {!desktopGrid && modeBar}
            </div>

            {total > 1 && (
                <div className="mt-3 flex justify-center gap-1.5 lg:hidden" aria-hidden="true">
                    {images.map((image, i) => (
                        <button key={image.id ?? i} type="button" tabIndex={-1} onClick={() => setIndex(i)}
                                className={`h-1.5 rounded-full transition-all ${i === index ? 'w-5 bg-primary' : 'w-1.5 bg-border'}`} />
                    ))}
                </div>
            )}

            {thumbnails && total > 1 && (
                <div className="mt-3 hidden gap-2 overflow-x-auto pb-1 lg:flex" role="group" aria-label={t('gallery.region', { total })}>
                    {images.map((image, i) => (
                        <button key={image.id ?? i} type="button" onClick={() => setIndex(i)} aria-label={t('gallery.show_image', { n: i + 1 })} aria-current={i === index ? 'true' : undefined}
                                className={`w-20 shrink-0 overflow-hidden rounded-md border bg-white ${i === index ? 'ring-2 ring-primary' : 'border-border opacity-80 hover:opacity-100'}`}>
                            <img src={image.thumb} alt="" loading="lazy" className="aspect-square w-full object-contain" />
                        </button>
                    ))}
                </div>
            )}

            <p id="gallery-status" className="sr-only" aria-live="polite">{t('gallery.slide_status', { n: index + 1, total })}</p>

            {viewer && (
                <ImageViewerModal
                    images={images}
                    productName={productName}
                    index={index}
                    onIndexChange={setIndex}
                    mode={viewer.mode}
                    modes={modes}
                    onModeChange={(mode) => setViewer((v) => (v ? { ...v, mode } : v))}
                    onClose={close}
                    returnFocus={() => items.current[index] ?? list.current}
                />
            )}
        </section>
    );
}
