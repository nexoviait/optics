import { faceWidthMm, pixelsPerMm, PUPIL_A, PUPIL_B, type Landmark } from '@/components/try-on/live/measure';

/**
 * Face measurements and an estimated face shape from MediaPipe's face mesh,
 * for Find My Frames. Runs on the shopper's device; nothing is uploaded.
 */

export type FaceShape = 'oval' | 'round' | 'square' | 'heart' | 'oblong' | 'diamond';

export interface FaceAnalysis {
    shape: FaceShape;
    /** Pupillary distance, mm. */
    pd: number;
    /** Face width at the cheekbones, mm. */
    width: number;
    /** Forehead to chin, mm. */
    length: number;
    /** Measured with the iris as a ruler; false means estimated from the eye corners. */
    iris: boolean;
    /** Head turned away from the camera, so widths read short. */
    turned: boolean;
    /** The face is small in the photo, so measurements are rough. */
    small: boolean;
}

/** Proportions relative to the cheek-to-cheek width of the mesh. */
export interface ShapeRatios {
    /** Forehead-to-chin length ÷ width. */
    ratio: number;
    /** Jaw (near the angle) ÷ width. */
    jaw: number;
    /** Temples ÷ width. */
    forehead: number;
}

// Face-mesh landmark indices.
const FOREHEAD_TOP = 10;
const CHIN = 152;
const CHEEK_A = 234;
const CHEEK_B = 454;
const JAW_A = 172;
const JAW_B = 397;
const TEMPLE_A = 21;
const TEMPLE_B = 251;
const NOSE_TIP = 1;
const IRISES = [469, 470, 471, 472, 474, 475, 476, 477];

/** Pupils closer than this (px) leave too few pixels to measure well. */
const MIN_PUPIL_PX = 40;
/** Nose off-centre between the cheeks by more than this share means a turned head. */
const MAX_ASYMMETRY = 0.15;

/**
 * Face shape from proportions. Thresholds are calibrated on MediaPipe's mesh (a typical
 * oval face reads ratio ≈ 1.2, jaw ≈ 0.8, forehead ≈ 0.92); it is an estimate the shopper
 * can correct, not a diagnosis.
 */
export function classifyShape({ ratio, jaw, forehead }: ShapeRatios): FaceShape {
    if (ratio >= 1.33) return 'oblong';
    if (ratio < 1.12) return jaw >= 0.82 ? 'square' : 'round';
    if (forehead - jaw >= 0.17) return 'heart';
    if (forehead < 0.86 && jaw < 0.76) return 'diamond';
    if (jaw >= 0.86) return 'square';
    return 'oval';
}

/** Measurements of the face in a w × h image, or null when key landmarks are missing. */
export function analyseFace(lm: Landmark[], w: number, h: number): FaceAnalysis | null {
    const needed = [FOREHEAD_TOP, CHIN, CHEEK_A, CHEEK_B, JAW_A, JAW_B, TEMPLE_A, TEMPLE_B, NOSE_TIP, PUPIL_A, PUPIL_B];
    if (needed.some((i) => !lm[i])) return null;
    const ppm = pixelsPerMm(lm, w, h);
    const width = faceWidthMm(lm, w, h);
    if (!ppm || !width) return null;

    const d = (a: number, b: number) => Math.hypot((lm[a]!.x - lm[b]!.x) * w, (lm[a]!.y - lm[b]!.y) * h);
    const cheeks = d(CHEEK_A, CHEEK_B);
    const toA = d(NOSE_TIP, CHEEK_A);
    const toB = d(NOSE_TIP, CHEEK_B);

    return {
        shape: classifyShape({ ratio: d(FOREHEAD_TOP, CHIN) / cheeks, jaw: d(JAW_A, JAW_B) / cheeks, forehead: d(TEMPLE_A, TEMPLE_B) / cheeks }),
        pd: d(PUPIL_A, PUPIL_B) / ppm,
        width,
        length: d(FOREHEAD_TOP, CHIN) / ppm,
        iris: IRISES.every((i) => lm[i]),
        turned: Math.abs(toA - toB) / (toA + toB) > MAX_ASYMMETRY,
        small: d(PUPIL_A, PUPIL_B) < MIN_PUPIL_PX,
    };
}

/** Landmarks drawn over the photo to show what was measured (image pixels). */
export function measurementPoints(lm: Landmark[], w: number, h: number) {
    const p = (i: number) => ({ x: lm[i]!.x * w, y: lm[i]!.y * h });
    return { pupils: [p(PUPIL_A), p(PUPIL_B)], cheeks: [p(CHEEK_A), p(CHEEK_B)], faceLength: [p(FOREHEAD_TOP), p(CHIN)] };
}
