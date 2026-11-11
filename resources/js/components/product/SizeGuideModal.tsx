import { useState } from 'react';
import { Check } from 'lucide-react';
import type { FrameSize } from '@/types/product';
import { Modal } from './Modal';

interface SizeGuideModalProps {
    sizes: FrameSize[];
    size: string;
    onSize: (code: string) => void;
    onClose: () => void;
}

/** Closest size to a measured face width (110–170 mm), or null. */
export function matchSize(sizes: FrameSize[], faceWidth: string): FrameSize | null {
    const w = Number(faceWidth);
    if (!faceWidth || !Number.isFinite(w) || w < 110 || w > 170 || !sizes.length) return null;
    return sizes.reduce((best, s) => (Math.abs(s.width - w) < Math.abs(best.width - w) ? s : best));
}

export function SizeGuideModal({ sizes, size, onSize, onClose }: SizeGuideModalProps) {
    const [faceWidth, setFaceWidth] = useState('');
    const data = sizes.find((s) => s.code === size);
    const match = matchSize(sizes, faceWidth);

    return (
        <Modal title="Find your frame size" subtitle="Measurements in millimeters. Provisional specifications; final fit is confirmed by an optician." onClose={onClose} width="max-w-xl">
            <div className="mt-4 flex justify-center gap-2">
                {sizes.map((s) => (
                    <button key={s.code} type="button" onClick={() => onSize(s.code)} aria-pressed={size === s.code} className={`rounded-md px-3 py-1 text-xs ${size === s.code ? 'bg-primary text-primary-foreground' : 'border border-border'}`}>{s.code}</button>
                ))}
            </div>
            <svg viewBox="0 0 420 275" className="mt-4 w-full text-foreground" role="img" aria-label="Frame diagram showing total frame width, lens width, bridge width and temple length">
                <g stroke="currentColor" fill="none" strokeWidth="2">
                    <rect x="56" y="66" width="126" height="70" rx="22" /><rect x="238" y="66" width="126" height="70" rx="22" /><path d="M182 90 Q210 70 238 90 M56 90 H30 M364 90 H390" />
                    <path d="M30 40 H390 M30 33 V47 M390 33 V47 M56 158 H182 M56 151 V165 M182 151 V165 M182 58 H238 M182 51 V65 M238 51 V65" />
                    <path d="M70 216 L304 205 Q332 202 341 230 M70 246 H341 M70 239 V253 M341 239 V253" />
                </g>
                <g fill="currentColor" textAnchor="middle" fontSize="11">
                    <text x="210" y="25">Frame width · {data?.width} mm</text>
                    <text x="119" y="179">Lens · {data?.lens} mm</text>
                    <text x="210" y="151">Bridge</text><text x="210" y="166">{data?.bridge} mm</text>
                    <text x="205" y="267">Temple length · {data?.temple} mm</text>
                </g>
            </svg>
            <label className="mt-4 block text-sm font-medium" htmlFor="face-width">Your face width (mm)</label>
            <div className="mt-2 flex gap-3">
                <input id="face-width" type="number" min={110} max={170} value={faceWidth} onChange={(e) => setFaceWidth(e.target.value)} placeholder="e.g. 130" className="input min-w-0 flex-1" />
                <button type="button" className="btn" disabled={!match} onClick={() => match && onSize(match.code)}><Check className="h-4 w-4" />Use {match?.code ?? 'match'}</button>
            </div>
            {faceWidth && <p role="status" className="mt-2 text-xs text-muted-foreground">{match ? `Recommended size: ${match.code} · ${match.face} faces` : 'Enter a face width between 110 and 170 mm.'}</p>}
        </Modal>
    );
}
