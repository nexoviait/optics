import { useState } from 'react';
import type { ImageSet, ProductImage } from '@/types/product';

/** Matches ProductImageResource::SIZES (used for the <head> preload of the first image). */
export const GALLERY_SIZES = '(min-width: 1024px) 720px, 100vw';

export function srcSet(set: ImageSet): string {
    return Object.entries(set)
        .sort(([a], [b]) => Number(a) - Number(b))
        .map(([width, url]) => `${url} ${width}w`)
        .join(', ');
}

/** Largest available URL, preferring JPEG (universally decodable) for <img src>. */
export function largestSrc(image: ProductImage, format: keyof ProductImage['sources'] = 'jpg'): string {
    const entries = Object.entries(image.sources[format]).sort(([a], [b]) => Number(b) - Number(a));
    return entries[0]?.[1] ?? Object.values(image.sources.jpg)[0] ?? image.thumb;
}

interface GalleryImageProps {
    image: ProductImage;
    alt: string;
    /** First image: eager + high fetch priority (it is the LCP element). */
    priority?: boolean;
    sizes?: string;
    className?: string;
    draggable?: boolean;
    /** Fill the parent's height (viewer) instead of reserving a 1:1 box (gallery). */
    fill?: boolean;
}

/**
 * Responsive product photo. Space is reserved with a 1:1 box (no layout shift)
 * and a soft pulse shows until the bytes arrive.
 */
export function GalleryImage({ image, alt, priority = false, sizes = GALLERY_SIZES, className = '', draggable = false, fill = false }: GalleryImageProps) {
    const [loaded, setLoaded] = useState(false);
    const webp = srcSet(image.sources.webp);

    return (
        <span className={`relative block w-full overflow-hidden bg-white ${fill ? 'h-full' : 'aspect-square'} ${className}`}>
            {!loaded && <span aria-hidden="true" className="absolute inset-0 animate-pulse bg-secondary/60" />}
            <picture>
                {webp && <source type="image/webp" srcSet={webp} sizes={sizes} />}
                <img
                    src={largestSrc(image)}
                    srcSet={srcSet(image.sources.jpg) || undefined}
                    sizes={sizes}
                    alt={alt}
                    width={image.width ?? 1080}
                    height={image.height ?? 1080}
                    loading={priority ? 'eager' : 'lazy'}
                    fetchPriority={priority ? 'high' : 'auto'}
                    decoding="async"
                    draggable={draggable}
                    onLoad={() => setLoaded(true)}
                    onError={() => setLoaded(true)}
                    className={`h-full w-full object-contain transition-opacity duration-300 ${loaded ? 'opacity-100' : 'opacity-0'}`}
                />
            </picture>
        </span>
    );
}
