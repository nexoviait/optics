import * as THREE from 'three';
import { afterEach, describe, expect, it, vi } from 'vitest';
import type { FrameModelData, FrameModelLine, FrameStyle, Point3 } from '@/lib/frameModel';
import { buildFrameMesh, frameToGlb, offsetPolygon } from './frameGlb';

const square: [number, number][] = [[0, 0], [10, 0], [10, 10], [0, 10]];

function rect(cx: number): Point3[] {
    return [[cx - 25, -20, 0], [cx + 25, -20, 0], [cx + 25, 20, 0], [cx - 25, 20, 0]];
}

/** Frame data from before parts and style existed. */
const legacy: FrameModelData = {
    color: '#1f3a3d',
    tinted: false,
    lines: [
        { points: rect(-31), width: 4.5, closed: true, lens: true },
        { points: rect(31), width: 4.5, closed: true, lens: true },
        { points: [[-8, 9, 1], [0, 14, 2], [8, 9, 1]], width: 3.5, closed: false },
        { points: [[58, 10, -2], [60, 10, -60], [63, -12, -132]], width: 3.5, closed: false },
        { points: [[-58, 10, -2], [-60, 10, -60], [-63, -12, -132]], width: 3.5, closed: false },
    ],
};

/** Soft-rectangle lens, 52 × 38 mm, counter-clockwise like FrameModel.php. */
function lens(cx: number): Point3[] {
    const p = (v: number) => Math.sign(v) * Math.abs(v) ** 0.5;
    return Array.from({ length: 40 }, (_, i): Point3 => {
        const t = (i / 40) * Math.PI * 2;
        return [cx + 26 * p(Math.cos(t)), 19 * p(Math.sin(t)), 0];
    });
}

const baseStyle: FrameStyle = {
    rim: 'acetate',
    finish: 'solid',
    fadeTo: null,
    roughness: 0.22,
    thickness: 4.8,
    depth: 4.2,
    wire: '#c9927e',
    temple: { metal: false, color: '#c98a84', finish: 'solid', tips: null },
    lens: { tint: null, gradient: false, mirror: false },
};

function styled(style: Partial<FrameStyle>, color = '#c98a84'): FrameModelData {
    const lines: FrameModelLine[] = [
        { part: 'rim', points: lens(-35), width: 4.8, closed: true, lens: true },
        { part: 'rim', points: lens(35), width: 4.8, closed: true, lens: true },
        { part: 'brow', points: lens(35).slice(0, 21), width: 5.2, closed: false },
        { part: 'bridge', points: [[-6.6, 8.4, 0.4], [0, 11.8, 1.1], [6.6, 8.4, 0.4]], width: 4.3, closed: false },
    ];
    for (const s of [-1, 1]) {
        lines.push(
            { part: 'end', points: [[s * 62, 11.4, 0.2], [s * 67, 10.8, -3.5]], width: 4.2, closed: false },
            { part: 'temple', points: [[s * 67, 10.8, -3.5], [s * 69, 10.8, -50], [s * 71, 10.8, -95]], width: 2.4, closed: false },
            { part: 'tip', points: [[s * 71, 10.8, -89], [s * 72, 5, -118], [s * 73, -11, -133]], width: 4.2, closed: false },
        );
    }
    return { color, tinted: false, lines, style: { ...baseStyle, ...style } };
}

function meshes(group: THREE.Object3D): THREE.Mesh<THREE.BufferGeometry, THREE.MeshStandardMaterial>[] {
    const out: THREE.Mesh<THREE.BufferGeometry, THREE.MeshStandardMaterial>[] = [];
    group.traverse((o) => (o as THREE.Mesh).isMesh && out.push(o as THREE.Mesh<THREE.BufferGeometry, THREE.MeshStandardMaterial>));
    return out;
}

const named = (group: THREE.Object3D, name: string) => meshes(group).filter((m) => m.material.name === name);

/** Alpha of the highest and lowest vertex. */
function alphaTopBottom(geometry: THREE.BufferGeometry): [number, number] {
    const pos = geometry.getAttribute('position');
    const color = geometry.getAttribute('color');
    let [top, bottom] = [0, 0];
    for (let i = 0; i < pos.count; i++) {
        if (pos.getY(i) > pos.getY(top)) top = i;
        if (pos.getY(i) < pos.getY(bottom)) bottom = i;
    }
    return [color.getW(top), color.getW(bottom)];
}

