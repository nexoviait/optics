import { describe, expect, it } from 'vitest';
import { OneEuroFilter, QuaternionFilter } from './oneEuro';

describe('OneEuroFilter', () => {
    it('returns the first value unchanged', () => {
        const f = new OneEuroFilter();
        expect(f.filter(5, 0)).toBe(5);
    });

    it('smooths out a jump (does not jump all the way on the first big step)', () => {
        const f = new OneEuroFilter(1.2, 0.02);
        f.filter(0, 0);
        f.filter(0, 0.016);
        f.filter(0, 0.032);
        // Sudden jump at t=0.048
        const smoothed = f.filter(100, 0.048);
        // The One Euro filter adapts: with a large speed signal it increases
        // the cutoff, so the response is fast — but it must stay below the
        // raw value and above 0.
        expect(smoothed).toBeGreaterThan(0);
        expect(smoothed).toBeLessThan(100);
    });

    it('tracks a slow ramp (stays within 20% of the input after 2 seconds)', () => {
        const f = new OneEuroFilter(1.2, 0.02);
        let v = 0;
        for (let t = 0; t <= 2; t += 0.016) {
            v = f.filter(t, t);
        }
        // The filter has had 2 s to track a ramp; it should have converged
        // to within 20% of the target value (2.0).
        expect(v).toBeGreaterThan(1.6);
        expect(v).toBeLessThan(2.4);
    });

    it('reset clears state so next value returns exactly', () => {
        const f = new OneEuroFilter();
        f.filter(10, 0);
        f.filter(10, 0.016);
        f.reset();
        expect(f.filter(99, 1)).toBe(99);
    });
});

describe('QuaternionFilter', () => {
    it('returns a unit quaternion', () => {
        const f = new QuaternionFilter();
        const q = f.filter([0, 0, 0, 1], 0);
        const len = Math.hypot(...q);
        expect(len).toBeCloseTo(1, 5);
    });

    it('flips to stay on the same hemisphere', () => {
        const f = new QuaternionFilter();
        // Seed with q
        f.filter([0, 0, 0, 1], 0);
        // Pass the antipodal quaternion (represents the same rotation)
        const out = f.filter([0, 0, 0, -1], 0.016);
        // The w component should remain positive (same side as the seed)
        expect(out[3]).toBeGreaterThan(0);
    });

    it('reset clears state', () => {
        const f = new QuaternionFilter();
        f.filter([0, 0, 0, 1], 0);
        f.reset();
        const q = f.filter([0.5, 0, 0, 0.866], 1);
        const len = Math.hypot(...q);
        expect(len).toBeCloseTo(1, 5);
    });
});
