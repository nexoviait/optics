import { render, type RenderOptions } from '@testing-library/react';
import type { ReactElement } from 'react';
import { I18nProvider } from '@/lib/i18n';
import type { ProductImage } from '@/types/product';

/** Subset of lang/en/gallery.php used by the components under test. */
export const translations = {
    gallery: {
        region: 'Product gallery with :total images',
        image_alt: ':name — view :n',
        slide_status: 'Image :n of :total',
        open_viewer: 'Open image :n full screen',
        show_image: 'Show image :n',
        previous: 'Previous image',
        next: 'Next image',
        close: 'Close',
        viewer_label: ':name image viewer',
        zoom_in: 'Zoom in',
        zoom_out: 'Zoom out',
        zoom_hint: 'Double-click or pinch to zoom',
        photos: 'Photos',
        spin: '360°',
        spin_label: '360° view of :name',
        spin_loading: 'Loading 360° view… :percent%',
        spin_hint: 'Drag to rotate',
        model_hint: 'Drag to turn',
        view_in_ar: 'View in your space',
        pause_rotation: 'Pause rotation',
        play_rotation: 'Play rotation',
        rotate_left: 'Rotate left',
        rotate_right: 'Rotate right',
    },
};

export function renderWithI18n(ui: ReactElement, options?: RenderOptions) {
    return render(<I18nProvider translations={translations}>{ui}</I18nProvider>, options);
}

export function makeImages(count: number, prefix = 'img'): ProductImage[] {
    return Array.from({ length: count }, (_, i) => ({
        id: i + 1,
        alt: null,
        viewType: 'front',
        width: 1080,
        height: 1080,
        isPrimary: i === 0,
        sources: {
            webp: { '480': `/${prefix}-${i}-480.webp`, '1080': `/${prefix}-${i}-1080.webp` },
            jpg: { '480': `/${prefix}-${i}-480.jpg`, '1080': `/${prefix}-${i}-1080.jpg` },
        },
        thumb: `/${prefix}-${i}-thumb.webp`,
    }));
}
