import { detectFace } from '@/face-fit';
import { analyseFace, measurementPoints, type FaceAnalysis } from '@/lib/faceAnalysis';

export type ScanResult =
    | { ok: true; face: FaceAnalysis; points: ReturnType<typeof measurementPoints>; width: number; height: number }
    | { ok: false; reason: 'no-face' | 'unavailable' };

/** Find and measure the face in a loaded portrait, in the browser (the photo is never uploaded). */
export async function scanPortrait(img: HTMLImageElement, mediapipeBase: string): Promise<ScanResult> {
    let fit;
    try {
        fit = await detectFace(img, mediapipeBase);
    } catch (e) {
        console.warn('Face scan unavailable:', e);
        return { ok: false, reason: 'unavailable' };
    }
    const { naturalWidth: width, naturalHeight: height } = img;
    const face = fit ? analyseFace(fit.landmarks, width, height) : null;
    if (!fit || !face) return { ok: false, reason: 'no-face' };
    return { ok: true, face, points: measurementPoints(fit.landmarks, width, height), width, height };
}
