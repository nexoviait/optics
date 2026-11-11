import { useState } from 'react';
import { ArrowLeft, Check, Clock, Keyboard, Loader2, Phone, Upload, X } from 'lucide-react';
import type { LensOption, PowerType } from '@/types/product';
import { money, send, toast } from '@/lib/storefront';
import { Modal } from './Modal';

interface LensDrawerProps {
    productId: number;
    productPrice: number;
    finish: string | null;
    size: string;
    cartUrl: string;
    powerTypes: PowerType[];
    lensOptions: LensOption[];
    rxSubmissionDays: number;
    supportPhone: string | null;
    onClose: () => void;
}

type Rx = Record<'r_sph' | 'l_sph' | 'r_cyl' | 'l_cyl' | 'r_axis' | 'l_axis' | 'add_power' | 'pd' | 'name' | 'phone', string>;

const range = (from: number, to: number, step: number) =>
    Array.from({ length: Math.round((to - from) / step) + 1 }, (_, i) => {
        const v = from + i * step;
        return `${v > 0 ? '+' : ''}${v.toFixed(2)}`.replace('+0.00', '0.00');
    });
const SPH = range(-12, 8, 0.25);
const CYL = range(-6, 0, 0.25);
const AXIS = Array.from({ length: 180 }, (_, i) => String(i + 1));
const ADD = range(0.75, 3.5, 0.25);
const TITLES = ['Select Power Type', 'Choose Your Lenses', 'Add Your Power'];

