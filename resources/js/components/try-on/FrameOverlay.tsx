import { useId } from 'react';
import type { FrameKind } from '@/types/product';

/**
 * Front-view glasses drawn for the 2D try-on (the fallback without WebGL).
 * Geometry matches GLASSES in useFaceFit: viewBox
 * 240×100, lens centres x = 68 / 172, outer lens edges x = 28 / 212, pupils at y = 46.
 */
export const GLASSES = { width: 240, height: 100, lensSpan: 184, pupilY: 46 } as const;

const n = (v: number) => Number(v.toFixed(2));

/** Lens outline for one eye centred at cx; o = +1 when the temple is to the right. */
const LENS: Record<FrameKind, (cx: number, o: number) => string> = {
    rect: (cx) => `M${n(cx - 30)},20 H${n(cx + 30)} Q${n(cx + 40)},20 ${n(cx + 40)},30 V64 Q${n(cx + 40)},76 ${n(cx + 28)},76 H${n(cx - 28)} Q${n(cx - 40)},76 ${n(cx - 40)},64 V30 Q${n(cx - 40)},20 ${n(cx - 30)},20 Z`,
    // Wayfarer: strong straight brow, flaring to the temple, narrowing at the bottom.
    bold: (cx, o) => `M${n(cx - o * 38)},22 Q${n(cx - o * 40)},16 ${n(cx - o * 30)},16 H${n(cx + o * 34)} Q${n(cx + o * 42)},16 ${n(cx + o * 41)},24 L${n(cx + o * 36)},64 Q${n(cx + o * 33)},76 ${n(cx + o * 20)},76 H${n(cx - o * 20)} Q${n(cx - o * 34)},76 ${n(cx - o * 37)},62 Z`,
    round: (cx) => `M${n(cx - 37)},48 A37,33 0 1 0 ${n(cx + 37)},48 A37,33 0 1 0 ${n(cx - 37)},48 Z`,
    panto: (cx) => `M${n(cx - 38)},34 Q${n(cx - 36)},18 ${n(cx)},18 Q${n(cx + 36)},18 ${n(cx + 38)},34 Q${n(cx + 40)},78 ${n(cx)},79 Q${n(cx - 40)},78 ${n(cx - 38)},34 Z`,
    hex: (cx) => 'M' + [0, 60, 120, 180, 240, 300].map((a) => `${n(cx + 40 * Math.cos((a * Math.PI) / 180))},${n(48 + 33 * Math.sin((a * Math.PI) / 180))}`).join(' L') + ' Z',
    // Teardrop: near-straight brow, lens drooping deepest towards the nose.
    aviator: (cx, o) => `M${n(cx - o * 30)},22 Q${n(cx + o * 10)},14 ${n(cx + o * 38)},22 Q${n(cx + o * 46)},34 ${n(cx + o * 38)},56 Q${n(cx + o * 26)},86 ${n(cx - o * 10)},83 Q${n(cx - o * 40)},80 ${n(cx - o * 38)},52 Q${n(cx - o * 38)},30 ${n(cx - o * 30)},22 Z`,
    cat: (cx, o) => `M${n(cx - o * 38)},30 Q${n(cx - o * 36)},20 ${n(cx - o * 20)},22 L${n(cx + o * 30)},20 Q${n(cx + o * 44)},14 ${n(cx + o * 42)},28 Q${n(cx + o * 38)},66 ${n(cx + o * 10)},74 Q${n(cx - o * 30)},78 ${n(cx - o * 38)},30 Z`,
};

const BRIDGE: Partial<Record<FrameKind, string>> = {
    bold: 'M106,30 Q120,22 134,30',
    cat: 'M107,32 Q120,24 133,32',
    aviator: 'M104,28 Q120,22 136,28 M106,36 L134,36',
};

interface FrameOverlayProps {
    kind: FrameKind;
    color: string;
    rimless?: boolean;
    thin?: boolean;
    tinted?: boolean;
}

export function FrameOverlay({ kind, color, rimless = false, thin = false, tinted = false }: FrameOverlayProps) {
    const uid = useId().replace(/:/g, '');
    const lenses = `${LENS[kind](68, -1)} ${LENS[kind](172, 1)}`;
    const t = rimless ? 1.2 : thin ? 2.6 : kind === 'bold' ? 9 : 6.5;
    const endPieces = kind === 'bold' ? ['M26,24 L6,22', 'M214,24 L234,22'] : ['M29,34 L6,32', 'M211,34 L234,32'];

    return (
        <svg viewBox={`0 0 ${GLASSES.width} ${GLASSES.height}`} className="h-full w-full overflow-visible" fill="none" strokeLinejoin="round" strokeLinecap="round" aria-hidden="true">
            <defs>
                <linearGradient id={`${uid}-sun`} x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0" stopColor="#0F1113" stopOpacity="0.88" />
                    <stop offset="1" stopColor="#2A2F33" stopOpacity="0.62" />
                </linearGradient>
                <linearGradient id={`${uid}-clear`} x1="0" y1="0" x2="1" y2="1">
                    <stop offset="0" stopColor="#FFFFFF" stopOpacity="0.16" />
                    <stop offset="0.5" stopColor="#FFFFFF" stopOpacity="0.03" />
                    <stop offset="1" stopColor="#C8D6E0" stopOpacity="0.10" />
                </linearGradient>
                <linearGradient id={`${uid}-sheen`} x1="0" y1="0" x2="0" y2="100" gradientUnits="userSpaceOnUse">
                    <stop offset="0.12" stopColor="#FFFFFF" stopOpacity="0.45" />
                    <stop offset="0.4" stopColor="#FFFFFF" stopOpacity="0" />
                </linearGradient>
                <clipPath id={`${uid}-clip`}><path d={lenses} /></clipPath>
            </defs>

            <path d={lenses} fill={`url(#${uid}-${tinted ? 'sun' : 'clear'})`} />
            <g clipPath={`url(#${uid}-clip)`} fill="#FFFFFF" fillOpacity={tinted ? 0.16 : 0.12}>
                <path d="M40,90 L76,10 L90,10 L54,90 Z M144,90 L180,10 L194,10 L158,90 Z" />
            </g>
            <path d={lenses} stroke={rimless ? 'rgba(255,255,255,0.55)' : color} strokeWidth={t} style={{ transition: 'stroke 250ms' }} />
            <g stroke={color} strokeWidth={rimless ? 2.2 : t * 0.85} style={{ transition: 'stroke 250ms' }}>
                <path d={BRIDGE[kind] ?? 'M107,38 Q120,30 133,38'} />
                {endPieces.map((d) => <path key={d} d={d} />)}
            </g>
            {!rimless && <path d={lenses} stroke={`url(#${uid}-sheen)`} strokeWidth={t * 0.45} />}
            {kind === 'bold' && !rimless && (
                <g fill="#D9DCDF"><rect x="30" y="20" width="4" height="2.4" rx="1" /><rect x="206" y="20" width="4" height="2.4" rx="1" /></g>
            )}
            {(thin || rimless) && (
                <g fill="#FFFFFF" fillOpacity="0.35" stroke={color} strokeOpacity="0.6" strokeWidth="1">
                    <ellipse cx="108" cy="60" rx="3.2" ry="5.5" transform="rotate(20 108 60)" />
                    <ellipse cx="132" cy="60" rx="3.2" ry="5.5" transform="rotate(-20 132 60)" />
                </g>
            )}
        </svg>
    );
}
