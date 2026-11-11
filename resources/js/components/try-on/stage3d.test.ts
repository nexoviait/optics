import * as THREE from 'three';
import { describe, expect, it } from 'vitest';
import type { FaceFit, Point } from '@/face-fit';
import { fallbackPlacement, placeOnPhoto, poseQuaternion } from './stage3d';

const IMAGE = { naturalWidth: 1000, naturalHeight: 1250 };

/** A face with pupils at `left` / `right` (image px) and irises `iris` px across. */
function face(left: Point, right: Point, iris: number | null = 23.4): FaceFit {
    const landmarks = Array.from({ length: 478 }, () => ({ x: 0, y: 0, z: 0 }));
    const ring = (start: number, c: Point, d: number) => {
        // right, top, left, bottom edge of the iris
        [[d / 2, 0], [0, -d / 2], [-d / 2, 0], [0, d / 2]].forEach(([dx, dy], i) => {
            landmarks[start + i] = { x: (c.x + dx!) / IMAGE.naturalWidth, y: (c.y + dy!) / IMAGE.naturalHeight, z: 0 };
        });
    };
    if (iris) {
        ring(469, left, iris);
        ring(474, right, iris);
    }
    return { left, right, faceWidth: 0, landmarks: iris ? landmarks : [], pose: null };
}

describe('placeOnPhoto', () => {
    it('centres the glasses between the pupils, true to size from the iris', () => {
        // 1000 × 1250 photo in a 400 × 500 stage: shown at 0.4×.
        const p = placeOnPhoto(face({ x: 440, y: 500 }, { x: 560, y: 500 }), IMAGE, { width: 400, height: 500 }, 140);
        expect(p.x).toBeCloseTo(0, 5);
        expect(p.y).toBeCloseTo(50, 5); // 200 px down a 500 px stage = 50 px above the centre
        expect(p.pxPerMm).toBeCloseTo((23.4 / 11.7) * 0.4, 5);
        expect(p.width).toBeCloseTo(140 * 0.8, 5);
        expect(p.rotation.angleTo(new THREE.Quaternion())).toBeCloseTo(0, 5);
    });

    it('allows for the part of the photo that object-cover crops off', () => {
        const square = { naturalWidth: 1000, naturalHeight: 1000 };
        const fit = { ...face({ x: 440, y: 500 }, { x: 560, y: 500 }, null), landmarks: [] };
        // Shown at 0.5×, 50 px cropped on each side.
        const p = placeOnPhoto(fit, square, { width: 400, height: 500 }, 140);
        expect(p.x).toBeCloseTo(0, 5);
        expect(p.y).toBeCloseTo(0, 5);
    });

    it('falls back to an average pupil distance without irises', () => {
        const p = placeOnPhoto(face({ x: 440, y: 500 }, { x: 566, y: 500 }, null), IMAGE, { width: 400, height: 500 }, 140);
        expect(p.pxPerMm).toBeCloseTo((126 / 63) * 0.4, 5);
    });

    it('tilts with the eye line when there is no head pose', () => {
        // The right-hand eye is higher in the photo: the line rises to the right (counter-clockwise, y up).
        const p = placeOnPhoto(face({ x: 440, y: 520 }, { x: 560, y: 480 }), IMAGE, { width: 400, height: 500 }, 140);
        expect(new THREE.Euler().setFromQuaternion(p.rotation).z).toBeCloseTo(Math.atan2(40, 120), 5);
    });

    it('uses the head pose when MediaPipe gives one', () => {
        const turn = new THREE.Matrix4().makeRotationY(0.3).setPosition(1, 2, -40);
        const p = placeOnPhoto({ ...face({ x: 440, y: 500 }, { x: 560, y: 500 }), pose: turn.toArray() }, IMAGE, { width: 400, height: 500 }, 140);
        expect(p.rotation.angleTo(new THREE.Quaternion().setFromAxisAngle(new THREE.Vector3(0, 1, 0), 0.3))).toBeCloseTo(0, 5);
    });
});

describe('poseQuaternion', () => {
    it('ignores a malformed pose', () => {
        expect(poseQuaternion([1, 2, 3], 0.2).angleTo(new THREE.Quaternion().setFromAxisAngle(new THREE.Vector3(0, 0, 1), 0.2))).toBeCloseTo(0, 5);
    });
});

describe('fallbackPlacement', () => {
    it('puts the glasses where the eyes are in a centred portrait', () => {
        const p = fallbackPlacement({ width: 400, height: 500 }, 140);
        expect(p.x).toBe(0);
        expect(p.y).toBeCloseTo(45, 5);
        expect(p.width).toBeCloseTo(224, 5);
        expect(p.pxPerMm).toBeCloseTo(1.6, 5);
    });
});
