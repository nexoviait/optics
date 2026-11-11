import { lazy, Suspense, useEffect, useState } from 'react';
import { Head, Link, usePage } from '@inertiajs/react';
import { BadgeCheck, Glasses, Loader2, Package, RefreshCw, Ruler, ScanFace, Share2, ShieldCheck, Star, Video } from 'lucide-react';
import type {
    FrameSize, GalleryLayout, LensOption, PageMeta, PowerType, ProductCardData, ProductDetail, ProductImage, Region, ReviewSummary, SpinSet, Variant,
} from '@/types/product';
import type { FrameModelData } from '@/lib/frameModel';
import type { SharedProps } from '@/types/shared';
import { I18nProvider } from '@/lib/i18n';
import { money, toast } from '@/lib/storefront';
import { ProductGallery } from '@/components/product-gallery/ProductGallery';
import type { ViewerMode } from '@/components/product-gallery/ImageViewerModal';
import { TryOnStage } from '@/components/try-on/TryOnStage';
import type { SizeOption } from '@/components/try-on/live/measure';
import { DeliveryChecker } from '@/components/product/DeliveryChecker';
import { LensDrawer } from '@/components/product/LensDrawer';
import { ProductCard } from '@/components/product/ProductCard';
import { Reviews } from '@/components/product/Reviews';
import { SizeGuideModal } from '@/components/product/SizeGuideModal';
import { SpecAccordion } from '@/components/product/SpecAccordion';
import { WishlistButton } from '@/components/product/WishlistButton';

const LiveTryOn = lazy(() => import('@/components/try-on/live/LiveTryOn').then((m) => ({ default: m.LiveTryOn })));
const PhotoTryOn3D = lazy(() => import('@/components/try-on/PhotoTryOn3D').then((m) => ({ default: m.PhotoTryOn3D })));

/** The portrait while the 3D try-on loads, so nothing jumps when it appears. */
function PortraitPlaceholder({ portrait }: { portrait: string }) {
    return (
        <div className="relative aspect-[4/5] overflow-hidden rounded-2xl bg-obsidian">
            <img src={portrait} alt="" className="h-full w-full object-cover" />
            <div className="absolute inset-0 grid place-items-center">
                <span className="flex items-center gap-2 rounded-full bg-obsidian/75 px-3 py-1.5 text-xs text-on-dark backdrop-blur"><Loader2 className="h-3.5 w-3.5 animate-spin" />Fitting the frame…</span>
            </div>
        </div>
    );
}

/** Matches liveTryOn prop from Shop\ProductController */
interface LiveTryOnProp {
    model: string | null;
    frame: FrameModelData;
    flip: boolean;
    scale: number;
    offsetY: number;
}

interface ShowProps {
    product: ProductDetail;
    images: ProductImage[];
    spinSet: SpinSet | null;
    model3d: string | null;
    model3dIos: string | null;
    liveTryOn: LiveTryOnProp | null;
    lifestyle: { src: string; alt: string }[];
    variants: Variant[];
    sizes: FrameSize[];
    powerTypes: PowerType[];
    lensOptions: LensOption[];
    reviews: ReviewSummary;
    related: ProductCardData[];
    regions: Region[];
    galleryLayout: GalleryLayout;
    meta: PageMeta;
}

export default function Show(props: ShowProps) {
    const shared = usePage<SharedProps>().props;

    return (
        <I18nProvider translations={shared.translations}>
            <ProductPage {...props} shared={shared} />
        </I18nProvider>
    );
}

