import * as THREE from 'three';
import { describe, expect, it } from 'vitest';
import type { FrameModelData, FrameModelLine, FrameStyle, Point3 } from '@/lib/frameModel';
import { buildTryOnGlasses } from './glasses3d';

function lens(cx: number): Point3[] {
    const p = (v: number) => Math.sign(v) * Math.abs(v) ** 0.5;
    return Array.from({ length: 40 }, (_, i): Point3 => {
        const t = (i / 40) * Math.PI * 2;
        return [cx + 26 * p(Math.cos(t)), 19 * p(Math.sin(t)), 0];
    });
}

const style: FrameStyle = {
    rim: 'acetate',
    finish: 'gradient',
    fadeTo: null,
    roughness: 0.22,
    thickness: 4.8,
    depth: 4.2,
    wire: '#c98a84',
    temple: { metal: true, color: '#c3c7cc', finish: 'solid', tips: { color: '#c98a84', finish: 'crystal' } },
    lens: { tint: null, gradient: false, mirror: false },
};

/** Rosé Gradient-like frame: 52 mm lenses, 18 mm bridge, steel temples with tips. */
function frame(): FrameModelData {
    const lines: FrameModelLine[] = [
        { part: 'rim', points: lens(-35), width: 4.8, closed: true, lens: true, color: '#c98a84' },
        { part: 'rim', points: lens(35), width: 4.8, closed: true, lens: true, color: '#c98a84' },
        { part: 'bridge', points: [[-6.6, 8.4, 0.4], [0, 11.8, 1.1], [6.6, 8.4, 0.4]], width: 4.3, closed: false, color: '#c98a84' },
    ];
    for (const s of [-1, 1]) {
        lines.push(
            { part: 'end', points: [[s * 62, 11.4, 0.2], [s * 67, 10.8, -3.5]], width: 4.2, closed: false, color: '#c98a84' },
            { part: 'temple', points: [[s * 67, 10.8, -3.5], [s * 69, 10.8, -50], [s * 71, 10.8, -95]], width: 2.4, closed: false, color: '#c3c7cc' },
            { part: 'tip', points: [[s * 71, 10.8, -89], [s * 72, 5, -118], [s * 73, -11, -133]], width: 4.2, closed: false, color: '#c98a84' },
        );
    }
    return { color: '#c98a84', tinted: false, lines, style };
}

function parts(glasses: THREE.Object3D) {
    glasses.updateMatrixWorld(true);
    const out: Record<string, THREE.Mesh<THREE.BufferGeometry, THREE.MeshStandardMaterial>[]> = {};
    glasses.traverse((o) => {
        const mesh = o as THREE.Mesh<THREE.BufferGeometry, THREE.MeshStandardMaterial>;
        if (mesh.isMesh) (out[mesh.material.name] ??= []).push(mesh);
    });
    return out;
}

describe('buildTryOnGlasses', () => {
    it('is one unit across the front, centred on the lens line', () => {
        const { frame: front = [], lens: lenses = [] } = parts(buildTryOnGlasses(frame()));
        const box = new THREE.Box3();
        front.forEach((m) => box.expandByObject(m));
        expect(box.max.x - box.min.x).toBeCloseTo(1, 3);
        expect(box.max.x + box.min.x).toBeCloseTo(0, 3);

        const glass = new THREE.Box3();
        lenses.forEach((m) => glass.expandByObject(m));
        expect((glass.max.y + glass.min.y) / 2).toBeCloseTo(0, 3);
        expect(glass.max.z).toBeCloseTo(0, 3); // lens plane at z = 0
    });

    it('fades the temples out towards the ears and hides the tips', () => {
        const { temple = [], tip = [] } = parts(buildTryOnGlasses(frame()));
        expect(tip.every((m) => !m.visible)).toBe(true);

        const geometry = temple[0]!.geometry;
        const pos = geometry.getAttribute('position');
        const alpha = geometry.getAttribute('color');
        expect(temple[0]!.material.transparent).toBe(true);
        for (let i = 0; i < pos.count; i += 40) {
            const depth = -pos.getZ(i);
            if (depth < 25) expect(alpha.getW(i)).toBe(1);
            if (depth > 75) expect(alpha.getW(i)).toBe(0);
        }
    });

    it('keeps clear lenses and crystal acetate see-through on skin', () => {
        const { lens: lenses = [], frame: front = [] } = parts(buildTryOnGlasses(frame()));
        expect(lenses[0]!.material.opacity).toBeLessThanOrEqual(0.05);
        const rim = front.find((m) => m.geometry.type === 'ExtrudeGeometry')!;
        const alpha = rim.geometry.getAttribute('color');
        const lowest = Math.min(...Array.from({ length: alpha.count }, (_, i) => alpha.getW(i)));
        expect(lowest).toBeLessThan(0.2);
    });

    it('recolours the frame for another finish, leaving steel temples alone', () => {
        const { frame: front = [], temple = [] } = parts(buildTryOnGlasses({ ...frame(), style: { ...style, finish: 'solid' } }, '#123456'));
        expect(front[0]!.material.color.getHexString()).toBe('123456');
        expect(temple[0]!.material.color.getHexString()).toBe('c3c7cc');
    });
});
