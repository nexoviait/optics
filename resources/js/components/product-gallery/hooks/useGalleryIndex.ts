import { useCallback, useEffect, useState } from 'react';

/**
 * Current slide index for a gallery of `count` items, wrapping at both ends.
 * Resets to the first slide whenever `resetKey` changes (e.g. a new colour
 * variant brings a new set of images).
 */
export function useGalleryIndex(count: number, resetKey: unknown) {
    const [index, setIndexState] = useState(0);

    useEffect(() => {
        setIndexState(0);
    }, [resetKey]);

    const setIndex = useCallback((next: number) => {
        setIndexState(count ? ((next % count) + count) % count : 0);
    }, [count]);

    const next = useCallback(() => setIndexState((i) => (count ? (i + 1) % count : 0)), [count]);
    const prev = useCallback(() => setIndexState((i) => (count ? (i - 1 + count) % count : 0)), [count]);

    return { index: Math.min(index, Math.max(0, count - 1)), setIndex, next, prev };
}
