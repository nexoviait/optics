import { describe, expect, it } from 'vitest';
import type { Landmark } from '@/components/try-on/live/measure';
import { MESH_TO_FACE_WIDTH } from '@/components/try-on/live/measure';
import { analyseFace, classifyShape, measurementPoints } from './faceAnalysis';

const W = 1000;
const H = 1000;

/**
 * A synthetic face in a 1000 × 1000 photo: irises 23.4 px across (2 px/mm), pupils
 * 126 px apart (63 mm), cheeks 240 px apart (120 mm), forehead-to-chin 288 px (ratio 1.2).
 */
function face({ ratio = 1.2, jaw = 0.8, forehead = 0.92, noseX = 500 } = {}): Landmark[] {
    const lm: Landmark[] = Array.from({ length: 478 }, () => ({ x: 0, y: 0, z: 0 }));
    const at = (i: number, x: number, y: number) => (lm[i] = { x: x / W, y: y / H, z: 0 });
    const cheeks = 240;
    at(234, 500 - cheeks / 2, 500);
    at(454, 500 + cheeks / 2, 500);
    at(10, 500, 500 - (cheeks * ratio) / 2);
    at(152, 500, 500 + (cheeks * ratio) / 2);
    at(172, 500 - (cheeks * jaw) / 2, 600);
    at(397, 500 + (cheeks * jaw) / 2, 600);
    at(21, 500 - (cheeks * forehead) / 2, 400);
    at(251, 500 + (cheeks * forehead) / 2, 400);
    at(1, noseX, 520);
    for (const [centre, ring] of [[437, 469], [563, 474]] as const) {
        at(ring === 469 ? 468 : 473, centre, 460);
        at(ring, centre + 11.7, 460);
        at(ring + 1, centre, 460 - 11.7);
        at(ring + 2, centre - 11.7, 460);
        at(ring + 3, centre, 460 + 11.7);
    }
    return lm;
}

describe('classifyShape', () => {
    it('reads typical proportions as oval', () => {
        expect(classifyShape({ ratio: 1.2, jaw: 0.79, forehead: 0.92 })).toBe('oval');
    });

    it('reads a long face as oblong', () => {
        expect(classifyShape({ ratio: 1.36, jaw: 0.79, forehead: 0.91 })).toBe('oblong');
    });

    it('tells round from square by the jaw on a short face', () => {
        expect(classifyShape({ ratio: 1.04, jaw: 0.8, forehead: 0.92 })).toBe('round');
        expect(classifyShape({ ratio: 1.06, jaw: 0.88, forehead: 0.92 })).toBe('square');
    });

    it('finds a heart (wide forehead, narrow jaw) and a diamond (narrow forehead and jaw)', () => {
        expect(classifyShape({ ratio: 1.22, jaw: 0.72, forehead: 0.93 })).toBe('heart');
        expect(classifyShape({ ratio: 1.22, jaw: 0.72, forehead: 0.84 })).toBe('diamond');
    });

    it('calls a balanced face with a strong jaw square', () => {
        expect(classifyShape({ ratio: 1.2, jaw: 0.88, forehead: 0.93 })).toBe('square');
    });
});

describe('analyseFace', () => {
    it('measures PD, width and length with the iris as the ruler', () => {
        const a = analyseFace(face(), W, H)!;
        expect(a.shape).toBe('oval');
        expect(a.pd).toBeCloseTo(63, 0);
        expect(a.width).toBeCloseTo(120 * MESH_TO_FACE_WIDTH, 0);
        expect(a.length).toBeCloseTo(144, 0);
        expect(a.iris).toBe(true);
        expect(a.turned).toBe(false);
        expect(a.small).toBe(false);
    });

    it('flags a turned head', () => {
        expect(analyseFace(face({ noseX: 560 }), W, H)!.turned).toBe(true);
    });

    it('flags a face too small to measure well', () => {
        // The same face in a photo a quarter the size.
        expect(analyseFace(face(), 250, 250)!.small).toBe(true);
    });

    it('returns null without the key landmarks', () => {
        expect(analyseFace([], W, H)).toBeNull();
    });

    it('gives the measured points in image pixels for the overlay', () => {
        const pts = measurementPoints(face(), W, H);
        expect(pts.pupils[0]).toEqual({ x: 437, y: 460 });
        expect(pts.cheeks[1]!.x).toBeCloseTo(620, 5);
    });
});
