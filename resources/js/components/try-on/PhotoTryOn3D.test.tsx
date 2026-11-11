import { render, screen } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { PhotoTryOn3D } from './PhotoTryOn3D';

afterEach(() => vi.restoreAllMocks());

describe('PhotoTryOn3D', () => {
    it('shows the drawn 2D frame when the browser has no WebGL', () => {
        vi.spyOn(HTMLCanvasElement.prototype, 'getContext').mockReturnValue(null);
        vi.spyOn(console, 'error').mockImplementation(() => {}); // three.js reports the missing context
        const warn = vi.spyOn(console, 'warn').mockImplementation(() => {});
        render(
            <PhotoTryOn3D
                portrait="/images/face.jpg"
                glasses={{ model: null, frame: { color: '#c98a84', tinted: false, lines: [] }, flip: false, scale: 1, offsetY: 0 }}
                color="#c98a84"
                frameWidthMm={140}
                mediapipeBase="/vendor/mediapipe"
                fallback={<p>Drawn frame</p>}
            />,
        );
        expect(screen.getByText('Drawn frame')).toBeInTheDocument();
        expect(warn).toHaveBeenCalled();
    });
});
