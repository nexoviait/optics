import { createRoot } from 'react-dom/client';
import type { LiveTryOnSettings } from '@/components/try-on/live/LiveTryOn';
import { PhotoTryOn3D } from '@/components/try-on/PhotoTryOn3D';
import { TryOnStage } from '@/components/try-on/TryOnStage';
import type { TryOnStyle } from '@/types/product';

export interface TryOnProps {
    portrait: string;
    glasses: LiveTryOnSettings;
    /** Drawn 2D frame, for browsers without WebGL. */
    tryOn: TryOnStyle;
    color: string;
    frameWidthMm: number;
    mediapipeBase: string;
}

/** The product page's 3D photo try-on, mounted into a Blade page: call `render` with new props, `unmount` when done. */
export function mountTryOn(el: HTMLElement) {
    const root = createRoot(el);
    return {
        render: (p: TryOnProps) =>
            root.render(
                <PhotoTryOn3D
                    portrait={p.portrait}
                    glasses={p.glasses}
                    color={p.color}
                    frameWidthMm={p.frameWidthMm}
                    mediapipeBase={p.mediapipeBase}
                    fallback={<TryOnStage portrait={p.portrait} frame={p.tryOn} color={p.color} mediapipeBase={p.mediapipeBase} />}
                />,
            ),
        unmount: () => root.unmount(),
    };
}
