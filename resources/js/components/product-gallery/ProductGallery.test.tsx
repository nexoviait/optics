import { act, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';
import { I18nProvider } from '@/lib/i18n';
import { makeImages, renderWithI18n, translations } from '@/test/utils';
import { ProductGallery } from './ProductGallery';

// The real <model-viewer> needs WebGL; in jsdom the element just renders inert.
vi.mock('@google/model-viewer', () => ({}));

const status = () => document.getElementById('gallery-status')?.textContent;

describe('ProductGallery', () => {
    it('renders an accessible list with the first image eager and the rest lazy', () => {
        renderWithI18n(<ProductGallery images={makeImages(3)} productName="Havana Keyhole" />);

        expect(screen.getByRole('region', { name: 'Product gallery with 3 images' })).toBeInTheDocument();
        const items = screen.getAllByRole('listitem');
        expect(items).toHaveLength(3);
        expect(items[1]).toHaveAttribute('data-gallery-index', '1');

        const imgs = screen.getAllByRole('img', { name: /Havana Keyhole — view/ });
        expect(imgs[0]).toHaveAttribute('loading', 'eager');
        expect(imgs[0]).toHaveAttribute('fetchpriority', 'high');
        expect(imgs[1]).toHaveAttribute('loading', 'lazy');
        expect(imgs[0]).toHaveAttribute('srcset', '/img-0-480.jpg 480w, /img-0-1080.jpg 1080w');
    });

    it('opens the viewer at the clicked image and syncs the URL hash', async () => {
        const user = userEvent.setup();
        renderWithI18n(<ProductGallery images={makeImages(4)} productName="Havana Keyhole" />);

        await user.click(screen.getByRole('button', { name: 'Open image 3 full screen' }));

        const dialog = screen.getByRole('dialog', { name: 'Havana Keyhole image viewer' });
        expect(dialog).toHaveAttribute('aria-modal', 'true');
        expect(within(dialog).getByText('Image 3 of 4')).toBeInTheDocument();
        expect(window.location.hash).toBe('#view-3');
        expect(document.body.style.overflow).toBe('hidden');
    });

    it('navigates with arrow keys and wraps around', async () => {
        const user = userEvent.setup();
        renderWithI18n(<ProductGallery images={makeImages(3)} productName="Frame" />);
        await user.click(screen.getByRole('button', { name: 'Open image 3 full screen' }));
        const dialog = screen.getByRole('dialog');

        await user.keyboard('{ArrowRight}');
        expect(within(dialog).getByText('Image 1 of 3')).toBeInTheDocument();
        await user.keyboard('{ArrowLeft}');
        expect(within(dialog).getByText('Image 3 of 3')).toBeInTheDocument();

        await user.click(within(dialog).getByRole('button', { name: 'Show image 2' }));
        expect(within(dialog).getByText('Image 2 of 3')).toBeInTheDocument();
        expect(window.location.hash).toBe('#view-2');
    });

    it('closes on Escape, unlocks scrolling and returns focus to the image', async () => {
        const user = userEvent.setup();
        renderWithI18n(<ProductGallery images={makeImages(3)} productName="Frame" />);
        const trigger = screen.getByRole('button', { name: 'Open image 2 full screen' });

        await user.click(trigger);
        expect(screen.getByRole('button', { name: 'Close' })).toHaveFocus();

        await user.keyboard('{Escape}');
        expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
        expect(trigger).toHaveFocus();
        expect(document.body.style.overflow).toBe('');
    });

    it('supports keyboard navigation of the gallery list and Enter to open', async () => {
        const user = userEvent.setup();
        renderWithI18n(<ProductGallery images={makeImages(3)} productName="Frame" />);

        screen.getByRole('list').focus();
        await user.keyboard('{ArrowRight}');
        expect(status()).toBe('Image 2 of 3');
        await user.keyboard('{Enter}');
        expect(within(screen.getByRole('dialog')).getByText('Image 2 of 3')).toBeInTheDocument();
    });

    it('resets to the first image when a new colour variant brings new images', async () => {
        const user = userEvent.setup();
        const { rerender } = renderWithI18n(<ProductGallery images={makeImages(3, 'champagne')} productName="Apex" />);
        screen.getByRole('list').focus();
        await user.keyboard('{ArrowRight}{ArrowRight}');
        expect(status()).toBe('Image 3 of 3');

        rerender(<I18nProvider translations={translations}><ProductGallery images={makeImages(2, 'obsidian')} productName="Apex" /></I18nProvider>);

        expect(status()).toBe('Image 1 of 2');
        expect(screen.getAllByRole('img', { name: /Apex — view/ })[0]).toHaveAttribute('src', '/obsidian-0-1080.jpg');
    });

    it('opens straight into the viewer from a #view-N link', () => {
        window.location.hash = '#view-2';
        renderWithI18n(<ProductGallery images={makeImages(3)} productName="Frame" />);

        expect(within(screen.getByRole('dialog')).getByText('Image 2 of 3')).toBeInTheDocument();
    });

    it('closes when the browser goes back', async () => {
        const user = userEvent.setup();
        renderWithI18n(<ProductGallery images={makeImages(3)} productName="Frame" />);
        await user.click(screen.getByRole('button', { name: 'Open image 1 full screen' }));

        await act(async () => {
            window.history.replaceState(null, '', window.location.pathname);
            window.dispatchEvent(new PopStateEvent('popstate'));
        });
        expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
    });

    it('plays turntable photos in the 360° view when they exist', async () => {
        const user = userEvent.setup();
        renderWithI18n(<ProductGallery images={makeImages(1)} productName="Frame" spinSet={{ frameCount: 8, frames: Array.from({ length: 8 }, (_, i) => `/spin/${i}.webp`) }} />);
        await user.click(screen.getByRole('button', { name: /360°/ }));

        const stage = within(screen.getByRole('dialog')).getByRole('img', { name: '360° view of Frame' });
        expect(stage).toHaveAttribute('data-frame');
    });

    it('shows the 3D model in the 360° view when one is uploaded', async () => {
        const user = userEvent.setup();
        renderWithI18n(<ProductGallery images={makeImages(2)} productName="Frame" model3d="/storage/products/1/model/frame.glb" model3dIos="/models/RF-1.usdz" spinSet={{ frameCount: 8, frames: Array.from({ length: 8 }, (_, i) => `/spin/${i}.webp`) }} />);
        await user.click(screen.getByRole('button', { name: /360°/ }));

        const dialog = screen.getByRole('dialog');
        const viewer = await vi.waitFor(() => {
            const el = dialog.querySelector('model-viewer');
            if (!el) throw new Error('model-viewer not rendered yet');
            return el;
        });
        expect(viewer).toHaveAttribute('src', '/storage/products/1/model/frame.glb');
        expect(viewer).toHaveAttribute('camera-controls');
        expect(viewer).toHaveAttribute('auto-rotate');
        // AR: "View in your space" on capable phones (model-viewer decides when to show the button).
        expect(viewer).toHaveAttribute('ar');
        expect(viewer).toHaveAttribute('ar-modes', 'webxr scene-viewer quick-look');
        expect(viewer).toHaveAttribute('ios-src', '/models/RF-1.usdz');
        expect(within(viewer as HTMLElement).getByRole('button', { name: 'View in your space' })).toHaveAttribute('slot', 'ar-button');
        // Photos are the fallback, not shown alongside.
        expect(within(dialog).queryByRole('img', { name: '360° view of Frame' })).toBeNull();

        await user.click(within(dialog).getByRole('button', { name: 'Pause rotation' }));
        expect(viewer).not.toHaveAttribute('auto-rotate');

        // If the model cannot load, the photo 360° view takes over.
        await act(async () => { viewer.dispatchEvent(new Event('error')); });
        expect(dialog.querySelector('model-viewer')).toBeNull();
        expect(within(dialog).getByRole('img', { name: '360° view of Frame' })).toHaveAttribute('data-frame');
    });

    it('turns the real product photo in the 360° view when there are no turntable photos', async () => {
        const user = userEvent.setup();
        renderWithI18n(<ProductGallery images={makeImages(2)} productName="Frame" />);
        await user.click(screen.getByRole('button', { name: /360°/ }));

        expect(window.location.hash).toBe('#view-360');
        const stage = within(screen.getByRole('dialog')).getByRole('img', { name: '360° view of Frame' });
        // The same photo as the gallery's first image, never a drawing.
        expect(stage.querySelector('img')).toHaveAttribute('src', '/img-0-1080.jpg');
        expect(stage.querySelector('canvas')).toBeNull();
        expect(screen.getByRole('button', { name: 'Pause rotation' })).toBeInTheDocument();
    });
});
