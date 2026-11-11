import { useState, type FormEvent } from 'react';
import { Check, MapPin, Truck } from 'lucide-react';
import type { Region } from '@/types/product';
import { send } from '@/lib/storefront';

interface Estimate {
    status: 'available' | 'unavailable' | 'invalid';
    message?: string;
    earliest?: string;
    latest?: string;
    dispatch?: string;
    dispatch_days?: number;
    courier?: string;
    zone?: string;
    transit_min?: number;
    transit_max?: number;
}

export function DeliveryChecker({ url, regions }: { url: string; regions: Region[] }) {
    const [region, setRegion] = useState(regions[0]?.code ?? 'CH');
    const [postal, setPostal] = useState('');
    const [result, setResult] = useState<Estimate | null>(null);
    const [loading, setLoading] = useState(false);

    const check = async (e: FormEvent) => {
        e.preventDefault();
        setLoading(true);
        try {
            setResult(await send<Estimate>(url, { region, postal }));
        } catch (err) {
            setResult({ status: 'invalid', message: (err as Error).message });
        } finally {
            setLoading(false);
        }
    };

    return (
        <section className="mt-6 border-b border-border pb-6">
            <h2 className="flex items-center gap-2 text-sm font-semibold"><Truck className="h-4 w-4" />Check delivery details</h2>
            <form className="mt-3 space-y-2" onSubmit={check}>
                <label className="sr-only" htmlFor="delivery-country">Delivery country</label>
                <select id="delivery-country" value={region} onChange={(e) => { setRegion(e.target.value); setResult(null); }} className="input text-xs">
                    {regions.map((r) => <option key={r.code} value={r.code}>{r.name}</option>)}
                </select>
                <div className="flex gap-2">
                    <label className="sr-only" htmlFor="postal-code">Zip / postal code</label>
                    <input id="postal-code" autoComplete="postal-code" maxLength={12} value={postal} onChange={(e) => { setPostal(e.target.value); setResult(null); }} placeholder="Zip / postal code" className="input min-w-0 flex-1" />
                    <button className="btn-ghost" disabled={loading}><MapPin className="h-4 w-4" />Check</button>
                </div>
            </form>
            <div aria-live="polite" className="mt-3 text-xs">
                {result && result.status !== 'available' && <p role="status" className={result.status === 'invalid' ? 'text-destructive' : 'text-muted-foreground'}>{result.message}</p>}
                {result?.status === 'available' && (
                    <div className="space-y-2">
                        <p className="flex items-center gap-2 font-medium"><Check className="h-4 w-4" />Estimated arrival: {result.earliest} – {result.latest}</p>
                        <p className="text-muted-foreground">Estimated dispatch {result.dispatch} · {result.dispatch_days} business days for inspection</p>
                        <p className="text-muted-foreground">{result.courier} · {result.zone} zone · {result.transit_min}–{result.transit_max} business days in transit</p>
                        <p className="text-muted-foreground">Shipping-zone estimate, not confirmed courier coverage. Weekends are excluded; prescription verification and customs may extend delivery.</p>
                    </div>
                )}
            </div>
        </section>
    );
}
