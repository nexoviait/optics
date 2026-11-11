import { useState, type FormEvent } from 'react';
import { useForm } from '@inertiajs/react';
import { BadgeCheck, PenLine, Star } from 'lucide-react';
import type { ReviewSummary } from '@/types/product';
import { Modal } from './Modal';

interface ReviewsProps {
    productName: string;
    reviews: ReviewSummary;
    storeUrl: string;
    loginUrl: string;
    user: { name: string } | null;
    errors: Record<string, string>;
}

const plural = (n: number, word: string) => `${n} ${word}${n === 1 ? '' : 's'}`;

export function Reviews({ productName, reviews, storeUrl, loginUrl, user, errors }: ReviewsProps) {
    const hasErrors = ['reviewer_name', 'rating', 'body', 'review'].some((k) => errors[k]);
    const [open, setOpen] = useState(hasErrors);

    return (
        <section id="reviews" className="mt-12 scroll-mt-24" aria-label="Customer ratings and reviews">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <div><p className="eyebrow text-muted-foreground">The collector perspective</p><h2 className="mt-2 text-xl font-semibold">Customer ratings & reviews</h2></div>
                {!reviews.hasReviewed && <button type="button" onClick={() => setOpen(true)} className="btn-ghost"><PenLine className="h-4 w-4" />Write a Review</button>}
            </div>
            <div className="mt-6 grid gap-8 lg:grid-cols-[280px_1fr]">
                <div>
                    <div className="flex items-baseline gap-2"><span className="font-display text-4xl font-semibold">{reviews.average ?? '—'}</span><span className="text-sm text-muted-foreground">/ 5 · {plural(reviews.count, 'review')}</span></div>
                    <div className="mt-4 space-y-2">
                        {reviews.distribution.map((row) => (
                            <div key={row.rating} className="flex items-center gap-3 text-xs">
                                <span className="flex w-7 items-center gap-1">{row.rating}<Star className="h-3 w-3" /></span>
                                <progress aria-label={`${row.rating}-star percentage`} value={row.percent} max={100} className="rating-bar h-1.5 min-w-0 flex-1" />
                                <span className="w-9 text-right text-muted-foreground" title={`${row.percent}%`}>{row.count}</span>
                            </div>
                        ))}
                    </div>
                    <p className="mt-4 text-xs text-muted-foreground">Verified badges appear only for customers with a delivered order for this frame.</p>
                </div>
                <div className="divide-y divide-border">
                    {reviews.items.length === 0 && (
                        <div className="border-y border-border py-8"><h3 className="text-sm font-medium">Be the first to review {productName}</h3><p className="mt-2 text-xs text-muted-foreground">No customer reviews yet.</p></div>
                    )}
                    {reviews.items.map((review) => (
                        <article key={review.id} className="py-5 first:pt-0">
                            <div className="flex flex-wrap items-center justify-between gap-2">
                                <div className="flex flex-wrap items-center gap-3">
                                    <h3 className="text-sm font-semibold">{review.name}</h3>
                                    {review.verified && <span className="flex items-center gap-1 text-xs text-muted-foreground"><BadgeCheck className="h-3 w-3" />Verified buyer</span>}
                                </div>
                                <time className="text-xs text-muted-foreground" dateTime={review.date}>{review.dateLabel}</time>
                            </div>
                            <div className="mt-2 flex gap-1 text-bronze" aria-label={`${review.rating} out of 5 stars`} role="img">
                                {[1, 2, 3, 4, 5].map((s) => <Star key={s} className={`h-3 w-3 ${s <= review.rating ? 'fill-current' : 'text-muted-foreground'}`} />)}
                            </div>
                            {review.title && <h4 className="mt-3 text-sm font-semibold">{review.title}</h4>}
                            <p className={`break-words text-sm leading-relaxed text-muted-foreground ${review.title ? 'mt-1' : 'mt-3'}`}>{review.body}</p>
                        </article>
                    ))}
                </div>
            </div>

            {open && (
                <Modal title={`Review ${productName}`} subtitle="Your experience, in your own words." onClose={() => setOpen(false)}>
                    {user ? <ReviewForm storeUrl={storeUrl} defaultName={user.name} onDone={() => setOpen(false)} /> : (
                        <div className="mt-4 space-y-4"><p className="text-sm text-muted-foreground">Sign in to submit a review.</p><a href={loginUrl} className="btn">Sign in</a></div>
                    )}
                </Modal>
            )}
        </section>
    );
}

function ReviewForm({ storeUrl, defaultName, onDone }: { storeUrl: string; defaultName: string; onDone: () => void }) {
    const form = useForm({ reviewer_name: defaultName, rating: 0, title: '', body: '' });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.post(storeUrl, { preserveScroll: true, onSuccess: onDone });
    };

    const error = (field: string) => (form.errors as Record<string, string | undefined>)[field];

    return (
        <form onSubmit={submit} className="mt-4 space-y-4">
            <fieldset>
                <legend className="text-sm font-medium">Your rating</legend>
                <div className="mt-2 flex gap-2">
                    {[1, 2, 3, 4, 5].map((v) => (
                        <button key={v} type="button" onClick={() => form.setData('rating', v)} aria-pressed={form.data.rating === v} aria-label={`Rate ${v} star${v === 1 ? '' : 's'}`} className="text-bronze">
                            <Star className={`h-6 w-6 ${form.data.rating >= v ? 'fill-current' : ''}`} />
                        </button>
                    ))}
                </div>
                {error('rating') && <p className="mt-1 text-xs text-destructive">{error('rating')}</p>}
            </fieldset>
            <label className="block text-sm">Display name
                <input value={form.data.reviewer_name} onChange={(e) => form.setData('reviewer_name', e.target.value)} minLength={2} maxLength={60} required className="input mt-2" />
                {error('reviewer_name') && <span className="mt-1 block text-xs text-destructive">{error('reviewer_name')}</span>}
            </label>
            <label className="block text-sm">Review title <span className="text-muted-foreground">(optional)</span>
                <input value={form.data.title} onChange={(e) => form.setData('title', e.target.value)} maxLength={120} placeholder="Sum it up in a few words" className="input mt-2" />
            </label>
            <label className="block text-sm">Your review
                <textarea value={form.data.body} onChange={(e) => form.setData('body', e.target.value)} minLength={10} maxLength={2000} required rows={5} className="input mt-2 resize-y" />
                {error('body') && <span className="mt-1 block text-xs text-destructive">{error('body')}</span>}
            </label>
            {error('review') && <p className="text-xs text-destructive">{error('review')}</p>}
            <button className="btn w-full" disabled={!form.data.rating || form.processing}>Submit review</button>
        </form>
    );
}
