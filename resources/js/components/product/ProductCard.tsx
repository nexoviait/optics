import { Link } from '@inertiajs/react';
import { ShoppingBag } from 'lucide-react';
import type { ProductCardData } from '@/types/product';
import { money } from '@/lib/storefront';
import { WishlistButton } from './WishlistButton';

/** Related-frame card (React twin of x-site.product-card). Links stay inside Inertia. */
export function ProductCard({ product }: { product: ProductCardData }) {
    return (
        <div className="group flex flex-col rounded-2xl border border-border bg-card p-3 transition hover:-translate-y-1 hover:shadow-[0_20px_40px_-24px_var(--obsidian)]">
            <div className="flex items-center justify-between gap-2">
                {product.badge && <span className="eyebrow truncate rounded-full bg-sage-soft px-2 py-1 text-[0.55rem]">{product.badge}</span>}
                <div className="ml-auto flex items-center gap-2">
                    {product.stock < 1 ? <span className="eyebrow text-[0.55rem] text-destructive">Sold out</span>
                        : product.isLowStock ? <span className="eyebrow text-[0.55rem] text-muted-foreground">Only {product.stock} left</span> : null}
                    <WishlistButton url={product.wishlistUrl} saved={product.wishlisted} productName={product.name} className="rounded-full p-1 hover:bg-secondary" />
                </div>
            </div>
            <Link href={product.url} className="relative mt-3 block overflow-hidden rounded-xl bg-white" aria-label={product.name}>
                {product.discountPercent > 0 && <span className="pointer-events-none absolute right-2 top-2 z-10 rounded bg-bronze px-1.5 py-0.5 text-[0.6rem] font-semibold text-obsidian">-{product.discountPercent}%</span>}
                <img src={product.image} alt={`${product.name} — ${product.colorName ?? ''}`} loading="lazy" decoding="async" className="aspect-[4/3] w-full object-contain p-2 transition duration-500 group-hover:scale-105" />
            </Link>
            <div className="mt-3 flex items-center justify-between">
                <span className="eyebrow text-[0.55rem] text-muted-foreground">{product.colorName}</span>
                <div className="flex gap-1">{product.finishes.map((c) => <span key={c} className="h-2.5 w-2.5 rounded-full border border-border" style={{ background: c }} />)}</div>
            </div>
            <Link href={product.url} className="mt-3">
                {product.brand && <p className="eyebrow text-[0.55rem] text-muted-foreground">{product.brand}</p>}
                <h3 className="text-sm font-semibold">{product.name}</h3>
                <p className="text-xs text-muted-foreground">{product.styleLine}</p>
            </Link>
            <dl className="mt-3 space-y-1 border-t border-border pt-3 text-[0.7rem]">
                {Object.entries(product.specs).map(([k, v]) => <div key={k} className="flex justify-between gap-2"><dt className="text-muted-foreground">{k}</dt><dd className="text-right">{v}</dd></div>)}
            </dl>
            <div className="mt-auto flex items-end justify-between gap-2 pt-4">
                <div>
                    {product.lensTag && <p className="eyebrow text-[0.5rem] text-muted-foreground">{product.lensTag}</p>}
                    <p className="font-display text-lg font-semibold">{money(product.price)}{product.compareAtPrice && <span className="ml-1.5 text-xs font-normal text-muted-foreground line-through">{money(product.compareAtPrice)}</span>}</p>
                    {product.offerText && <p className="text-[0.6rem] text-muted-foreground">{product.offerText}</p>}
                </div>
                <button type="button" disabled={product.stock < 1} onClick={() => void window.addToBag?.(product.id)} className="btn btn-sm"><ShoppingBag className="h-3.5 w-3.5" />Add to Bag</button>
            </div>
        </div>
    );
}
