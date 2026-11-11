import { useCallback, useEffect, useRef, useState, type KeyboardEvent, type ReactNode } from 'react';
import { createPortal } from 'react-dom';
import { ChevronLeft, ChevronRight, Images, X, ZoomIn, ZoomOut } from 'lucide-react';
import type { ProductImage } from '@/types/product';
import { useTranslate } from '@/lib/i18n';
import { GalleryImage, largestSrc } from './GalleryImage';
import { ZoomPane, type ZoomPaneHandle } from './ZoomPane';
import { useBodyScrollLock } from './hooks/useBodyScrollLock';
import { useFocusTrap } from './hooks/useFocusTrap';
import { useImagePreload } from './hooks/useImagePreload';
import { useSwipe } from './hooks/useSwipe';

/**
 * An extra way to look at the product inside the viewer (360° spin, virtual
 * try-on, and later e.g. a WebGL/GLB model). The viewer only needs a label,
 * an icon and something to render; the mode owns its own interaction.
 */
export interface ViewerMode {
    id: string;
    label: string;
    icon: ReactNode;
    render: () => ReactNode;
}

export const PHOTOS_MODE = 'photos';

interface ImageViewerModalProps {
    images: ProductImage[];
    productName: string;
    index: number;
    onIndexChange: (index: number) => void;
    mode: string;
    modes: ViewerMode[];
    onModeChange: (mode: string) => void;
    onClose: () => void;
    /** Element to focus after closing (the gallery image that opened the viewer). */
    returnFocus: () => HTMLElement | null;
}

