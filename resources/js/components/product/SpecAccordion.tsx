import { useState } from 'react';
import { ChevronDown } from 'lucide-react';
import type { FrameSize, SpecGroup } from '@/types/product';

interface SpecAccordionProps {
    groups: SpecGroup[];
    size: FrameSize | undefined;
    sizeGuideUrl: string;
}

/** Technical details; the frame dimensions follow the selected size. */
export function SpecAccordion({ groups, size, sizeGuideUrl }: SpecAccordionProps) {
    const [open, setOpen] = useState<string | null>(groups[0]?.title ?? null);

    return (
        <div className="mt-5 divide-y divide-border border-b border-border">
            {groups.map((group) => {
                const expanded = open === group.title;
                const id = `spec-${group.title.replace(/\W+/g, '-').toLowerCase()}`;
                return (
                    <div key={group.title}>
                        <button type="button" onClick={() => setOpen(expanded ? null : group.title)} aria-expanded={expanded} aria-controls={id} className="flex w-full items-center justify-between py-3 text-sm font-medium">
                            {group.title}<ChevronDown className={`h-4 w-4 transition ${expanded ? 'rotate-180' : ''}`} />
                        </button>
                        {expanded && (
                            <dl id={id} className="space-y-2 pb-4 text-xs">
                                {group.rows.map((row) => (
                                    <div key={row.label} className="grid grid-cols-[minmax(0,1fr)_minmax(0,1.4fr)] gap-4">
                                        <dt className="text-muted-foreground">{row.label}</dt>
                                        <dd className="break-words text-right">{row.href ? <a href={row.href} className="underline decoration-dotted">{row.value}</a> : row.value}</dd>
                                    </div>
                                ))}
                                {group.title === 'Style Details' && size && (
                                    <>
                                        <div className="grid grid-cols-2 gap-4"><dt className="text-muted-foreground">Frame Width</dt><dd className="text-right">{size.width} mm · {size.code}</dd></div>
                                        <div className="grid grid-cols-2 gap-4"><dt className="text-muted-foreground">Frame Dimensions</dt><dd className="text-right">{size.lens}-{size.bridge}-{size.temple}</dd></div>
                                        <p className="text-[0.65rem] text-muted-foreground">Dimensions follow the selected size and are provisional pending atelier confirmation. <a href={sizeGuideUrl} className="underline">How to read frame sizes</a></p>
                                    </>
                                )}
                            </dl>
                        )}
                    </div>
                );
            })}
        </div>
    );
}
