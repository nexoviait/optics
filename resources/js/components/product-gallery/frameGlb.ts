import * as THREE from 'three';
import { GLTFExporter } from 'three/examples/jsm/exporters/GLTFExporter.js';
import { hexToRgb, type FrameFinish, type FrameModelData, type FrameModelLine, type FrameStyle, type Point3 } from '@/lib/frameModel';

/**
 * Solid 3D eyeglass frame from the product's frame data (app/Support/FrameModel.php):
 * bevelled acetate rims, metal eye-wires, a browline brow or bare rimless lenses,
 * plus bridge, end pieces, temples and tips, in the product's finish (solid,
 * crystal, gradient, tortoise or metal). Millimetre input, metre output (glTF),
 * y up, front facing +Z.
 */

type V2 = [number, number];

const BEVEL = 0.7;
/** Tortoise pattern: one texture tile per this many millimetres. */
const PATTERN_MM = 34;
const UP = new THREE.Vector3(0, 1, 0);
const DEPTH_AXIS = new THREE.Vector3(0, 0, 1);

/** Frame data from before `style` existed: acetate when the rim stroke is wide, as the old builder did. */
function styleOf(model: FrameModelData): FrameStyle {
    if (model.style) return model.style;
    const width = model.lines.find((l) => l.lens)?.width ?? 4.5;
    return {
        rim: width >= 3 ? 'acetate' : width <= 1.6 ? 'rimless' : 'metal',
        finish: 'solid',
        fadeTo: null,
        roughness: 0.4,
        thickness: width,
        depth: 3.6,
        wire: model.color,
        temple: { metal: false, color: model.color, finish: 'solid', tips: null },
        lens: { tint: model.tinted ? '#1D2226' : null, gradient: false, mirror: false },
    };
}

// ── 2D outline helpers ────────────────────────────────────────────────────

function dedupe(points: V2[]): V2[] {
    const out = points.filter((p, i) => {
        const q = points[(i + points.length - 1) % points.length]!;
        return Math.hypot(p[0] - q[0], p[1] - q[1]) > 0.01;
    });
    return out.length >= 3 ? out : points;
}

function signedArea(points: V2[]): number {
    return points.reduce((sum, [x, y], i) => {
        const [nx, ny] = points[(i + 1) % points.length]!;
        return sum + x * ny - nx * y;
    }, 0) / 2;
}

/** Move a closed polygon's edges outwards (d > 0) or inwards (d < 0), mitred at the corners; d may vary per vertex. */
export function offsetPolygon(points: V2[], d: number | ((p: V2) => number)): V2[] {
    const pts = dedupe(points);
    const dist = typeof d === 'number' ? () => d : d;
    const sign = signedArea(pts) >= 0 ? 1 : -1;
    const n = pts.length;
    const edgeNormal = (a: V2, b: V2): V2 => {
        const len = Math.hypot(b[0] - a[0], b[1] - a[1]) || 1;
        return [(sign * (b[1] - a[1])) / len, (-sign * (b[0] - a[0])) / len];
    };
    return pts.map((p, i) => {
        const n1 = edgeNormal(pts[(i + n - 1) % n]!, p);
        const n2 = edgeNormal(p, pts[(i + 1) % n]!);
        const mx = n1[0] + n2[0];
        const my = n1[1] + n2[1];
        const ml = Math.hypot(mx, my) || 1;
        // Mitre length 1/cos(half angle), capped so sharp corners don't spike.
        const scale = Math.min(2, 1 / Math.max(0.5, (mx * n1[0] + my * n1[1]) / ml));
        const k = dist(p) * scale;
        return [p[0] + (mx / ml) * k, p[1] + (my / ml) * k];
    });
}

/** Extra points along long straight edges so a smooth curve through them stays straight. */
function densify(points: V2[], step = 3): V2[] {
    return points.flatMap((p, i) => {
        const q = points[(i + 1) % points.length]!;
        const n = Math.max(1, Math.ceil(Math.hypot(q[0] - p[0], q[1] - p[1]) / step));
        return Array.from({ length: n }, (_, k): V2 => [p[0] + ((q[0] - p[0]) * k) / n, p[1] + ((q[1] - p[1]) * k) / n]);
    });
}

/** Indices of the closed outline at or above y, in order along it. */
function runAbove(points: V2[], y: number): number[] {
    const n = points.length;
    const above = points.map((p) => p[1] >= y);
    let start = 0;
    while (start < n && !(above[start] && !above[(start + n - 1) % n])) start++;
    const run: number[] = [];
    for (let i = 0; i < n && above[(start + i) % n]; i++) run.push((start + i) % n);
    return run;
}

