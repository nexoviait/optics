/**
 * Draws the 3D frame model built by app/Support/FrameModel.php on a canvas.
 * Shared by the Blade spin viewer (product cards, admin) and the React 360°
 * viewer on the product page, so both look identical.
 */

export type Point3 = [number, number, number];

export type FramePart = 'rim' | 'brow' | 'bridge' | 'bar' | 'end' | 'temple' | 'tip';
export type FrameFinish = 'solid' | 'crystal' | 'gradient' | 'tortoise';
export type PartFinish = Exclude<FrameFinish, 'gradient'>;

export interface FrameModelLine {
    points: Point3[];
    width: number;
    closed: boolean;
    lens?: boolean;
    part?: FramePart;
    /** Defaults to the frame colour. */
    color?: string;
}

/** What the solid 3D model needs beyond the outlines (app/Support/FrameModel.php). */
export interface FrameStyle {
    rim: 'acetate' | 'metal' | 'rimless' | 'half';
    finish: FrameFinish;
    /** Gradient: the colour at the bottom; null = clear crystal. */
    fadeTo: string | null;
    roughness: number;
    /** Rim face width in mm (the brow, for half-rim frames). */
    thickness: number;
    depth: number;
    /** Half-rim: the metal lower wire and bridge. */
    wire: string;
    temple: { metal: boolean; color: string; finish: PartFinish; tips: { color: string; finish: PartFinish } | null };
    lens: { tint: string | null; gradient: boolean; mirror: boolean };
}

export interface FrameModelData {
    color: string;
    tinted: boolean;
    lines: FrameModelLine[];
    style?: FrameStyle;
}

export const MODEL_VIEW = { width: 960, height: 640, pitch: -12, distance: 520, zoom: 4.1, pivot: 60 };
export const MAX_PITCH = 75; // degrees the model can be tilted up or down by dragging

type Projected = [number, number, number];
type Segment = { z: number; lens: Projected[] } | { z: number; a: Projected; b: Projected; width: number; rgb: [number, number, number] };

export function hexToRgb(hex: string | null | undefined): [number, number, number] {
    const v = (hex || '#0F1113').replace('#', '');
    return [0, 2, 4].map((i) => parseInt(v.slice(i, i + 2), 16) || 0) as [number, number, number];
}

/** Draw the model at `angle` (yaw) and `pitch`, in degrees (painter's algorithm, far → near). */
export function drawFrameModel(canvas: HTMLCanvasElement, model: FrameModelData, angle: number, pitch: number): void {
    const dpr = Math.min(window.devicePixelRatio || 1, 2);
    const w = canvas.clientWidth;
    const h = canvas.clientHeight;
    if (!w || !h) return;
    if (canvas.width !== Math.round(w * dpr) || canvas.height !== Math.round(h * dpr)) {
        canvas.width = Math.round(w * dpr);
        canvas.height = Math.round(h * dpr);
    }

    const ctx = canvas.getContext('2d');
    if (!ctx) return;
    ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
    ctx.clearRect(0, 0, w, h);
    const fit = Math.min(w / MODEL_VIEW.width, h / MODEL_VIEW.height);
    ctx.translate(w / 2, h / 2);
    ctx.scale(fit, fit);

    // Soft floor shadow.
    ctx.fillStyle = 'rgba(15, 17, 19, 0.07)';
    ctx.beginPath();
    ctx.ellipse(0, MODEL_VIEW.height * 0.3, 280, 17, 0, 0, Math.PI * 2);
    ctx.fill();

    const yaw = (angle * Math.PI) / 180;
    const tilt = (pitch * Math.PI) / 180;
    const [cy, sy, cp, sp] = [Math.cos(yaw), Math.sin(yaw), Math.cos(tilt), Math.sin(tilt)];
    const project = ([x, y, z]: Point3): Projected => {
        z += MODEL_VIEW.pivot; // rotate around the frame's centre of mass, not the lens plane
        [x, z] = [x * cy + z * sy, -x * sy + z * cy];
        [y, z] = [y * cp - z * sp, y * sp + z * cp];
        const s = (MODEL_VIEW.zoom * MODEL_VIEW.distance) / (MODEL_VIEW.distance - z);
        return [x * s, -y * s, z];
    };

    const segments: Segment[] = [];
    for (const line of model.lines) {
        const pts = line.points.map(project);
        if (line.lens) segments.push({ z: pts.reduce((sum, p) => sum + p[2], 0) / pts.length - 1, lens: pts });
        const rgb = hexToRgb(line.color ?? model.color);
        const count = pts.length - (line.closed ? 0 : 1);
        for (let i = 0; i < count; i++) {
            const a = pts[i]!;
            const b = pts[(i + 1) % pts.length]!;
            segments.push({ z: (a[2] + b[2]) / 2, a, b, width: line.width, rgb });
        }
    }
    segments.sort((p, q) => p.z - q.z);

    ctx.lineCap = 'round';
    ctx.lineJoin = 'round';
    for (const seg of segments) {
        if ('lens' in seg) {
            ctx.fillStyle = model.tinted ? 'rgba(30, 34, 38, 0.62)' : 'rgba(190, 205, 215, 0.14)';
            ctx.beginPath();
            seg.lens.forEach(([x, y], i) => (i ? ctx.lineTo(x, y) : ctx.moveTo(x, y)));
            ctx.closePath();
            ctx.fill();
            continue;
        }
        // Shade by depth so far parts read as further away.
        const shade = Math.max(0.55, Math.min(1.15, 0.85 + seg.z / 400));
        const tone = (c: number) => Math.round(Math.min(255, c * shade + (1 - shade) * 40));
        const [r, g, b] = seg.rgb;
        ctx.strokeStyle = `rgb(${tone(r)}, ${tone(g)}, ${tone(b)})`;
        ctx.lineWidth = seg.width;
        ctx.beginPath();
        ctx.moveTo(seg.a[0], seg.a[1]);
        ctx.lineTo(seg.b[0], seg.b[1]);
        ctx.stroke();
    }
}
