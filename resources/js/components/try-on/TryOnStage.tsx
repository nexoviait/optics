import { useRef } from 'react';
import { Loader2, Minus, Plus, RotateCcw } from 'lucide-react';
import type { TryOnStyle } from '@/types/product';
import { FrameOverlay } from './FrameOverlay';
import { useFaceFit } from './useFaceFit';

interface TryOnStageProps {
    portrait: string;
    frame: TryOnStyle;
    color: string;
    mediapipeBase: string;
    className?: string;
}

const STATUS_TEXT = { fitted: 'Fitted to your eyes · drag to adjust', manual: 'Drag the frame onto your eyes' } as const;

/**
 * Virtual try-on: a portrait with the glasses fitted to the detected pupils.
 * Shows the product's transparent try-on photo when one was uploaded, otherwise
 * the drawn frame in the selected finish.
 */
export function TryOnStage({ portrait, frame, color, mediapipeBase, className = '' }: TryOnStageProps) {
    const stage = useRef<HTMLDivElement>(null);
    const photo = useRef<HTMLImageElement>(null);
    const { status, style, detect, resize, reset, handlers } = useFaceFit(stage, photo, mediapipeBase);

    return (
        <div ref={stage} className={`relative aspect-[4/5] select-none overflow-hidden rounded-2xl bg-obsidian ${className}`}>
            <img ref={photo} src={portrait} onLoad={() => void detect()} alt="Virtual try-on portrait" draggable={false} className="h-full w-full object-cover" />

            <div
                role="application"
                tabIndex={0}
                aria-label="Glasses position. Drag, or use arrow keys to move and plus or minus to resize."
                style={style}
                {...handlers}
                className={`absolute cursor-grab touch-none drop-shadow-[0_3px_4px_rgba(0,0,0,0.35)] transition-opacity duration-300 focus:outline-none focus-visible:ring-2 focus-visible:ring-bronze active:cursor-grabbing ${status === 'loading' ? 'pointer-events-none opacity-0' : 'opacity-100'}`}
            >
                {frame.image
                    ? <img src={frame.image} alt="" draggable={false} className="pointer-events-none h-full w-full object-contain" />
                    : <FrameOverlay kind={frame.kind} color={color} rimless={frame.rimless} thin={frame.thin} tinted={frame.tinted} />}
            </div>

            {status === 'loading' && (
                <div className="pointer-events-none absolute inset-0 grid place-items-center">
                    <span className="flex items-center gap-2 rounded-full bg-obsidian/75 px-3 py-1.5 text-xs text-on-dark backdrop-blur"><Loader2 className="h-3.5 w-3.5 animate-spin" />Locating your eyes…</span>
                </div>
            )}
            {status !== 'loading' && (
                <>
                    <div className="absolute right-3 top-3 flex items-center gap-1 rounded-full bg-obsidian/75 p-1 text-on-dark backdrop-blur">
                        <button type="button" onClick={() => resize(-0.05)} className="rounded-full p-1.5 hover:bg-on-dark/15" aria-label="Make glasses smaller"><Minus className="h-3.5 w-3.5" /></button>
                        <button type="button" onClick={() => resize(0.05)} className="rounded-full p-1.5 hover:bg-on-dark/15" aria-label="Make glasses larger"><Plus className="h-3.5 w-3.5" /></button>
                        <button type="button" onClick={reset} className="rounded-full p-1.5 hover:bg-on-dark/15" aria-label="Reset glasses position"><RotateCcw className="h-3.5 w-3.5" /></button>
                    </div>
                    <p aria-live="polite" className="pointer-events-none absolute left-1/2 top-14 -translate-x-1/2 whitespace-nowrap rounded-full bg-obsidian/60 px-2.5 py-1 text-[0.6rem] text-on-dark backdrop-blur">{STATUS_TEXT[status]}</p>
                </>
            )}
        </div>
    );
}