function toShape(points: V2[]): THREE.Shape {
    return new THREE.Shape(points.map(([x, y]) => new THREE.Vector2(x, y)));
}

function extrude(shape: THREE.Shape, depth: number, bevel: number): THREE.BufferGeometry {
    const core = Math.max(0.3, depth - bevel * 2);
    const geometry = new THREE.ExtrudeGeometry(shape, { depth: core, bevelEnabled: true, bevelThickness: bevel, bevelSize: bevel, bevelSegments: 3, curveSegments: 8 });
    geometry.translate(0, 0, -core / 2);
    return geometry;
}

// ── Solids ────────────────────────────────────────────────────────────────

interface SweepOptions {
    closed?: boolean;
    /** Profile size across the path (perpendicular to it and to `ref`) and along `ref`. */
    w: number;
    h: number;
    /** The profile's h side leans towards this axis: up for bridges and temples, depth for rim wires. */
    ref?: THREE.Vector3;
    /** Profile scale at the end of the path (1 = no taper). */
    taper?: number;
}

/** Sweep a rounded-rectangle profile along a smooth path through the points, with end caps when open. */
function sweep(points: Point3[], { closed = false, w, h, ref = UP, taper = 1 }: SweepOptions): THREE.BufferGeometry {
    const curve = new THREE.CatmullRomCurve3(points.map(([x, y, z]) => new THREE.Vector3(x, y, z)), closed, 'centripetal');
    const steps = Math.max(16, Math.ceil(curve.getLength() / 1.5));
    const ring = 16;
    const profile: V2[] = Array.from({ length: ring }, (_, i) => {
        const a = (i / ring) * Math.PI * 2;
        // Superellipse: flat sides with rounded edges, like cut acetate.
        const c = Math.cos(a);
        const s = Math.sin(a);
        return [(Math.sign(c) * Math.abs(c) ** 0.5 * w) / 2, (Math.sign(s) * Math.abs(s) ** 0.5 * h) / 2];
    });

    const positions: number[] = [];
    const index: number[] = [];
    const rows = closed ? steps : steps + 1;
    let side = new THREE.Vector3(1, 0, 0);
    for (let i = 0; i < rows; i++) {
        const t = i / steps;
        const p = curve.getPointAt(t);
        const tangent = curve.getTangentAt(t);
        const next = new THREE.Vector3().crossVectors(tangent, ref);
        if (next.lengthSq() > 1e-6) side = next.normalize();
        const normal = new THREE.Vector3().crossVectors(side, tangent).normalize();
        const k = 1 + (taper - 1) * t;
        for (const [a, b] of profile) {
            positions.push(p.x + (side.x * a + normal.x * b) * k, p.y + (side.y * a + normal.y * b) * k, p.z + (side.z * a + normal.z * b) * k);
        }
    }
    for (let i = 0; i < steps; i++) {
        const r0 = i * ring;
        const r1 = ((i + 1) % rows) * ring;
        for (let j = 0; j < ring; j++) {
            const j1 = (j + 1) % ring;
            index.push(r0 + j, r1 + j, r0 + j1, r0 + j1, r1 + j, r1 + j1);
        }
    }
    if (!closed) {
        for (const [row, at, flip] of [[0, 0, true], [rows - 1, 1, false]] as const) {
            const centre = positions.length / 3;
            const p = curve.getPointAt(at);
            positions.push(p.x, p.y, p.z);
            for (let j = 0; j < ring; j++) {
                const a = row * ring + j;
                const b = row * ring + ((j + 1) % ring);
                index.push(...(flip ? [centre, b, a] : [centre, a, b]));
            }
        }
    }

    const geometry = new THREE.BufferGeometry();
    geometry.setAttribute('position', new THREE.Float32BufferAttribute(positions, 3));
    geometry.setIndex(index);
    geometry.computeVertexNormals();
    return geometry;
}

/** Acetate ring round a lens: flat front, bevelled edges, a little thicker at the brow than underneath. */
function rimRing(lens: V2[], thickness: number, depth: number): THREE.BufferGeometry {
    const ys = lens.map((p) => p[1]);
    const y0 = Math.min(...ys);
    const span = Math.max(...ys) - y0 || 1;
    const width = (p: V2) => thickness * (0.84 + 0.32 * ((p[1] - y0) / span));
    const shape = toShape(offsetPolygon(lens, (p) => width(p) - BEVEL));
    shape.holes.push(toShape(offsetPolygon(lens, BEVEL)));
    return extrude(shape, depth, BEVEL);
}

