import { useEffect } from 'react';

const warmed = new Set<string>();

/** Start downloading images in the background (e.g. the viewer's next and previous slides). */
export function useImagePreload(urls: (string | undefined)[]): void {
    const key = urls.filter(Boolean).join('|');

    useEffect(() => {
        for (const url of key.split('|')) {
            if (!url || warmed.has(url)) continue;
            warmed.add(url);
            const img = new Image();
            img.decoding = 'async';
            img.src = url;
        }
    }, [key]);
}