export function ImageViewerModal({ images, productName, index, onIndexChange, mode, modes, onModeChange, onClose, returnFocus }: ImageViewerModalProps) {
    const t = useTranslate();
    const dialog = useRef<HTMLDivElement>(null);
    const closeButton = useRef<HTMLButtonElement>(null);
    const zoom = useRef<ZoomPaneHandle>(null);
    const [zoomed, setZoomed] = useState(false);
    const total = images.length;
    const current = images[index];
    const isPhotos = mode === PHOTOS_MODE;

    useBodyScrollLock(true);
    useFocusTrap(dialog, true, { initialFocus: closeButton, returnFocus });

    const go = useCallback((step: number) => {
        if (total > 1) onIndexChange((index + step + total) % total);
    }, [index, total, onIndexChange]);

    // A new slide always starts un-zoomed.
    useEffect(() => zoom.current?.reset(), [index, mode]);

    const neighbour = (step: number) => {
        const image = images[(index + step + total) % total];
        return image ? largestSrc(image) : undefined;
    };
    useImagePreload(isPhotos && total > 1 ? [neighbour(1), neighbour(-1)] : []);

    const swipe = useSwipe({ onLeft: () => go(1), onRight: () => go(-1), disabled: zoomed || !isPhotos });

    const onKeyDown = (e: KeyboardEvent<HTMLDivElement>) => {
        if (e.key === 'Escape') {
            e.stopPropagation();
            onClose();
        } else if (isPhotos && !(e.target instanceof HTMLInputElement) && (e.key === 'ArrowRight' || e.key === 'ArrowLeft')) {
            e.preventDefault();
            go(e.key === 'ArrowRight' ? 1 : -1);
        }
    };

    const allModes: ViewerMode[] = [{ id: PHOTOS_MODE, label: t('gallery.photos'), icon: <Images className="h-3.5 w-3.5" />, render: () => null }, ...modes];
    const activeMode = modes.find((m) => m.id === mode);

    return createPortal(
        <div
            // No backdrop blur: re-blurring the page behind every frame of the 360° animation made it stutter.
            className="fixed inset-0 z-[60] grid place-items-center bg-obsidian/80 p-0 sm:p-6"
            onMouseDown={(e) => e.target === e.currentTarget && onClose()}
        >
            <div
                ref={dialog}
                role="dialog"
                aria-modal="true"
                aria-label={t('gallery.viewer_label', { name: productName })}
                onKeyDown={onKeyDown}
                className="relative flex h-full w-full max-w-6xl flex-col overflow-hidden bg-white shadow-2xl sm:h-[min(92vh,900px)] sm:rounded-3xl"
            >
                <header className="flex items-center gap-2 border-b border-border px-3 py-2.5 sm:px-5">
                    {allModes.length > 1 && (
                        <div role="tablist" aria-label={productName} className="flex gap-1 rounded-lg border border-border p-1 text-xs">
                            {allModes.map((m) => (
                                <button key={m.id} type="button" role="tab" aria-selected={m.id === mode} onClick={() => onModeChange(m.id)}
                                        className={`flex items-center gap-1 rounded-md px-2.5 py-1.5 ${m.id === mode ? 'bg-primary text-primary-foreground' : 'hover:bg-secondary'}`}>
                                    {m.icon}{m.label}
                                </button>
                            ))}
                        </div>
                    )}
                    <div className="ml-auto flex items-center gap-1">
                        {isPhotos && (
                            <button type="button" onClick={() => zoom.current?.toggle()} aria-pressed={zoomed} aria-label={zoomed ? t('gallery.zoom_out') : t('gallery.zoom_in')} className="rounded-full p-2 hover:bg-secondary">
                                {zoomed ? <ZoomOut className="h-5 w-5" /> : <ZoomIn className="h-5 w-5" />}
                            </button>
                        )}
                        <button ref={closeButton} type="button" onClick={onClose} aria-label={t('gallery.close')} className="rounded-full p-2 hover:bg-secondary">
                            <X className="h-5 w-5" />
                        </button>
                    </div>
                </header>

                <div className="relative min-h-0 flex-1">
                    {isPhotos && current && (
                        <>
                            <div className="h-full w-full" {...swipe}>
                                <ZoomPane ref={zoom} onZoomChange={setZoomed}>
                                    <GalleryImage key={current.id ?? index} image={current} alt={t('gallery.image_alt', { name: productName, n: index + 1 })} priority sizes="100vw" fill />
                                </ZoomPane>
                            </div>
                            {total > 1 && (
                                <>
                                    <button type="button" onClick={() => go(-1)} aria-label={t('gallery.previous')} className="absolute left-3 top-1/2 -translate-y-1/2 rounded-full border border-border bg-card/95 p-2.5 shadow hover:bg-secondary">
                                        <ChevronLeft className="h-5 w-5" />
                                    </button>
                                    <button type="button" onClick={() => go(1)} aria-label={t('gallery.next')} className="absolute right-3 top-1/2 -translate-y-1/2 rounded-full border border-border bg-card/95 p-2.5 shadow hover:bg-secondary">
                                        <ChevronRight className="h-5 w-5" />
                                    </button>
                                </>
                            )}
                            <p className="pointer-events-none absolute bottom-3 left-1/2 hidden -translate-x-1/2 rounded-full bg-card/90 px-3 py-1 text-[0.65rem] text-muted-foreground sm:block">{t('gallery.zoom_hint')}</p>
                        </>
                    )}
                    {!isPhotos && activeMode && <div className="h-full w-full overflow-auto p-3 sm:p-6">{activeMode.render()}</div>}
                </div>

                {isPhotos && total > 1 && (
                    <footer className="flex justify-center gap-2 overflow-x-auto border-t border-border px-3 py-3" aria-label={t('gallery.region', { total })}>
                        {images.map((image, i) => (
                            <button key={image.id ?? i} type="button" onClick={() => onIndexChange(i)} aria-label={t('gallery.show_image', { n: i + 1 })} aria-current={i === index ? 'true' : undefined}
                                    className={`h-16 w-16 shrink-0 overflow-hidden rounded-lg border-2 bg-white ${i === index ? 'border-primary' : 'border-transparent opacity-70 hover:opacity-100'}`}>
                                <img src={image.thumb} alt="" loading="lazy" className="h-full w-full object-contain" />
                            </button>
                        ))}
                    </footer>
                )}

                <p className="sr-only" aria-live="polite">{isPhotos ? t('gallery.slide_status', { n: index + 1, total }) : activeMode?.label}</p>
            </div>
        </div>,
        document.body,
    );
}
