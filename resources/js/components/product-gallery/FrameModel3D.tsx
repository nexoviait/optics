import { useEffect, useState, type ReactNode } from 'react';
import { Loader2 } from 'lucide-react';
import type { FrameModelData } from '@/lib/frameModel';
import { useTranslate } from '@/lib/i18n';
import { ModelViewer3D } from './ModelViewer3D';

interface FrameModel3DProps {
    frame: FrameModelData;
    productName: string;
    /** Rendered instead if the model can't be built or WebGL fails. */
    fallback: ReactNode;
}

/**
 * 360° view for products without an uploaded .glb: a solid 3D frame is built in
 * the browser from the product's shape and colour, exported as a .glb and shown
 * in the same <model-viewer> stage as uploaded models (white background, soft
 * shadow, drag / zoom, auto-rotate). three.js only loads when this view opens.
 */
export function FrameModel3D({ frame, productName, fallback }: FrameModel3DProps) {
    const t = useTranslate();
    const key = JSON.stringify(frame);
    const [src, setSrc] = useState<string | null>(null);
    const [failed, setFailed] = useState(false);

    useEffect(() => {
        let cancelled = false;
        setSrc(null);
        setFailed(false);
        import('./frameGlb')
            .then(({ frameGlbUrl }) => frameGlbUrl(JSON.parse(key) as FrameModelData))
            .then((url) => !cancelled && setSrc(url))
            .catch(() => !cancelled && setFailed(true));
        return () => {
            cancelled = true;
        };
    }, [key]);

    if (failed) return <>{fallback}</>;
    if (!src) {
        return (
            <div className="grid h-full w-full place-items-center bg-white">
                <span className="flex items-center gap-2 rounded-full bg-card/90 px-3 py-1.5 text-sm text-muted-foreground shadow-sm">
                    <Loader2 className="h-4 w-4 animate-spin" aria-hidden="true" />{t('gallery.spin_loading', { percent: 0 })}
                </span>
            </div>
        );
    }
    return <ModelViewer3D src={src} productName={productName} fallback={fallback} />;
}
