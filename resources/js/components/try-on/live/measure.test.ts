import { describe, expect, it } from 'vitest';
import { faceWidthMm, median, MESH_TO_FACE_WIDTH, pixelsPerMm, recommendSize } from './measure';
import type { Landmark } from './measure';

// Build a minimal landmark array (478 entries) with specific values set.
function makeLandmarks(overrides: Record<number, Landmark>): Landmark[] {
    const lm: Landmark[] = Array.from({ length: 478 }, () => ({ x: 0, y: 0, z: 0 }));
    Object.entries(overrides).forEach(([k, v]) => {
        lm[Number(k)] = v;
    });
    return lm;
}

describe('pixelsPerMm', () => {
    it('computes pixels per mm from iris landmarks', () => {
        const W = 1280;
        const H = 720;
        // Both irises: 469-472 = right iris, 474-477 = left iris.
        // Diameter of 23.4 px → ppm = 23.4 / 11.7 = 2
        const irisPxDiameter = 23.4;
        const lm = makeLandmarks({
            // Right iris
            469: { x: 0.5 - irisPxDiameter / 2 / W, y: 0.5 },
            471: { x: 0.5 + irisPxDiameter / 2 / W, y: 0.5 },
            470: { x: 0.5, y: 0.5 - irisPxDiameter / 2 / H },
            472: { x: 0.5, y: 0.5 + irisPxDiameter / 2 / H },
            // Left iris (same diameter)
            474: { x: 0.4 - irisPxDiameter / 2 / W, y: 0.5 },
            476: { x: 0.4 + irisPxDiameter / 2 / W, y: 0.5 },
            475: { x: 0.4, y: 0.5 - irisPxDiameter / 2 / H },
            477: { x: 0.4, y: 0.5 + irisPxDiameter / 2 / H },
        });
        const ppm = pixelsPerMm(lm, W, H);
        expect(ppm).not.toBeNull();
        expect(ppm!).toBeCloseTo(2, 1); // irisPxDiameter(23.4) / IRIS_DIAMETER_MM(11.7) = 2
    });

    it('falls back to eye-corner distance when iris landmarks are missing', () => {
        const W = 1280;
        const H = 720;
        // Eye outer corners 90 mm apart → ppm = 90 px / 90 mm = 1
        const eyeWidthPx = 90;
        const lm = makeLandmarks({
            33:  { x: 0.5 - eyeWidthPx / 2 / W, y: 0.5 },
            263: { x: 0.5 + eyeWidthPx / 2 / W, y: 0.5 },
        });
        const ppm = pixelsPerMm(lm, W, H);
        expect(ppm).not.toBeNull();
        expect(ppm!).toBeCloseTo(1, 1);
    });
});

describe('faceWidthMm', () => {
    it('returns null when iris landmarks are absent', () => {
        expect(faceWidthMm(makeLandmarks({}), 1280, 720)).toBeNull();
    });

    it('returns face width when iris and face edges are present', () => {
        const W = 1280;
        const H = 720;
        const irisPxDiameter = 23.4; // ppm = 2 (each iris separately)
        const faceWidthPx = 280;      // → faceWidthMm = 280 / 2 = 140 mm
        // Set BOTH iris rings (469-472 = right iris, 474-477 = left iris) to the same size
        // so both contribute the same ppm and the average stays at 2.
        const lm = makeLandmarks({
            // Right iris
            469: { x: 0.5 - irisPxDiameter / 2 / W, y: 0.5 },
            471: { x: 0.5 + irisPxDiameter / 2 / W, y: 0.5 },
            470: { x: 0.5, y: 0.5 - irisPxDiameter / 2 / H },
            472: { x: 0.5, y: 0.5 + irisPxDiameter / 2 / H },
            // Left iris (same diameter placed symmetrically)
            474: { x: 0.4 - irisPxDiameter / 2 / W, y: 0.5 },
            476: { x: 0.4 + irisPxDiameter / 2 / W, y: 0.5 },
            475: { x: 0.4, y: 0.5 - irisPxDiameter / 2 / H },
            477: { x: 0.4, y: 0.5 + irisPxDiameter / 2 / H },
            // Face edges
            234: { x: 0.5 - faceWidthPx / 2 / W, y: 0.5 },
            454: { x: 0.5 + faceWidthPx / 2 / W, y: 0.5 },
        });
        const result = faceWidthMm(lm, W, H);
        expect(result).not.toBeNull();
        // 140 mm between the mesh's face edges, which sit ~10% inside the full face width.
        expect(result!).toBeCloseTo(140 * MESH_TO_FACE_WIDTH, 0);
    });
});

describe('median', () => {
    it('returns the middle value for odd-length arrays', () => {
        expect(median([3, 1, 2])).toBe(2);
    });
    it('averages the two middle values for even-length arrays', () => {
        expect(median([1, 2, 3, 4])).toBe(2.5);
    });
    it('handles a single value', () => {
        expect(median([7])).toBe(7);
    });
});

describe('recommendSize', () => {
    const sizes = [
        { code: 'XS', width: 124 },
        { code: 'S',  width: 130 },
        { code: 'M',  width: 138 },
        { code: 'L',  width: 146 },
    ];

    it('recommends the closest size', () => {
        expect(recommendSize(135, sizes)?.code).toBe('M'); // |138-135|=3 < |130-135|=5
        expect(recommendSize(127, sizes)?.code).toBe('XS'); // |124-127|=3 = |130-127|=3 → reduce keeps first (XS)
        expect(recommendSize(146, sizes)?.code).toBe('L');
    });

    it('returns null for a face that is too narrow (< 110 mm)', () => {
        expect(recommendSize(100, sizes)).toBeNull();
    });

    it('returns null for a face that is too wide (> 170 mm)', () => {
        expect(recommendSize(175, sizes)).toBeNull();
    });

    it('returns null for an empty sizes array', () => {
        expect(recommendSize(140, [])).toBeNull();
    });
});
