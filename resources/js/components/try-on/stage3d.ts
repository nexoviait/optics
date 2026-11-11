import * as THREE from 'three';
import { RoomEnvironment } from 'three/examples/jsm/environments/RoomEnvironment.js';
import type { FaceFit } from '@/face-fit';
import { buildHeadOccluder, disposeObject } from './live/glasses3d';
import { pixelsPerMm } from './live/measure';

/**
 * The 3D layer of both try-ons: a transparent WebGL canvas over the photo or
 * video, in pixel units (origin at the centre, y up). The camera is in
 * perspective from a typical selfie distance, so temples recede into the head
 * as they do in a real photo, while the lens plane (z = 0) maps 1:1 to pixels.
 */

/** Assumed camera distance; only the depth of the temples depends on it. */
export const VIEW_DISTANCE_MM = 600;
/** Average distance between the pupils, when the irises can't be measured. */
const PUPIL_DISTANCE_MM = 63;

export interface GlassesStage {
    /** Follows the head: position in px, head-pose rotation, scale = frame width in px. */
    anchor: THREE.Group;
    /** Holds the glasses; its y nudges them up or down (frame-width units). */
    holder: THREE.Group;
    /** Canvas size in CSS / video pixels. */
    resize(width: number, height: number): void;
    /** Camera distance for the face's scale (px per mm at the eyes). */
    setScale(pxPerMm: number): void;
    render(): void;
    dispose(): void;
}

export function createGlassesStage(canvas: HTMLCanvasElement, { preserveDrawingBuffer = false } = {}): GlassesStage {
    const renderer = new THREE.WebGLRenderer({ canvas, alpha: true, antialias: true, preserveDrawingBuffer });
    renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 2));
    renderer.outputColorSpace = THREE.SRGBColorSpace;
    const scene = new THREE.Scene();
    const pmrem = new THREE.PMREMGenerator(renderer);
    scene.environment = pmrem.fromScene(new RoomEnvironment(), 0.04).texture;
    scene.add(new THREE.HemisphereLight(0xffffff, 0x444444, 1.2));
    const sun = new THREE.DirectionalLight(0xffffff, 1.2);
    sun.position.set(0, 1, 2);
    scene.add(sun);

    const camera = new THREE.PerspectiveCamera(30, 1, 1, 10000);
    const anchor = new THREE.Group();
    const holder = new THREE.Group();
    anchor.add(holder, buildHeadOccluder());
    anchor.visible = false;
    scene.add(anchor);

    let size = { width: 0, height: 0 };
    let scale = 0;
    const updateCamera = () => {
        if (!size.height || !scale) return;
        const distance = VIEW_DISTANCE_MM * scale;
        camera.aspect = size.width / size.height;
        camera.fov = THREE.MathUtils.radToDeg(2 * Math.atan(size.height / 2 / distance));
        camera.position.set(0, 0, distance);
        camera.near = distance / 50;
        camera.far = distance * 20;
        camera.updateProjectionMatrix();
    };

    return {
        anchor,
        holder,
        resize(width, height) {
            if (width === size.width && height === size.height) return;
            size = { width, height };
            renderer.setSize(width, height, false);
            updateCamera();
        },
        setScale(pxPerMm) {
            if (Math.abs(pxPerMm - scale) < 1e-4) return;
            scale = pxPerMm;
            updateCamera();
        },
        render: () => renderer.render(scene, camera),
        dispose() {
            disposeObject(scene);
            pmrem.dispose();
            renderer.dispose();
        },
    };
}

/** Head rotation from MediaPipe's facial transformation; without one, just the tilt of the eye line. */
export function poseQuaternion(pose: ArrayLike<number> | null, roll: number): THREE.Quaternion {
    const q = new THREE.Quaternion();
    if (pose && pose.length === 16) {
        new THREE.Matrix4().fromArray(Array.from(pose)).decompose(new THREE.Vector3(), q, new THREE.Vector3());
        return q;
    }
    return q.setFromAxisAngle(new THREE.Vector3(0, 0, 1), roll);
}

export interface Placement {
    /** Centre between the pupils, px from the stage centre, y up. */
    x: number;
    y: number;
    /** Frame width in px. */
    width: number;
    pxPerMm: number;
    rotation: THREE.Quaternion;
}

/** Where the glasses go on a photo shown object-cover in a stage: between the pupils, true to size, in the head's pose. */
export function placeOnPhoto(fit: FaceFit, image: { naturalWidth: number; naturalHeight: number }, stage: { width: number; height: number }, frameWidthMm: number): Placement {
    const { naturalWidth: iw, naturalHeight: ih } = image;
    const cover = Math.max(stage.width / iw, stage.height / ih);
    const ox = (stage.width - iw * cover) / 2;
    const oy = (stage.height - ih * cover) / 2;
    const dx = fit.right.x - fit.left.x;
    const dy = fit.right.y - fit.left.y;
    // The iris is a built-in ruler; failing that, assume average-width pupils.
    const ppm = (pixelsPerMm(fit.landmarks, iw, ih) ?? Math.hypot(dx, dy) / PUPIL_DISTANCE_MM) * cover;
    const mx = ((fit.left.x + fit.right.x) / 2) * cover + ox;
    const my = ((fit.left.y + fit.right.y) / 2) * cover + oy;
    return {
        x: mx - stage.width / 2,
        y: stage.height / 2 - my,
        width: frameWidthMm * ppm,
        pxPerMm: ppm,
        rotation: poseQuaternion(fit.pose, Math.atan2(-dy, dx)),
    };
}

/** No face found: roughly where the eyes are in a centred portrait, facing the camera. */
export function fallbackPlacement(stage: { width: number; height: number }, frameWidthMm: number): Placement {
    const width = stage.width * 0.56;
    return { x: 0, y: stage.height * 0.09, width, pxPerMm: width / frameWidthMm, rotation: new THREE.Quaternion() };
}
