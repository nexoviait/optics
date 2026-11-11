import { useEffect, useState } from 'react';
import { Heart } from 'lucide-react';
import { send, setBadge, toast } from '@/lib/storefront';

interface WishlistButtonProps {
    url: string;
    saved: boolean;
    productName: string;
    className?: string;
}

/** Heart toggle; keeps the header wishlist badge in sync. */
export function WishlistButton({ url, saved: initial, productName, className = '' }: WishlistButtonProps) {
    const [saved, setSaved] = useState(initial);
    const [busy, setBusy] = useState(false);
    useEffect(() => setSaved(initial), [initial, url]);

    const toggle = async () => {
        setBusy(true);
        try {
            const res = await send<{ saved: boolean; count: number; message: string }>(url);
            setSaved(res.saved);
            setBadge('wishlist', res.count);
            toast(res.message);
        } catch (e) {
            toast((e as Error).message, 'error');
        } finally {
            setBusy(false);
        }
    };

    return (
        <button type="button" onClick={toggle} disabled={busy} aria-pressed={saved}
                aria-label={`${saved ? 'Remove' : 'Save'} ${productName} ${saved ? 'from' : 'to'} wishlist`} className={className}>
            <Heart className={`h-4 w-4 ${saved ? 'fill-current text-destructive' : 'text-muted-foreground'}`} />
        </button>
    );
}
