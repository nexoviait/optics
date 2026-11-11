import { useEffect, useState } from 'react';
import { X } from 'lucide-react';

export interface Look {
    id: string;
    productId: number;
    name: string;
    url: string;
    color: string;
    image: string; // data-URL
}

const KEY = 'optics_compare_looks';
const MAX = 4;

export function loadLooks(): Look[] {
    try {
        return JSON.parse(sessionStorage.getItem(KEY) ?? '[]') as Look[];
    } catch {
        return [];
    }
}

export function saveLook(look: Look): void {
    const existing = loadLooks().filter((l) => l.id !== look.id);
    // Newest first; cap at MAX
    const updated = [look, ...existing].slice(0, MAX);
    sessionStorage.setItem(KEY, JSON.stringify(updated));
}

export function removeLook(id: string): void {
    const updated = loadLooks().filter((l) => l.id !== id);
    sessionStorage.setItem(KEY, JSON.stringify(updated));
}

interface CompareLooksProps {
    /** Increment this to trigger a re-read from sessionStorage. */
    refreshKey?: number;
}

/**
 * Shows up to four saved try-on snapshots in a side-by-side grid. Each card
 * links to the product page and can be removed. The grid only appears when
 * there is at least one saved snapshot.
 *
 * Data lives in sessionStorage — it is never uploaded, and it disappears
 * when the tab is closed. Selfie Segmentation (background removal) and
 * Remove Glasses (AI inpainting) are intentionally omitted: segmentation
 * needs a large model (~6 MB, significant load time) and would not add
 * value in a comparison grid; inpainting needs a server-side diffusion
 * model and cannot be done reliably in the browser.
 */
export function CompareLooks({ refreshKey }: CompareLooksProps) {
    const [looks, setLooks] = useState<Look[]>(() => loadLooks());

    useEffect(() => {
        setLooks(loadLooks());
    }, [refreshKey]);

    const remove = (id: string) => {
        removeLook(id);
        setLooks((prev) => prev.filter((l) => l.id !== id));
    };

    if (!looks.length) return null;

    return (
        <section aria-label="Compare looks" className="rounded-xl border border-border p-3">
            <p className="mb-2 text-xs font-medium">Compare looks</p>
            <div className={`grid gap-2 ${looks.length === 1 ? 'grid-cols-1' : looks.length === 2 ? 'grid-cols-2' : looks.length === 3 ? 'grid-cols-3' : 'grid-cols-4'}`}>
                {looks.map((look) => (
                    <div key={look.id} className="relative overflow-hidden rounded-lg">
                        <a href={look.url} title={look.name}>
                            <img src={look.image} alt={`You trying on ${look.name}`} className="aspect-[3/4] w-full object-cover" />
                        </a>
                        <button
                            type="button"
                            onClick={() => remove(look.id)}
                            aria-label={`Remove ${look.name} from comparison`}
                            className="absolute right-1 top-1 rounded-full bg-obsidian/70 p-1 text-on-dark hover:bg-obsidian"
                        >
                            <X className="h-3 w-3" />
                        </button>
                        <p className="truncate bg-obsidian/60 px-1.5 py-0.5 text-[0.6rem] text-on-dark">{look.name}</p>
                    </div>
                ))}
            </div>
            <button type="button" onClick={() => { sessionStorage.removeItem(KEY); setLooks([]); }} className="mt-2 text-[0.65rem] text-muted-foreground underline">Clear all</button>
        </section>
    );
}
