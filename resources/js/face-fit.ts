/*
 * Face fitting for the AR try-on. Finds the pupils and face edges in a photo
 * with MediaPipe Face Landmarker (runs entirely in the browser; the photo is
 * never uploaded) and returns them in natural-image pixels.
 *
 * MediaPipe (~15 MB of WASM + model) is self-hosted under public/vendor/mediapipe
 * and only downloaded the first time a try-on photo is actually shown.
 * Used by the React try-ons and Find My Frames.
 */
import type { FaceLandmarker } from '@mediapipe/tasks-vision';

export interface Point {
    x: number;
    y: number;
}

export interface FaceFit {
    /** Pupils, `left` being the one with the smaller x in the image. */
    left: Point;
    right: Point;
    faceWidth: number;
    /** Every face-mesh landmark, x / y as fractions of the image. */
    landmarks: { x: number; y: number; z: number }[];
    /** Head pose: MediaPipe's 4 × 4 facial transformation (column-major), or null. */
    pose: number[] | null;
}

// Face-mesh landmark indices.
const IRIS_A = 468;
const IRIS_B = 473;
const EYE_CORNER_A = 33;
const EYE_CORNER_B = 263;
const CHEEK_A = 234;
const CHEEK_B = 454;

let landmarker: Promise<FaceLandmarker> | null = null;
const cache = new Map<string, FaceFit | null>(); // image src → fit (null when no face was found)

function loadLandmarker(base: string): Promise<FaceLandmarker> {
    landmarker ??= (async () => {
        const { FaceLandmarker, FilesetResolver } = await import('@mediapipe/tasks-vision');
        const fileset = await FilesetResolver.forVisionTasks(`${base}/wasm`);
        return FaceLandmarker.createFromOptions(fileset, {
            baseOptions: { modelAssetPath: `${base}/face_landmarker.task`, delegate: 'CPU' },
            runningMode: 'IMAGE',
            numFaces: 1,
            outputFacialTransformationMatrixes: true,
        });
    })();
    landmarker.catch(() => (landmarker = null)); // allow a retry after a network failure
    return landmarker;
}

export async function detectFace(img: HTMLImageElement, base: string): Promise<FaceFit | null> {
    if (cache.has(img.src)) return cache.get(img.src) ?? null;
    const model = await loadLandmarker(base);
    const w = img.naturalWidth;
    const h = img.naturalHeight;
    const result = model.detect(img);
    const points = result.faceLandmarks?.[0];
    const pose = result.facialTransformationMatrixes?.[0]?.data;
    let fit: FaceFit | null = null;
    if (points) {
        const px = (i: number): Point | null => (points[i] ? { x: points[i].x * w, y: points[i].y * h } : null);
        // Iris centres are the most precise; fall back to the eye-corner midpoint.
        const a = px(IRIS_A) ?? px(EYE_CORNER_A);
        const b = px(IRIS_B) ?? px(EYE_CORNER_B);
        const cheekA = px(CHEEK_A);
        const cheekB = px(CHEEK_B);
        if (a && b && cheekA && cheekB) {
            const [left, right] = a.x <= b.x ? [a, b] : [b, a];
            fit = {
                left,
                right,
                faceWidth: Math.hypot(cheekB.x - cheekA.x, cheekB.y - cheekA.y),
                landmarks: points.map(({ x, y, z }) => ({ x, y, z })),
                pose: pose ? Array.from(pose) : null,
            };
        }
    }
    cache.set(img.src, fit);
    return fit;
}