afterEach(() => vi.restoreAllMocks());

describe('offsetPolygon', () => {
    it('grows a polygon outwards for either winding', () => {
        for (const pts of [square, [...square].reverse()]) {
            const out = offsetPolygon(pts, 2);
            const xs = out.map((p) => p[0]);
            expect(Math.min(...xs)).toBeCloseTo(-2, 5);
            expect(Math.max(...xs)).toBeCloseTo(12, 5);
        }
    });

    it('shrinks with a negative distance', () => {
        const xs = offsetPolygon(square, -1).map((p) => p[0]);
        expect(Math.min(...xs)).toBeCloseTo(1, 5);
    });

    it('takes a distance per vertex', () => {
        const ys = offsetPolygon(square, (p) => (p[1] > 5 ? 2 : 1)).map((p) => p[1]);
        expect(Math.max(...ys)).toBeCloseTo(12, 5);
        expect(Math.min(...ys)).toBeCloseTo(-1, 5);
    });
});

describe('buildFrameMesh', () => {
    it('still builds frame data without parts or style', () => {
        const group = buildFrameMesh(legacy);
        expect(meshes(group)).toHaveLength(7); // 2 rims + 2 lenses + bridge + 2 temples

        const size = new THREE.Box3().setFromObject(group).getSize(new THREE.Vector3());
        expect(size.x).toBeGreaterThan(0.12);
        expect(size.x).toBeLessThan(0.14);
        expect(size.z).toBeGreaterThan(0.12);
        expect(named(group, 'frame')[0]!.material.color.getHexString()).toBe('1f3a3d');
        expect(named(group, 'lens')[0]!.material.opacity).toBeLessThan(0.3);
        expect(named(buildFrameMesh({ ...legacy, tinted: true }), 'lens')[0]!.material.opacity).toBeGreaterThan(0.7);
    });

    it('fades a crystal gradient from the colour at the brow to clear underneath', () => {
        const group = buildFrameMesh(styled({ finish: 'gradient' }));
        const front = named(group, 'frame');
        expect(front[0]!.material.vertexColors).toBe(true);
        expect(front[0]!.material.transparent).toBe(true);

        const rim = front.find((m) => m.geometry.type === 'ExtrudeGeometry')!;
        expect(rim.geometry.getAttribute('color').itemSize).toBe(4);
        const [top, bottom] = alphaTopBottom(rim.geometry);
        expect(top).toBeGreaterThan(0.9);
        expect(bottom).toBeLessThan(0.35);
    });

    it('fades to a light colour, opaque, when the gradient is not crystal', () => {
        const front = named(buildFrameMesh(styled({ finish: 'gradient', fadeTo: '#f0dcda' })), 'frame');
        expect(front[0]!.material.transparent).toBe(false);
        expect(alphaTopBottom(front[0]!.geometry)).toEqual([1, 1]);
    });

    it('gives steel temples a metal finish and crystal acetate tips', () => {
        const group = buildFrameMesh(styled({ finish: 'gradient', temple: { metal: true, color: '#c3c7cc', finish: 'solid', tips: { color: '#c98a84', finish: 'crystal' } } }));
        expect(meshes(group)).toHaveLength(11); // 2 rims + 2 lenses + bridge + 2 × (end + temple + tip)

        const temples = named(group, 'temple');
        expect(temples).toHaveLength(2);
        expect(temples[0]!.material.metalness).toBe(1);
        expect(temples[0]!.material.color.getHexString()).toBe('c3c7cc');

        const tips = named(group, 'tip');
        expect(tips).toHaveLength(2);
        expect(tips[0]!.material.transparent).toBe(true);
        expect(tips[0]!.material.color.getHexString()).toBe('c98a84');
    });

    it('draws metal frames as thin eye-wires, painted when near-black', () => {
        const silver = named(buildFrameMesh(styled({ rim: 'metal', thickness: 2.2 }, '#c0c4c8')), 'frame');
        expect(silver[0]!.material.metalness).toBe(1);
        expect(silver.some((m) => m.geometry.type === 'ExtrudeGeometry')).toBe(false);

        const black = named(buildFrameMesh(styled({ rim: 'metal', thickness: 2.2 }, '#1c1c1e')), 'frame');
        expect(black[0]!.material.metalness).toBeLessThan(0.5);
    });

    it('builds a browline: acetate brow over a metal wire and bridge', () => {
        const group = buildFrameMesh(styled({ rim: 'half', thickness: 5.2 }));
        expect(named(group, 'wire')).toHaveLength(3); // 2 lower wires + bridge
        const brows = named(group, 'frame').filter((m) => m.geometry.type === 'ExtrudeGeometry');
        expect(brows).toHaveLength(2);

        const box = new THREE.Box3().setFromBufferAttribute(brows[1]!.geometry.getAttribute('position') as THREE.BufferAttribute);
        expect(box.max.y).toBeGreaterThan(19); // above the lens top
        expect(box.min.y).toBeGreaterThan(-1.5); // only the top half
    });

    it('gives rimless lenses a thickness and no rim', () => {
        const group = buildFrameMesh(styled({ rim: 'rimless', thickness: 1.5 }, '#b8bcc2'));
        const lenses = named(group, 'lens');
        expect(lenses).toHaveLength(2);
        expect(lenses.every((m) => m.geometry.type === 'ExtrudeGeometry')).toBe(true);
        expect(named(group, 'frame').some((m) => m.geometry.type === 'ExtrudeGeometry')).toBe(false);
    });

    it('tints sunglass lenses, fading the gradient ones and mirroring on request', () => {
        const gradient = named(buildFrameMesh(styled({ lens: { tint: '#3b5a3a', gradient: true, mirror: false } })), 'lens')[0]!;
        expect(gradient.material.vertexColors).toBe(true);
        const [top, bottom] = alphaTopBottom(gradient.geometry);
        expect(top).toBeGreaterThan(bottom);

        const mirror = named(buildFrameMesh(styled({ lens: { tint: '#2c5c8f', gradient: false, mirror: true } })), 'lens')[0]!;
        expect(mirror.material.metalness).toBeGreaterThan(0.5);
        expect(mirror.material.color.getHexString()).toBe('2c5c8f');
    });

    it('maps a seamless tortoiseshell pattern onto the front', () => {
        const gradient = { addColorStop: vi.fn() };
        const ctx = new Proxy({ createRadialGradient: () => gradient } as Record<string, unknown>, {
            get: (target, key) => (key in target ? target[key as string] : () => undefined),
            set: () => true,
        });
        vi.spyOn(HTMLCanvasElement.prototype, 'getContext').mockReturnValue(ctx as never);

        const group = buildFrameMesh(styled({ finish: 'tortoise' }, '#8a4a24'));
        const rim = named(group, 'frame').find((m) => m.geometry.type === 'ExtrudeGeometry')!;
        expect(rim.material.map).toBeInstanceOf(THREE.CanvasTexture);
        expect(rim.material.map!.wrapS).toBe(THREE.RepeatWrapping);
        const pos = rim.geometry.getAttribute('position');
        expect(rim.geometry.getAttribute('uv').getX(0)).toBeCloseTo(pos.getX(0) / 34, 5);
    });

    it('falls back to the plain colour when there is no 2D canvas for tortoise', () => {
        vi.spyOn(HTMLCanvasElement.prototype, 'getContext').mockReturnValue(null);
        const front = named(buildFrameMesh(styled({ finish: 'tortoise' }, '#8a4a24')), 'frame');
        expect(front[0]!.material.map).toBeNull();
        expect(front[0]!.material.color.getHexString()).toBe('8a4a24');
    });
});

describe('frameToGlb', () => {
    it('exports a binary glTF with the gradient as transparent vertex colours', async () => {
        const buffer = await frameToGlb(styled({ finish: 'gradient' }));
        const bytes = new Uint8Array(buffer);
        expect(new TextDecoder().decode(bytes.subarray(0, 4))).toBe('glTF');

        const jsonLength = new DataView(buffer).getUint32(12, true);
        const json = JSON.parse(new TextDecoder().decode(bytes.subarray(20, 20 + jsonLength))) as {
            meshes: { primitives: { attributes: Record<string, number> }[] }[];
            materials: { name: string; alphaMode?: string }[];
        };
        expect(json.meshes.some((m) => m.primitives.some((p) => 'COLOR_0' in p.attributes))).toBe(true);
        expect(json.materials.find((m) => m.name === 'frame')?.alphaMode).toBe('BLEND');
    });
});
