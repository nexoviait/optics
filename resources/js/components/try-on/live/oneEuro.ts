/**
 * One Euro filter (Casiez et al., 2012): heavy smoothing while the head is
 * still (no jitter), light smoothing when it moves fast (no lag). A fixed
 * lerp has to trade one for the other.
 */
export class OneEuroFilter {
    private x: number | null = null;
    private dx = 0;
    private lastT = 0;

    /**
     * @param minCutoff Hz; lower = steadier when still.
     * @param beta      how quickly smoothing relaxes with speed; higher = less lag.
     */
    constructor(private minCutoff = 1.2, private beta = 0.02, private dCutoff = 1) {}

    private static alpha(cutoff: number, dt: number): number {
        const tau = 1 / (2 * Math.PI * cutoff);
        return 1 / (1 + tau / dt);
    }

    /** @param t time in seconds */
    filter(value: number, t: number): number {
        if (this.x === null) {
            this.x = value;
            this.lastT = t;
            return value;
        }
        const dt = Math.max(1e-3, t - this.lastT);
        this.lastT = t;
        const rawDx = (value - this.x) / dt;
        this.dx += OneEuroFilter.alpha(this.dCutoff, dt) * (rawDx - this.dx);
        const cutoff = this.minCutoff + this.beta * Math.abs(this.dx);
        this.x += OneEuroFilter.alpha(cutoff, dt) * (value - this.x);
        return this.x;
    }

    reset(): void {
        this.x = null;
        this.dx = 0;
    }
}

/** Filters a unit quaternion component-wise (keeping the same hemisphere), then renormalises. */
export class QuaternionFilter {
    private parts = [0, 1, 2, 3].map(() => new OneEuroFilter(1.5, 0.3));
    private last: [number, number, number, number] | null = null;

    filter(q: [number, number, number, number], t: number): [number, number, number, number] {
        // q and -q are the same rotation; flip to stay near the previous one so the filter never averages through zero.
        if (this.last && q[0] * this.last[0] + q[1] * this.last[1] + q[2] * this.last[2] + q[3] * this.last[3] < 0) {
            q = [-q[0], -q[1], -q[2], -q[3]];
        }
        const out = q.map((v, i) => this.parts[i]!.filter(v, t)) as [number, number, number, number];
        const len = Math.hypot(...out) || 1;
        this.last = out.map((v) => v / len) as [number, number, number, number];
        return this.last;
    }

    reset(): void {
        this.parts.forEach((p) => p.reset());
        this.last = null;
    }
}
