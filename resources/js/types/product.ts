/** Mirrors App\Enums\ImageViewType. */
export type ImageViewType = 'front' | 'angle' | 'side' | 'model_female' | 'model_male' | 'top' | 'case' | 'other';

/** Image URLs by pixel width (JSON object keys are strings, e.g. "480"). */
export type ImageSet = Record<string, string>;

export interface ImageSources {
    webp: ImageSet;
    jpg: ImageSet;
}

/** App\Http\Resources\ProductImageResource */
export interface ProductImage {
    id: number | null;
    alt: string | null;
    viewType: ImageViewType;
    width: number | null;
    height: number | null;
    isPrimary: boolean;
    sources: ImageSources;
    thumb: string;
}

export interface SpinSet {
    frameCount: number;
    frames: string[];
}

export type FrameKind = 'rect' | 'bold' | 'round' | 'panto' | 'hex' | 'aviator' | 'cat';

/** App\Models\Product::tryOnStyle() */
export interface TryOnStyle {
    image: string | null;
    kind: FrameKind;
    thin: boolean;
    rimless: boolean;
    tinted: boolean;
}

export interface SpecRow {
    label: string;
    value: string;
    href?: string;
}

export interface SpecGroup {
    title: string;
    rows: SpecRow[];
}

export interface ProductDetail {
    id: number;
    slug: string;
    name: string;
    brand: string | null;
    brandUrl: string | null;
    category: string;
    categoryUrl: string;
    ageGroup: string | null;
    styleLine: string;
    price: number;
    compareAtPrice: number | null;
    discountPercent: number;
    offerText: string | null;
    description: string | null;
    color: string | null;
    colorName: string | null;
    finishes: string[];
    stock: number;
    isLowStock: boolean;
    tryOn: TryOnStyle;
    specs: SpecGroup[];
    wishlisted: boolean;
    urls: { wishlist: string; review: string; cart: string };
}

export interface Variant {
    slug: string;
    url: string;
    label: string | null;
    swatch: string;
    current: boolean;
}

export interface FrameSize {
    code: string;
    width: number;
    lens: number;
    bridge: number;
    temple: number;
    face: string;
}

export interface PowerType {
    code: string;
    label: string;
    description: string | null;
    price: number;
    needsLens: boolean;
    needsRx: boolean;
}

export interface LensOption {
    code: string;
    label: string;
    description: string | null;
    price: number;
    badge: string | null;
    features: string[];
}

export interface Review {
    id: number;
    name: string;
    rating: number;
    title: string | null;
    body: string;
    verified: boolean;
    date: string;
    dateLabel: string;
}

export interface ReviewSummary {
    items: Review[];
    count: number;
    average: number | null;
    verified: number;
    distribution: { rating: number; count: number; percent: number }[];
    hasReviewed: boolean;
}

export interface ProductCardData {
    id: number;
    url: string;
    name: string;
    brand: string | null;
    badge: string | null;
    styleLine: string | null;
    image: string;
    price: number;
    compareAtPrice: number | null;
    discountPercent: number;
    offerText: string | null;
    lensTag: string | null;
    colorName: string | null;
    finishes: string[];
    specs: Record<string, string>;
    stock: number;
    isLowStock: boolean;
    wishlisted: boolean;
    wishlistUrl: string;
}

export interface Region {
    code: string;
    name: string;
}

export type GalleryLayout = 'grid' | 'thumbnails';

export interface PageMeta {
    title: string;
    description: string | null;
    jsonLd?: Record<string, unknown>;
}
