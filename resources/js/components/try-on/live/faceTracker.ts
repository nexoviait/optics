import type { FaceLandmarker, FaceLandmarkerResult } from '@mediapipe/tasks-vision';

/**
 * MediaPipe Face Landmarker in video mode, with the head-pose matrix, for the
 * live try-on. Uses the self-hosted model under public/vendor/mediapipe (the
 * same files as the photo try-on), so no frame ever leaves the device.
 */
let tracker: Promise<FaceLandmarker> | null = null;

export function loadFaceTracker(base: string): Promise<FaceLandmarker> {
    tracker ??= (async () => {
        const { FaceLandmarker, FilesetResolver } = await import('@mediapipe/tasks-vision');
        const fileset = await FilesetResolver.forVisionTasks(`${base}/wasm`);
        const create = (delegate: 'GPU' | 'CPU') => FaceLandmarker.createFromOptions(fileset, {
            baseOptions: { modelAssetPath: `${base}/face_landmarker.task`, delegate },
            runningMode: 'VIDEO',
            numFaces: 1,
            outputFacialTransformationMatrixes: true,
        });
        // GPU is much faster; some browsers / drivers refuse it, so fall back to CPU.
        try {
            return await create('GPU');
        } catch {
            return await create('CPU');
        }
    })();
    tracker.catch(() => (tracker = null)); // allow a retry after a network failure
    return tracker;
}

export type { FaceLandmarkerResult };
