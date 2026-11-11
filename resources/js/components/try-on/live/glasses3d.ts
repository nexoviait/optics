import * as THREE from 'three';
import { GLTFLoader } from 'three/examples/jsm/loaders/GLTFLoader.js';
import type { FrameModelData } from '@/lib/frameModel';
import { buildFrameMesh } from '@/components/product-gallery/frameGlb';

/**
 * Glasses for the try-ons, normalised so that 1 unit = the frame's width, the
 * lens centre line sits at the origin, the lens plane at z = 0 and the front
 * faces +Z (towards the camera). The caller scales them to the width in pixels.
 */

/** Opticians fit frames with the pupils a little above the lens centre line. */
export const PUPIL_ABOVE_CENTRE_MM = 2.5;

/** Temples are solid to this far behind the hinge, then fade out by FADE_END (mm), where hair and ears would hide them. */
const FADE_START_MM = 25;
const FADE_END_MM = 75;

/** The frame in another finish colour: every part drawn in the frame colour follows it. */
function recolour(model: FrameModelData, color: string): FrameModelData {
    const from = model.color.toLowerCase();
    if (from === color.toLowerCase()) return model;
    const swap = (c: string) => (c.toLowerCase() === from ? color : c);
    const style = model.style;
    return {
        ...model,
        color,
        lines: model.lines.map((l) => (l.color ? { ...l, color: swap(l.color) } : l)),
        style: style && {
            ...style,
            wire: swap(style.wire),
            temple: { ...style.temple, color: swap(style.temple.color), tips: style.temple.tips && { ...style.temple.tips, color: swap(style.temple.tips.color) } },
        },
    };
}

/**
 * On a face, see-through parts must be clearer than on the 360° view's white stage:
 * clear lenses are just a sheen, and crystal acetate (vertex alpha < 1) lets the skin through.
 */
function clearForSkin(mesh: THREE.Mesh): void {
    const material = mesh.material as THREE.MeshStandardMaterial;
    if (material.name === 'lens' && !material.vertexColors && material.opacity < 0.2) material.opacity = 0.05;
    const colors = mesh.geometry.getAttribute('color');
    if (material.name !== 'frame' || colors?.itemSize !== 4) return;
    for (let i = 0; i < colors.count; i++) colors.setW(i, colors.getW(i) ** 1.6);
}

/** Fade a temple out with depth (vertex alpha), keeping its colour. */
function fadeTemple(mesh: THREE.Mesh): void {
    const pos = mesh.geometry.getAttribute('position');
    const colors = new Float32Array(pos.count * 4);
    for (let i = 0; i < pos.count; i++) {
        const t = THREE.MathUtils.smoothstep(-pos.getZ(i), FADE_START_MM, FADE_END_MM);
        colors.set([1, 1, 1, 1 - t], i * 4);
    }
    mesh.geometry.setAttribute('color', new THREE.BufferAttribute(colors, 4));
    const material = mesh.material as THREE.MeshStandardMaterial;
    material.vertexColors = true;
    material.transparent = true;
    material.needsUpdate = true;
}

/**
 * The product's solid frame (the same model as the 360° view) for the try-ons.
 * Width is measured across the front, so the frame is true to size on the face.
 */
export function buildTryOnGlasses(model: FrameModelData, color?: string): THREE.Group {
    const frame = buildFrameMesh(color ? recolour(model, color) : model);
    frame.updateMatrixWorld(true);
    const front = new THREE.Box3();
    frame.traverse((o) => {
        const mesh = o as THREE.Mesh;
        if (!mesh.isMesh) return;
        const part = (mesh.material as THREE.Material).name;
        if (part === 'tip') mesh.visible = false; // behind the ear
        else if (part === 'temple') fadeTemple(mesh);
        else {
            clearForSkin(mesh);
            front.expandByObject(mesh);
        }
    });
    const wrapper = new THREE.Group();
    wrapper.add(frame);
    wrapper.scale.setScalar(1 / (front.max.x - front.min.x || 1));
    return wrapper;
}

/** A product's .glb, centred on its front and scaled to unit width. `flip` turns a model that faces backwards. */
export async function loadModelGlasses(url: string, flip: boolean): Promise<THREE.Object3D> {
    const gltf = await new GLTFLoader().loadAsync(url);
    const model = gltf.scene;
    if (flip) model.rotation.y += Math.PI;
    const wrapper = new THREE.Group();
    wrapper.add(model);
    wrapper.updateMatrixWorld(true);
    const box = new THREE.Box3().setFromObject(wrapper);
    const size = box.getSize(new THREE.Vector3());
    const center = box.getCenter(new THREE.Vector3());
    const s = 1 / (size.x || 1);
    wrapper.scale.setScalar(s);
    // Front face (max Z) at the origin, horizontally and vertically centred.
    wrapper.position.set(-center.x * s, -center.y * s, -box.max.z * s);
    const outer = new THREE.Group();
    outer.add(wrapper);
    return outer;
}

/**
 * Invisible head that hides the far temple when the head turns (writes depth,
 * not colour). In frame-width units, its front just behind the lenses.
 */
export function buildHeadOccluder(): THREE.Mesh {
    const head = new THREE.Mesh(new THREE.SphereGeometry(0.5, 32, 24), new THREE.MeshBasicMaterial({ colorWrite: false }));
    head.scale.set(0.9, 1.15, 1.25);
    head.position.set(0, -0.1, -0.68);
    head.renderOrder = -1;
    return head;
}

export function disposeObject(object: THREE.Object3D): void {
    object.traverse((child) => {
        const mesh = child as THREE.Mesh;
        mesh.geometry?.dispose();
        const materials = Array.isArray(mesh.material) ? mesh.material : mesh.material ? [mesh.material] : [];
        materials.forEach((m) => {
            Object.values(m).forEach((v) => (v instanceof THREE.Texture ? v.dispose() : null));
            m.dispose();
        });
    });
}