/** 3-step lens configuration: power type → lens → prescription, then adds the frame to the bag. */
export function LensDrawer(props: LensDrawerProps) {
    const { powerTypes, lensOptions, productPrice, onClose } = props;
    const [step, setStep] = useState(1);
    const [powerCode, setPowerCode] = useState<string | null>(null);
    const [lensCode, setLensCode] = useState<string | null>(null);
    const [submitting, setSubmitting] = useState(false);
    const [powerOpen, setPowerOpen] = useState(false);

    const power = powerTypes.find((p) => p.code === powerCode);
    const lens = lensOptions.find((l) => l.code === lensCode);
    const total = productPrice + (power?.price ?? 0) + (power?.needsLens && lens ? lens.price : 0);

    const submit = async (extra: { rx_mode?: 'later' | 'manual' | 'upload'; rx?: Partial<Rx>; file?: File }, chosenPower = power, chosenLens = lensCode) => {
        const form = new FormData();
        form.append('product_id', String(props.productId));
        form.append('size', props.size);
        form.append('finish', props.finish ?? '');
        form.append('lens[power_type]', chosenPower?.code ?? '');
        if (chosenLens) form.append('lens[lens]', chosenLens);
        if (chosenPower?.needsRx) form.append('lens[rx_mode]', extra.rx_mode ?? 'later');
        Object.entries(extra.rx ?? {}).forEach(([k, v]) => v !== '' && form.append(`lens[rx][${k}]`, v));
        if (extra.file) form.append('prescription_file', extra.file);

        setSubmitting(true);
        try {
            const res = await send<{ redirect?: string }>(props.cartUrl, form);
            window.location.href = res.redirect ?? props.cartUrl; // the bag is a Blade page
        } catch (e) {
            toast((e as Error).message, 'error');
            setSubmitting(false);
        }
    };

    const choosePower = (p: PowerType) => {
        setPowerCode(p.code);
        setLensCode(null);
        if (!p.needsLens) void submit({}, p, null);
        else setStep(2);
    };

    const chooseLens = (l: LensOption) => {
        setLensCode(l.code);
        if (!power?.needsRx) void submit({}, power, l.code);
        else setStep(3);
    };

    const card = (active: boolean) => `w-full rounded-xl border p-4 text-left transition ${active ? 'border-primary bg-secondary' : 'border-border bg-card hover:border-bronze'}`;

    return (
        <Modal title={TITLES[step - 1] ?? ''} onClose={onClose} variant="drawer">
            <div className="p-4">
                <div className="flex items-center gap-2">
                    {step > 1 && <button type="button" onClick={() => setStep(step - 1)} aria-label="Back"><ArrowLeft className="h-4 w-4" /></button>}
                    <h2 className="font-display text-lg font-semibold">{TITLES[step - 1]}</h2>
                    <button type="button" className="ml-auto" onClick={onClose} aria-label="Close"><X className="h-4 w-4" /></button>
                </div>
                <div className="mt-3 flex gap-1">{[1, 2, 3].map((s) => <span key={s} className={`h-1 flex-1 rounded ${s <= step ? 'bg-primary' : 'bg-border'}`} />)}</div>
            </div>

            <div className="flex-1 space-y-3 p-4">
                {step === 1 && powerTypes.map((p) => (
                    <button key={p.code} type="button" onClick={() => choosePower(p)} className={`flex items-center gap-4 ${card(powerCode === p.code)}`}>
                        <div className="flex-1"><div className="text-sm font-semibold">{p.label}</div><div className="text-xs text-muted-foreground">{p.description}</div></div>
                        <span className="font-mono text-xs">{p.price ? `+${money(p.price)}` : 'Included'}</span>
                    </button>
                ))}
                {step === 2 && lensOptions.map((l) => (
                    <button key={l.code} type="button" onClick={() => chooseLens(l)} className={card(lensCode === l.code)}>
                        <div className="flex items-start gap-4">
                            <div className="flex-1">
                                <div className="flex flex-wrap items-center gap-2"><span className="text-sm font-semibold">{l.label}</span>{l.badge && <span className="eyebrow rounded-full bg-bronze-soft px-2 py-0.5 text-[0.5rem]">{l.badge}</span>}</div>
                                <div className="text-xs text-muted-foreground">{l.description}</div>
                            </div>
                            <span className="font-mono text-xs">{l.price ? `+${money(l.price)}` : 'Included'}</span>
                        </div>
                        {l.features.length > 0 && (
                            <ul className="mt-3 grid gap-1 border-t border-border pt-3 text-xs sm:grid-cols-2">
                                {l.features.map((f) => <li key={f} className="flex items-start gap-1.5"><Check className="mt-0.5 h-3 w-3 shrink-0 text-sage" /><span>{f}</span></li>)}
                            </ul>
                        )}
                    </button>
                ))}
                {step === 3 && (
                    <>
                        <button type="button" onClick={() => void submit({ rx_mode: 'later' })} className="flex w-full items-center gap-4 rounded-xl border border-border bg-card p-4 text-left hover:border-bronze">
                            <div className="grid h-10 w-10 shrink-0 place-items-center rounded-lg bg-bronze-soft"><Clock className="h-4 w-4" /></div>
                            <div><div className="text-sm font-semibold">Submit power later</div><div className="text-xs text-muted-foreground">Within {props.rxSubmissionDays} days of order — we'll remind you</div></div>
                        </button>
                        <button type="button" onClick={() => setPowerOpen(true)} className="flex w-full items-center gap-4 rounded-xl border border-border bg-card p-4 text-left hover:border-bronze">
                            <div className="grid h-10 w-10 shrink-0 place-items-center rounded-lg bg-bronze-soft"><Keyboard className="h-4 w-4" /></div>
                            <div><div className="text-sm font-semibold">Enter power manually</div><div className="text-xs text-muted-foreground">SPH / CYL / AXIS</div></div>
                        </button>
                        <label className="flex w-full cursor-pointer items-center gap-4 rounded-xl border border-border bg-card p-4 hover:border-bronze focus-within:ring-2 focus-within:ring-ring">
                            <div className="grid h-10 w-10 shrink-0 place-items-center rounded-lg bg-bronze-soft"><Upload className="h-4 w-4" /></div>
                            <div><div className="text-sm font-semibold">Upload prescription</div><div className="text-xs text-muted-foreground">PDF or photo, max 5 MB — reviewed by our opticians</div></div>
                            <input type="file" accept="image/*,application/pdf" className="sr-only" onChange={(e) => { const file = e.target.files?.[0]; if (file) void submit({ rx_mode: 'upload', file }); }} />
                        </label>
                    </>
                )}
            </div>

            <div className="flex items-center justify-between border-t border-border p-4">
                <div><div className="eyebrow text-[0.55rem] text-muted-foreground">Frame + lenses</div><div className="font-display text-lg font-semibold">{money(total)}</div></div>
                {submitting
                    ? <span className="flex items-center gap-1 text-xs"><Loader2 className="h-3 w-3 animate-spin" />Adding to bag…</span>
                    : <span className="flex items-center gap-1 text-xs text-muted-foreground"><Check className="h-3 w-3" />Free precision fitting</span>}
            </div>

            {powerOpen && <PowerModal needsAdd={power?.code === 'progressive'} supportPhone={props.supportPhone} onClose={() => setPowerOpen(false)} onSave={(rx) => { setPowerOpen(false); void submit({ rx_mode: 'manual', rx }); }} />}
        </Modal>
    );
}

