import { cleanup, fireEvent, render, screen } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import { CompareLooks, loadLooks, saveLook } from './CompareLooks';
import type { Look } from './CompareLooks';

const KEY = 'optics_compare_looks';

const makeLook = (id: string): Look => ({
    id,
    productId: 1,
    name: `Frame ${id}`,
    url: `/frames/${id}`,
    color: '#000',
    image: `data:image/png;base64,abc${id}`,
});

afterEach(() => {
    cleanup();
    sessionStorage.removeItem(KEY);
});

describe('saveLook / loadLooks', () => {
    it('saves and loads looks', () => {
        saveLook(makeLook('a'));
        expect(loadLooks()).toHaveLength(1);
        expect(loadLooks()[0]!.id).toBe('a');
    });

    it('caps at 4 looks, newest first', () => {
        ['a', 'b', 'c', 'd', 'e'].forEach((id) => saveLook(makeLook(id)));
        const looks = loadLooks();
        expect(looks).toHaveLength(4);
        expect(looks[0]!.id).toBe('e');
        expect(looks[3]!.id).toBe('b'); // 'a' was dropped
    });

    it('deduplicates by id', () => {
        saveLook(makeLook('a'));
        saveLook(makeLook('a')); // same id again
        expect(loadLooks()).toHaveLength(1);
    });
});

describe('CompareLooks component', () => {
    beforeEach(() => sessionStorage.removeItem(KEY));

    it('renders nothing when there are no looks', () => {
        const { container } = render(<CompareLooks />);
        expect(container.firstChild).toBeNull();
    });

    it('shows saved looks after refreshKey changes', () => {
        saveLook(makeLook('x'));
        const { rerender } = render(<CompareLooks refreshKey={0} />);
        // Initially loaded on mount
        expect(screen.getByAltText(/Frame x/i)).toBeTruthy();
        // Add another and re-render with a new refreshKey
        saveLook(makeLook('y'));
        rerender(<CompareLooks refreshKey={1} />);
        expect(screen.getByAltText(/Frame y/i)).toBeTruthy();
    });

    it('removes a look when its X button is clicked', () => {
        saveLook(makeLook('z'));
        render(<CompareLooks refreshKey={0} />);
        const btn = screen.getByRole('button', { name: /remove frame z/i });
        fireEvent.click(btn);
        expect(screen.queryByAltText(/Frame z/i)).toBeNull();
    });

    it('clears all looks', () => {
        saveLook(makeLook('p'));
        saveLook(makeLook('q'));
        render(<CompareLooks refreshKey={0} />);
        const clearBtn = screen.getByRole('button', { name: /clear all/i });
        fireEvent.click(clearBtn);
        expect(screen.queryByAltText(/Frame p/i)).toBeNull();
        expect(loadLooks()).toHaveLength(0);
    });
});