function ProductPage({ product, images, spinSet, model3d, model3dIos, liveTryOn, lifestyle, variants, sizes, powerTypes, lensOptions, reviews, related, regions, galleryLayout, meta, shared }: ShowProps & { shared: SharedProps }) {
    // A size passed in the link (e.g. from Find My Frames), else M.
    const [size, setSize] = useState(() => {
        const wanted = new URLSearchParams(window.location.search).get('size');
        return sizes.find((s) => s.code === wanted)?.code ?? sizes.find((s) => s.code === 'M')?.code ?? sizes[0]?.code ?? '';
    });
    const [frameColor, setFrameColor] = useState(product.finishes[0] ?? '#0F1113');
    const [tryOnTab, setTryOnTab] = useState<'photo' | 'live'>('photo');
    const [sizeGuide, setSizeGuide] = useState(false);
    const [drawer, setDrawer] = useState(false);
    const sizeData = sizes.find((s) => s.code === size);

    // New product (colour variant / related frame) without a reload: reset per-product state.
    const firstFinish = product.finishes[0] ?? "#0F1113";
    useEffect(() => setFrameColor(firstFinish), [product.id, firstFinish]);

    // Flash messages from redirects (e.g. a saved review) use the storefront's toasts.
    useEffect(() => {
        if (shared.flash.success) toast(shared.flash.success);
    }, [shared.flash.success]);

    const share = async () => {
        const data = { title: product.name, url: window.location.href.split('#')[0] ?? window.location.href };
        if (navigator.share) {
            try { await navigator.share(data); } catch { /* dismissed */ }
            return;
        }
        await navigator.clipboard.writeText(data.url);
        toast('Link copied to clipboard');
    };

    // Convert FrameSize to SizeOption for the live try-on measurement.
    const sizeOptions: SizeOption[] = sizes.map((s) => ({ code: s.code, width: s.width }));
    const currentSizeMm = sizes.find((s) => s.code === size)?.width ?? 140;

    const tryOnMode: ViewerMode = {
        id: 'try-on',
        label: 'Try On',
        icon: <ScanFace className="h-3.5 w-3.5" />,
        render: () => (
            <div className="mx-auto flex max-w-md flex-col gap-4">
                {/* Tab switcher: Model photo | Live camera */}
                <div className="flex rounded-lg border border-border p-0.5 text-xs" role="tablist">
                    <button role="tab" aria-selected={tryOnTab === 'photo'} type="button" onClick={() => setTryOnTab('photo')}
                            className={`flex flex-1 items-center justify-center gap-1.5 rounded-md py-2 ${tryOnTab === 'photo' ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:text-foreground'}`}>
                        <ScanFace className="h-3.5 w-3.5" />Model photo
                    </button>
                    <button role="tab" aria-selected={tryOnTab === 'live'} type="button" onClick={() => setTryOnTab('live')}
                            className={`flex flex-1 items-center justify-center gap-1.5 rounded-md py-2 ${tryOnTab === 'live' ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:text-foreground'}`}>
                        <Video className="h-3.5 w-3.5" />Live camera
                    </button>
                </div>

                {tryOnTab === 'photo' && (
                    <>
                        {/* The 3D frame, unless the product has an uploaded try-on photo; the drawn 2D frame without WebGL. */}
                        {liveTryOn && !product.tryOn.image ? (
                            <Suspense fallback={<PortraitPlaceholder portrait={shared.urls.tryOnPortrait} />}>
                                <PhotoTryOn3D
                                    portrait={shared.urls.tryOnPortrait}
                                    glasses={liveTryOn}
                                    color={frameColor}
                                    frameWidthMm={currentSizeMm}
                                    mediapipeBase={shared.urls.mediapipe}
                                    fallback={<TryOnStage portrait={shared.urls.tryOnPortrait} frame={product.tryOn} color={frameColor} mediapipeBase={shared.urls.mediapipe} />}
                                />
                            </Suspense>
                        ) : (
                            <TryOnStage portrait={shared.urls.tryOnPortrait} frame={product.tryOn} color={frameColor} mediapipeBase={shared.urls.mediapipe} />
                        )}
                        {liveTryOn && !product.tryOn.image && sizes.length > 1 && (
                            <div className="flex items-center justify-center gap-1.5 text-xs" role="group" aria-label="Frame size on the model">
                                <span className="mr-1 text-muted-foreground">Size</span>
                                {sizes.map((s) => (
                                    <button key={s.code} type="button" onClick={() => setSize(s.code)} aria-pressed={size === s.code}
                                            className={`min-w-9 rounded-full border px-2.5 py-1 ${size === s.code ? 'border-primary bg-primary text-primary-foreground' : 'border-border hover:border-foreground'}`}>
                                        {s.code}
                                    </button>
                                ))}
                            </div>
                        )}
                        {!product.tryOn.image && product.finishes.length > 1 && (
                            <div className="flex items-center justify-center gap-2">
                                {product.finishes.map((c, i) => (
                                    <button key={c} type="button" onClick={() => setFrameColor(c)} aria-pressed={frameColor === c} aria-label={`Preview finish ${i + 1}`}
                                            className={`h-7 w-7 rounded-full border-2 ${frameColor === c ? 'border-primary' : 'border-border'}`} style={{ background: c }} />
                                ))}
                            </div>
                        )}
                        <a href={shared.urls.visionMatch} className="btn-ghost justify-center"><ScanFace className="h-4 w-4" />Try it on your own photo with VisionMatch</a>
                    </>
                )}

                {tryOnTab === 'live' && liveTryOn && (
                    <Suspense fallback={<div className="flex items-center justify-center gap-2 py-16 text-sm text-muted-foreground"><Loader2 className="h-4 w-4 animate-spin" />Loading live try-on…</div>}>
                        <LiveTryOn
                            settings={liveTryOn}
                            mediapipeBase={shared.urls.mediapipe}
                            product={{ id: product.id, name: product.name, url: window.location.href }}
                            color={frameColor}
                            frameWidthMm={currentSizeMm}
                            sizes={sizeOptions}
                            onUseSize={setSize}
                        />
                    </Suspense>
                )}

                {tryOnTab === 'live' && !liveTryOn && (
                    <p className="rounded-xl border border-border p-4 text-sm text-muted-foreground">Live camera try-on is not set up for this product yet.</p>
                )}
            </div>
        ),
    };

    const trust = [
        { icon: RefreshCw, label: `${shared.store.exchangeDays} days exchange` },
        { icon: Package, label: 'Easy returns' },
        { icon: ShieldCheck, label: `${shared.store.warrantyDays} days warranty` },
    ];

    return (
        <div className="mx-auto max-w-7xl px-5 py-10">
            <Head title={`${meta.title} | ${shared.store.name}`}>
                {meta.description && <meta head-key="description" name="description" content={meta.description} />}
                {meta.jsonLd && <script head-key="product-jsonld" type="application/ld+json" dangerouslySetInnerHTML={{ __html: JSON.stringify(meta.jsonLd).replace(/</g, '\\u003c') }} />}
            </Head>

            <nav className="text-xs text-muted-foreground" aria-label="Breadcrumb">
                <a href={shared.urls.home}>Home</a> / <a href={product.categoryUrl} className="capitalize">{product.category}</a>
                {product.brand && product.brandUrl && <> / <a href={product.brandUrl}>{product.brand}</a></>} / {product.name}
            </nav>

            <div className="mt-6 grid gap-10 lg:grid-cols-[minmax(0,1.3fr)_minmax(0,1fr)]">
                <div className="min-w-0">
                    <ProductGallery images={images} productName={product.name} spinSet={spinSet} model3d={model3d} model3dIos={model3dIos} frameModel={liveTryOn?.frame ?? null} layout={galleryLayout} extraModes={[tryOnMode]} />
                    <div className="mt-4 grid grid-cols-3 gap-2 text-center text-[0.65rem] text-muted-foreground lg:hidden">
                        {trust.map(({ icon: Icon, label }) => <div key={label} className="rounded-lg border border-border p-3"><Icon className="mx-auto mb-1 h-4 w-4" />{label}</div>)}
                    </div>
                </div>

                {/* Purchase panel */}
                <div className="lg:sticky lg:top-24 lg:self-start">
                    <div className="flex items-start justify-between gap-3">
                        <div>
                            {product.brand && product.brandUrl && <a href={product.brandUrl} className="eyebrow text-muted-foreground hover:text-foreground">{product.brand}</a>}
                            <h1 className="mt-2 text-3xl font-semibold">{product.name}{product.ageGroup && <span className="text-lg font-normal text-muted-foreground"> | {product.ageGroup}</span>}</h1>
                            <p className="mt-1 text-sm text-muted-foreground">{product.styleLine}</p>
                        </div>
                        <div className="flex shrink-0 gap-1">
                            <WishlistButton url={product.urls.wishlist} saved={product.wishlisted} productName={product.name} className="rounded-full border border-border p-2 hover:border-bronze" />
                            <button type="button" onClick={() => void share()} className="rounded-full border border-border p-2 hover:border-bronze" aria-label="Share this frame"><Share2 className="h-4 w-4" /></button>
                        </div>
                    </div>

                    {reviews.count > 0 && (
                        <a href="#reviews" className="mt-3 inline-flex items-center gap-2 rounded-full bg-secondary px-3 py-1 text-xs">
                            <span className="flex items-center gap-1 font-semibold"><Star className="h-3 w-3 fill-current text-bronze" />{reviews.average}</span>
                            <span className="text-muted-foreground">{reviews.count} rating{reviews.count === 1 ? '' : 's'}{reviews.verified > 0 && ` · ${reviews.verified} verified buyer${reviews.verified === 1 ? '' : 's'}`}</span>
                        </a>
                    )}

                    <div className="mt-4 flex flex-wrap items-baseline gap-x-3 gap-y-1">
                        <span className="font-display text-2xl font-semibold">{money(product.price)}</span>
                        {product.compareAtPrice && (
                            <>
                                <span className="text-sm text-muted-foreground line-through">{money(product.compareAtPrice)}</span>
                                <span className="rounded bg-bronze-soft px-2 py-0.5 text-xs font-semibold">{product.discountPercent}% off</span>
                            </>
                        )}
                        <span className="text-xs text-muted-foreground">USD frame price</span>
                    </div>
                    {product.offerText && <p className="mt-1 text-sm font-medium text-sage">{product.offerText}</p>}
                    {product.description && <p className="mt-4 text-sm text-muted-foreground">{product.description}</p>}

                    <section className="mt-6">
                        <div className="eyebrow text-[0.55rem] text-muted-foreground">Colour: <span className="normal-case tracking-normal text-foreground">{product.colorName}</span></div>
                        <div className="mt-2 flex flex-wrap gap-2">
                            {variants.length > 1
                                ? variants.map((v) => (
                                    // Inertia link: the new colour's gallery and details swap in without a full page load.
                                    <Link key={v.slug} href={v.url} preserveScroll title={v.label ?? undefined} aria-current={v.current ? 'true' : undefined}
                                          className={`overflow-hidden rounded-lg border-2 ${v.current ? 'border-primary' : 'border-border hover:border-bronze'}`}>
                                        <span className="flex items-center gap-2 px-2 py-1.5 text-xs"><span className="h-4 w-4 rounded-full border border-border" style={{ background: v.swatch }} />{v.label}</span>
                                    </Link>
                                ))
                                : product.finishes.map((c, i) => (
                                    <button key={c} type="button" onClick={() => setFrameColor(c)} aria-pressed={frameColor === c} aria-label={`Preview finish ${i + 1} in try-on`}
                                            className={`h-8 w-8 rounded-full border-2 ${frameColor === c ? 'border-primary' : 'border-border'}`} style={{ background: c }} />
                                ))}
                        </div>
                    </section>

                    <section className="mt-6 border-y border-border py-5">
                        <div className="flex items-center justify-between">
                            <h2 className="text-sm font-semibold">Frame size</h2>
                            <button type="button" onClick={() => setSizeGuide(true)} className="flex items-center gap-1 text-xs underline"><Ruler className="h-3.5 w-3.5" />Size Guide</button>
                        </div>
                        <div className="mt-2 flex gap-2">
                            {sizes.map((s) => (
                                <button key={s.code} type="button" onClick={() => setSize(s.code)} aria-pressed={size === s.code}
                                        className={`w-12 rounded-md border border-border py-2 text-sm ${size === s.code ? 'border-primary bg-primary text-primary-foreground' : 'bg-card'}`}>{s.code}</button>
                            ))}
                        </div>
                        {sizeData && <p className="mt-3 text-xs text-muted-foreground">Best suited for {sizeData.face} faces · <span className="font-mono">{sizeData.lens}-{sizeData.bridge}-{sizeData.temple} mm</span></p>}
                    </section>

                    <div className="mt-6">
                        {product.stock > 0 ? (
                            <>
                                <button type="button" onClick={() => setDrawer(true)} className="btn w-full py-3.5">Select Lenses & Add to Bag</button>
                                <button type="button" onClick={() => void window.addToBag?.(product.id, product.color)} className="mt-2 w-full text-center text-xs underline">or buy frame only</button>
                                {product.isLowStock && <p className="mt-2 text-xs text-muted-foreground">Only {product.stock} left in the atelier.</p>}
                            </>
                        ) : (
                            <button type="button" disabled className="btn w-full py-3.5">Sold out — restocking soon</button>
                        )}
                    </div>

                    <div className="mt-5 hidden grid-cols-3 gap-2 text-center text-[0.65rem] text-muted-foreground lg:grid">
                        {trust.map(({ icon: Icon, label }) => <div key={label} className="rounded-lg border border-border p-3"><Icon className="mx-auto mb-1 h-4 w-4" />{label}</div>)}
                    </div>

                    <DeliveryChecker url={shared.urls.deliveryEstimate} regions={regions} />
                    <SpecAccordion groups={product.specs} size={sizeData} sizeGuideUrl={shared.urls.sizeGuidePage} />
                </div>
            </div>

            <section aria-label="Optical trust and assurance" className="mt-12 border-y border-border bg-secondary/50 py-7">
                <div className="grid gap-5 px-2 sm:grid-cols-2 lg:grid-cols-4">
                    {[
                        { icon: RefreshCw, title: `${shared.store.exchangeDays}-day exchange`, detail: 'Complimentary atelier exchange' },
                        { icon: ShieldCheck, title: `${shared.store.warrantyDays}-day warranty`, detail: 'Atelier craftsmanship assurance' },
                        { icon: Glasses, title: 'Free anti-glare lenses', detail: 'Included with eligible lens configurations' },
                        { icon: BadgeCheck, title: 'Certified optician inspection', detail: 'Inspected before courier dispatch' },
                    ].map(({ icon: Icon, title, detail }) => (
                        <div key={title} className="flex items-center gap-3"><Icon className="h-6 w-6 shrink-0 text-bronze" /><div><h3 className="text-xs font-semibold">{title}</h3><p className="mt-1 text-[0.65rem] text-muted-foreground">{detail}</p></div></div>
                    ))}
                </div>
            </section>

            {lifestyle.length > 0 && (
                <section className="mt-14" aria-label="Style stories">
                    <p className="eyebrow text-muted-foreground">Style Stories</p>
                    <h2 className="mt-2 text-xl font-semibold">A little inspiration for your next look.</h2>
                    <div className="mt-5 flex snap-x snap-mandatory gap-4 overflow-x-auto pb-2">
                        {lifestyle.map((image) => (
                            <figure key={image.src} className="w-64 shrink-0 snap-start overflow-hidden rounded-2xl border border-border bg-card sm:w-72">
                                <img src={image.src} alt={image.alt} loading="lazy" decoding="async" className="aspect-[4/5] w-full object-cover" />
                            </figure>
                        ))}
                    </div>
                </section>
            )}

            <Reviews productName={product.name} reviews={reviews} storeUrl={product.urls.review} loginUrl={shared.urls.login} user={shared.auth.user} errors={shared.errors} />

            {related.length > 0 && (
                <>
                    <h2 className="mt-20 text-2xl font-medium">Similar frames you may like</h2>
                    <div className="mt-6 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">{related.map((p) => <ProductCard key={p.id} product={p} />)}</div>
                </>
            )}

            {sizeGuide && <SizeGuideModal sizes={sizes} size={size} onSize={setSize} onClose={() => setSizeGuide(false)} />}
            {drawer && (
                <LensDrawer productId={product.id} productPrice={product.price} finish={product.color} size={size} cartUrl={product.urls.cart}
                            powerTypes={powerTypes} lensOptions={lensOptions} rxSubmissionDays={shared.store.rxSubmissionDays} supportPhone={shared.store.supportPhone}
                            onClose={() => setDrawer(false)} />
            )}
        </div>
    );
}