/** Browline brow: an acetate band over the top of the lens, thinning towards the sides. */
function browBand(lens: V2[], thickness: number, depth: number): THREE.BufferGeometry | null {
    const ys = lens.map((p) => p[1]);
    const top = Math.max(...ys);
    const mid = (top + Math.min(...ys)) / 2;
    const run = runAbove(lens, mid);
    if (run.length < 3) return null;
    const inner = offsetPolygon(lens, BEVEL * 0.5);
    const outer = offsetPolygon(lens, (p) => thickness * (0.5 + 0.5 * Math.max(0, (p[1] - mid) / (top - mid || 1))) - BEVEL);
    return extrude(toShape([...run.map((i) => inner[i]!), ...[...run].reverse().map((i) => outer[i]!)]), depth, BEVEL);
}

function wire(outline: V2[], across: number, deep: number): THREE.BufferGeometry {
    return sweep(densify(outline).map(([x, y]): Point3 => [x, y, 0]), { closed: true, w: across, h: deep, ref: DEPTH_AXIS });
}

// ── Finishes ──────────────────────────────────────────────────────────────

const smoothstep = (a: number, b: number, x: number) => {
    const t = Math.min(1, Math.max(0, (x - a) / (b - a)));
    return t * t * (3 - 2 * t);
};

/** Vertical colour fade in vertex colours (RGBA): `top` at the brow to `bottom` (or clear) underneath. */
function paintFade(geometry: THREE.BufferGeometry, top: THREE.Color, bottom: THREE.Color | null, y0: number, y1: number, alpha: [number, number]): void {
    const pos = geometry.getAttribute('position');
    const from = bottom ?? top.clone().lerp(new THREE.Color(0xffffff), 0.75);
    const colors = new Float32Array(pos.count * 4);
    for (let i = 0; i < pos.count; i++) {
        const t = smoothstep(0.1, 0.8, (pos.getY(i) - y0) / (y1 - y0 || 1));
        colors[i * 4] = from.r + (top.r - from.r) * t;
        colors[i * 4 + 1] = from.g + (top.g - from.g) * t;
        colors[i * 4 + 2] = from.b + (top.b - from.b) * t;
        colors[i * 4 + 3] = alpha[0] + (alpha[1] - alpha[0]) * t;
    }
    geometry.setAttribute('color', new THREE.BufferAttribute(colors, 4));
}

/** Front-on (x, y) or side-on (z, y) texture coordinates in pattern tiles. */
function planarUV(geometry: THREE.BufferGeometry, axis: 'x' | 'z'): void {
    const pos = geometry.getAttribute('position');
    const uv = new Float32Array(pos.count * 2);
    for (let i = 0; i < pos.count; i++) {
        uv[i * 2] = (axis === 'x' ? pos.getX(i) : pos.getZ(i)) / PATTERN_MM;
        uv[i * 2 + 1] = pos.getY(i) / PATTERN_MM;
    }
    geometry.setAttribute('uv', new THREE.BufferAttribute(uv, 2));
}

function mulberry32(seed: number): () => number {
    return () => {
        seed = (seed + 0x6d2b79f5) | 0;
        let t = Math.imul(seed ^ (seed >>> 15), 1 | seed);
        t = (t + Math.imul(t ^ (t >>> 7), 61 | t)) ^ t;
        return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
    };
}

/** Seamless tortoiseshell: the frame colour with amber patches, dark spots and honey flecks. Null without a 2D canvas. */
function tortoiseTexture(hex: string): THREE.CanvasTexture | null {
    if (typeof document === 'undefined') return null;
    const size = 256;
    const canvas = document.createElement('canvas');
    canvas.width = canvas.height = size;
    const ctx = canvas.getContext('2d');
    if (!ctx) return null;

    const base = new THREE.Color(hex);
    const amber = base.clone().lerp(new THREE.Color('#C9832F'), 0.6);
    const honey = amber.clone().lerp(new THREE.Color('#E8B872'), 0.5);
    const dark = base.clone().multiplyScalar(0.25);
    const rgba = (c: THREE.Color, a: number) => `rgba(${hexToRgb(`#${c.getHexString()}`).join(',')},${a})`;
    ctx.fillStyle = rgba(base, 1);
    ctx.fillRect(0, 0, size, size);

    const rand = mulberry32(parseInt(hex.replace('#', ''), 16) || 1);
    const spots = (count: number, color: THREE.Color, rMin: number, rMax: number, alpha: number) => {
        for (let i = 0; i < count; i++) {
            const [x, y, r] = [rand() * size, rand() * size, rMin + rand() * (rMax - rMin)];
            const [squash, angle] = [0.4 + rand() * 0.4, 0.35 + (rand() - 0.5) * 0.7];
            // Drawn at every wrap-around offset so the tile repeats without seams.
            for (const dx of [-size, 0, size]) {
                for (const dy of [-size, 0, size]) {
                    ctx.save();
                    ctx.translate(x + dx, y + dy);
                    ctx.rotate(angle);
                    ctx.scale(1, squash);
                    const g = ctx.createRadialGradient(0, 0, 0, 0, 0, r);
                    g.addColorStop(0, rgba(color, alpha));
                    g.addColorStop(0.55, rgba(color, alpha * 0.75));
                    g.addColorStop(1, rgba(color, 0));
                    ctx.fillStyle = g;
                    ctx.fillRect(-r, -r, r * 2, r * 2);
                    ctx.restore();
                }
            }
        }
    };
    spots(34, amber, 12, 30, 0.75);
    spots(80, dark, 4, 14, 0.9);
    spots(20, honey, 2, 5, 0.5);

    const texture = new THREE.CanvasTexture(canvas);
    texture.wrapS = texture.wrapT = THREE.RepeatWrapping;
    texture.colorSpace = THREE.SRGBColorSpace;
    texture.userData.mimeType = 'image/jpeg';
    return texture;
}