function PowerModal({ needsAdd, supportPhone, onClose, onSave }: { needsAdd: boolean; supportPhone: string | null; onClose: () => void; onSave: (rx: Partial<Rx>) => void }) {
    const [rx, setRx] = useState<Rx>({ r_sph: '0.00', l_sph: '0.00', r_cyl: '0.00', l_cyl: '0.00', r_axis: '90', l_axis: '90', add_power: '', pd: '', name: '', phone: '' });
    const [same, setSame] = useState(false);
    const [cyl, setCyl] = useState(false);
    const [error, setError] = useState('');

    const set = (key: keyof Rx, value: string) => setRx((current) => {
        const next = { ...current, [key]: value };
        if (same && key.startsWith('r_')) next[`l_${key.slice(2)}` as keyof Rx] = value;
        return next;
    });

    const toggleSame = () => {
        setSame(!same);
        if (!same) setRx((r) => ({ ...r, l_sph: r.r_sph, l_cyl: r.r_cyl, l_axis: r.r_axis }));
    };

    const save = () => {
        if (needsAdd && !rx.add_power) return setError('Select your reading addition (ADD)');
        if (rx.pd !== '' && !(Number(rx.pd) >= 40 && Number(rx.pd) <= 80)) return setError('Pupil distance should be between 40 and 80 mm');
        if (rx.name.trim().length < 2) return setError('Enter patient name');
        if (!/^[+\d\s-]{7,18}$/.test(rx.phone.trim())) return setError('Enter a valid phone number');
        setError('');
        onSave(cyl ? rx : { ...rx, r_cyl: '0.00', l_cyl: '0.00', r_axis: '', l_axis: '' });
    };

    const lists = { sph: SPH, cyl: CYL, axis: AXIS } as const;

    return (
        <Modal title="Enter Eye Power" onClose={onClose} elevated>
            <div className="mt-3 space-y-3">
                <label className="flex items-center justify-between rounded-lg border border-border p-3 text-sm">I have same power for both eyes
                    <span className="switch"><input type="checkbox" checked={same} onChange={toggleSame} /></span></label>
                <label className="flex items-center justify-between rounded-lg border border-border p-3 text-sm">I have cylindrical power
                    <span className="switch"><input type="checkbox" checked={cyl} onChange={() => setCyl(!cyl)} /></span></label>
                <div className="grid grid-cols-[auto_1fr_1fr_1fr] items-center gap-2 text-xs">
                    <span /><span className="eyebrow text-[0.55rem] text-muted-foreground">Power (SPH)</span><span className="eyebrow text-[0.55rem] text-muted-foreground">CYL</span><span className="eyebrow text-[0.55rem] text-muted-foreground">Axis</span>
                    {(['r', 'l'] as const).map((eye) => (
                        <div key={eye} className="contents">
                            <span className="font-semibold">{eye === 'r' ? 'Right (OD)' : 'Left (OS)'}</span>
                            {(['sph', 'cyl', 'axis'] as const).map((field) => {
                                const key = `${eye}_${field}` as keyof Rx;
                                return (
                                    <select key={field} value={rx[key]} onChange={(e) => set(key, e.target.value)} disabled={(field !== 'sph' && !cyl) || (eye === 'l' && same)}
                                            aria-label={`${eye === 'r' ? 'Right (OD)' : 'Left (OS)'} ${field.toUpperCase()}`} className="w-full rounded-md border border-input bg-card px-2 py-2 font-mono text-sm disabled:opacity-50">
                                        {lists[field].map((o) => <option key={o} value={o}>{o}</option>)}
                                    </select>
                                );
                            })}
                        </div>
                    ))}
                </div>
                <div className="grid gap-3 sm:grid-cols-2">
                    {needsAdd && (
                        <select value={rx.add_power} onChange={(e) => set('add_power', e.target.value)} aria-label="Reading addition (ADD)" className="input font-mono">
                            <option value="">Reading addition (ADD)</option>
                            {ADD.map((o) => <option key={o} value={o}>{o}</option>)}
                        </select>
                    )}
                    <input inputMode="decimal" placeholder="Pupil distance, mm (optional)" value={rx.pd} onChange={(e) => set('pd', e.target.value)} aria-label="Pupil distance in millimetres" className="input" />
                </div>
                <div className="grid gap-3 sm:grid-cols-2">
                    <input placeholder="Patient name" value={rx.name} onChange={(e) => set('name', e.target.value)} aria-label="Patient name" className="input" />
                    <input placeholder="Phone number" value={rx.phone} onChange={(e) => set('phone', e.target.value)} aria-label="Phone number" className="input" />
                </div>
                {error && <p className="text-xs text-destructive" role="alert">{error}</p>}
                {supportPhone && (
                    <div className="flex items-center gap-2 rounded-lg bg-bronze-soft p-3 text-xs"><Phone className="h-3.5 w-3.5" /> Can't find your power? Call <a href={`tel:${supportPhone.replace(/\s+/g, '')}`} className="font-semibold">{supportPhone}</a></div>
                )}
                <button type="button" onClick={save} className="btn w-full py-3">Save & Proceed</button>
            </div>
        </Modal>
    );
}
