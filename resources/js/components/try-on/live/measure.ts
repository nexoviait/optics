/**
 * Real-world scale from a face, using the iris: its visible diameter is
 * close to 11.7 mm in almost every adult, so it works as a built-in ruler
 * (the same trick optical apps use). Everything runs on the shopper's device.
 */

export interface Landmark {
    x: number;
    y: number;
    z?: number;
}

export const IRIS_DIAMETER_MM = 11.7;

// Face-mesh landmark indices (MediaPipe Face Landmarker, with irises).
export const PUPIL_A = 468;
export const PUPIL_B = 473;
const IRIS_A = [469, 470, 471, 472]; // right, top, left, bottom edge of the first iris
const IRIS_B = [474, 475, 476, 477];
export const EYE_OUTER_A = 33;
export const EYE_OUTER_B = 263;
const FACE_EDGE_A = 234;
const FACE_EDGE_B = 454;

/** Typical distance between the outer eye corners; only used when the irises are not found. */
const EYE_CORNERS_MM = 90;

/**
 * The face mesh's outline runs inside the ears, so its widest points read ~10% short of
 * the full face width a frame is sized against (calibrated on MediaPipe's landmarks).
 */
export const MESH_TO_FACE_WIDTH = 1.1;

const dist = (a: Landmark, b: Landmark, w: number, h: number) => Math.hypot((a.x - b.x) * w, (a.y - b.y) * h);

/** Pixels per millimetre at the eyes, in a w × h image. */
export function pixelsPerMm(lm: Landmark[], w: number, h: number): number | null {
    const diameters: number[] = [];
    for (const [r, t, l, b] of [IRIS_A, IRIS_B]) {
        const pts = [lm[r!], lm[t!], lm[l!], lm[b!]];
        if (pts.some((p) => !p)) continue;
        // Width shrinks as the head turns, height as it nods; the larger is closer to the true diameter.
        diameters.push(Math.max(dist(pts[0]!, pts[2]!, w, h), dist(pts[1]!, pts[3]!, w, h)));
    }
    if (diameters.length) {
        const ppm = diameters.reduce((s, d) => s + d, 0) / diameters.length / IRIS_DIAMETER_MM;
        if (ppm > 0) return ppm;
    }
    const a = lm[EYE_OUTER_A];
    const b = lm[EYE_OUTER_B];
    return a && b ? dist(a, b, w, h) / EYE_CORNERS_MM : null;
}

/** Face width (cheekbone to cheekbone) in millimetres, or null. */
export function faceWidthMm(lm: Landmark[], w: number, h: number): number | null {
    const a = lm[FACE_EDGE_A];
    const b = lm[FACE_EDGE_B];
    const ppm = pixelsPerMm(lm, w, h);
    return a && b && ppm ? (dist(a, b, w, h) / ppm) * MESH_TO_FACE_WIDTH : null;
}

export function median(values: number[]): number {
    const sorted = [...values].sort((a, b) => a - b);
    const mid = Math.floor(sorted.length / 2);
    return sorted.length % 2 ? sorted[mid]! : (sorted[mid - 1]! + sorted[mid]!) / 2;
}

export interface SizeOption {
    code: string;
    width: number;
}

/**
 * The frame size whose total width is closest to the face width (same rule as
 * FrameSize::matchFaceWidth on the server). Null outside 110–170 mm, which
 * means the measurement went wrong rather than an unusual face.
 */
export function recommendSize(faceWidth: number, sizes: SizeOption[]): SizeOption | null {
    if (faceWidth < 110 || faceWidth > 170 || !sizes.length) return null;
    return sizes.reduce((best, s) => (Math.abs(s.width - faceWidth) < Math.abs(best.width - faceWidth) ? s : best));
}