function acetate(name: string, color: string, finish: FrameFinish, roughness: number, clearFade = false): THREE.MeshStandardMaterial {
    const m = new THREE.MeshStandardMaterial({ name, color: new THREE.Color(color), roughness, metalness: 0 });
    if (finish === 'crystal') Object.assign(m, { transparent: true, opacity: 0.6, roughness: Math.min(roughness, 0.14) });
    if (finish === 'gradient') {
        m.vertexColors = true;
        m.color.set(0xffffff);
        m.roughness = Math.min(roughness, 0.14);
        m.transparent = clearFade;
    }
    if (finish === 'tortoise') {
        const map = tortoiseTexture(color);
        if (map) Object.assign(m, { map, color: new THREE.Color(0xffffff) });
        m.roughness = Math.min(roughness, 0.2);
    }
    return m;
}

function metal(name: string, color: string, roughness: number): THREE.MeshStandardMaterial {
    const c = new THREE.Color(color);
    // Near-black "metal" is painted / coated, not bare.
    const painted = 0.2126 * c.r + 0.7152 * c.g + 0.0722 * c.b < 0.03;
    return new THREE.MeshStandardMaterial({ name, color: c, metalness: painted ? 0.35 : 1, roughness: painted ? Math.max(roughness, 0.4) : roughness });
}

function lensMaterial({ tint, gradient, mirror }: FrameStyle['lens']): THREE.MeshStandardMaterial {
    if (!tint) return new THREE.MeshStandardMaterial({ name: 'lens', color: 0xffffff, transparent: true, opacity: 0.12, roughness: 0.02, metalness: 0, side: THREE.DoubleSide });
    return new THREE.MeshStandardMaterial({
        name: 'lens',
        color: gradient ? 0xffffff : tint,
        vertexColors: gradient,
        transparent: true,
        opacity: gradient ? 1 : 0.84,
        roughness: mirror ? 0.06 : 0.04,
        metalness: mirror ? 0.85 : 0,
        side: THREE.DoubleSide,
    });
}

// ── Frame ─────────────────────────────────────────────────────────────────

export function buildFrameMesh(model: FrameModelData): THREE.Group {
    const style = styleOf(model);
    const metalFront = style.rim === 'metal' || style.rim === 'rimless';
    const gradient = !metalFront && style.finish === 'gradient';
    const front = metalFront ? metal('frame', model.color, style.roughness) : acetate('frame', model.color, style.finish, style.roughness, style.fadeTo === null);
    const wireMaterial = metal('wire', style.wire, Math.min(style.roughness, 0.3));
    const { temple: t } = style;
    // Steel temples on an acetate front: satin, so they read as silver rather than mirror-dark.
    const templeMaterial = t.metal ? metal('temple', t.color, metalFront ? style.roughness : 0.34) : acetate('temple', t.color, t.finish, style.roughness);
    const tipMaterial = t.tips ? acetate('tip', t.tips.color, t.tips.finish, 0.2) : templeMaterial;
    const lens = lensMaterial(style.lens);

    const outlines = model.lines.filter((l) => l.lens).map((l) => dedupe(l.points.map(([x, y]): V2 => [x, y])));
    const ys = outlines.flat().map((p) => p[1]);
    const [y0, y1] = [Math.min(...ys), Math.max(...ys)];
    const fadeTop = new THREE.Color(model.color);
    const fadeBottom = style.fadeTo ? new THREE.Color(style.fadeTo) : null;

    const group = new THREE.Group();
    group.name = 'frame';
    const add = (geometry: THREE.BufferGeometry, material: THREE.MeshStandardMaterial, uvAxis: 'x' | 'z' = 'x', renderOrder = 0) => {
        if (material.map) planarUV(geometry, uvAxis);
        if (material === front && gradient) paintFade(geometry, fadeTop, fadeBottom, y0, y1, fadeBottom ? [1, 1] : [0.28, 0.95]);
        const mesh = new THREE.Mesh(geometry, material);
        mesh.renderOrder = renderOrder;
        group.add(mesh);
    };

    const tuck = style.rim === 'acetate' ? Math.min(1, style.thickness / 2) : 0.5;
    for (const line of model.lines) {
        if (line.points.length < 2) continue;
        if (line.lens) {
            const outline = dedupe(line.points.map(([x, y]): V2 => [x, y]));
            if (style.rim === 'acetate') add(rimRing(outline, style.thickness, style.depth), front);
            if (style.rim === 'metal') add(wire(outline, 1.6, 2.2), front);
            if (style.rim === 'half') {
                add(wire(outline, 1.1, 1.6), wireMaterial);
                const brow = browBand(outline, style.thickness, style.depth);
                if (brow) add(brow, front);
            }
            // Rimless lenses are the structure, so they get thickness; otherwise they sit tucked into the rim.
            const glass = style.rim === 'rimless' ? extrude(toShape(outline), 1.8, 0.4) : new THREE.ShapeGeometry(toShape(offsetPolygon(outline, tuck)), 24);
            if (style.lens.tint && style.lens.gradient) {
                const lensYs = outline.map((p) => p[1]);
                paintFade(glass, new THREE.Color(style.lens.tint), new THREE.Color(style.lens.tint), Math.min(...lensYs), Math.max(...lensYs), [0.4, 0.92]);
            }
            add(glass, lens, 'x', 1);
            continue;
        }
        const solid = partSolid(line, style, metalFront);
        if (!solid) continue;
        const material = { temple: templeMaterial, tip: tipMaterial, wire: wireMaterial, front }[solid.material];
        add(sweep(line.points, { closed: line.closed, ...solid.profile }), material, solid.material === 'temple' || solid.material === 'tip' ? 'z' : 'x');
    }

    group.scale.setScalar(0.001); // mm → m
    return group;
}

/** Material and cross-section of a bridge, end piece, temple or tip. */
function partSolid(line: FrameModelLine, style: FrameStyle, metalFront: boolean): { material: 'front' | 'wire' | 'temple' | 'tip'; profile: Omit<SweepOptions, 'closed'> } | null {
    const block = { w: style.depth * 0.9, h: Math.min(style.thickness, 5.2) * 0.9 };
    switch (line.part) {
        case 'brow':
            return null; // built from the rim outline
        case 'bridge':
            if (style.rim === 'half') return { material: 'wire', profile: { w: 1.6, h: 1.6 } };
            return { material: 'front', profile: metalFront ? { w: 1.6, h: 1.6 } : { w: style.depth * 0.85, h: style.thickness * 0.85 } };
        case 'bar':
            return { material: 'front', profile: { w: 1.5, h: 1.5 } };
        case 'end':
            return { material: 'front', profile: metalFront ? { w: 2, h: 2 } : block };
        case 'temple':
            return { material: 'temple', profile: style.temple.metal ? { w: 1.3, h: 2.6, taper: 0.75 } : { w: 1.9, h: line.width, taper: 0.75 } };
        case 'tip':
            return { material: 'tip', profile: { w: 2.6, h: 4.2, taper: 0.8 } };
        default:
            // Frame data without parts: bands as wide as the canvas stroke.
            return { material: 'front', profile: { w: line.width * 0.55, h: line.width } };
    }
}

/** Binary glTF of the frame, ready for <model-viewer>. */
export async function frameToGlb(model: FrameModelData): Promise<ArrayBuffer> {
    const scene = new THREE.Scene();
    scene.add(buildFrameMesh(model));
    const result = await new GLTFExporter().parseAsync(scene, { binary: true });
    return result as ArrayBuffer;
}

const cache = new Map<string, Promise<string>>();

/** Object URL of the frame's .glb, built once per shape + finish for the page's lifetime. */
export function frameGlbUrl(model: FrameModelData): Promise<string> {
    const key = JSON.stringify(model);
    let url = cache.get(key);
    if (!url) {
        url = frameToGlb(model).then((buffer) => URL.createObjectURL(new Blob([buffer], { type: 'model/gltf-binary' })));
        url.catch(() => cache.delete(key));
        cache.set(key, url);
    }
    return url;
}
