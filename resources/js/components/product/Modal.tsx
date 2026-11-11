import { useRef, type ReactNode } from 'react';
import { createPortal } from 'react-dom';
import { X } from 'lucide-react';
import { useBodyScrollLock } from '@/components/product-gallery/hooks/useBodyScrollLock';
import { useFocusTrap } from '@/components/product-gallery/hooks/useFocusTrap';

interface ModalProps {
    title: string;
    subtitle?: string;
    onClose: () => void;
    children: ReactNode;
    /** 'center' dialog (default) or a right-hand 'drawer'. */
    variant?: 'center' | 'drawer';
    width?: string;
    /** Rendered above other dialogs (e.g. the eye-power modal over the lens drawer). */
    elevated?: boolean;
}

/** Accessible dialog: portal, focus trap, Esc / backdrop to close, body scroll lock. */
export function Modal({ title, subtitle, onClose, children, variant = 'center', width = 'max-w-lg', elevated = false }: ModalProps) {
    const panel = useRef<HTMLDivElement>(null);
    useBodyScrollLock(true);
    useFocusTrap(panel, true);

    const drawer = variant === 'drawer';

    return createPortal(
        <div className={`fixed inset-0 ${elevated ? 'z-[70]' : 'z-50'} ${drawer ? '' : 'grid place-items-center p-4'}`}>
            <div className="absolute inset-0 bg-obsidian/50" onClick={onClose} aria-hidden="true" />
            <div
                ref={panel}
                role="dialog"
                aria-modal="true"
                aria-label={title}
                onKeyDown={(e) => {
                    if (e.key === 'Escape') {
                        e.stopPropagation();
                        onClose();
                    }
                }}
                className={drawer
                    ? 'absolute inset-y-0 right-0 flex w-full flex-col overflow-y-auto bg-background sm:max-w-md'
                    : `relative max-h-[90dvh] w-full ${width} overflow-y-auto rounded-lg bg-card p-6`}
            >
                {!drawer && (
                    <div className="flex items-start justify-between gap-4">
                        <div>
                            <h2 className="text-lg font-semibold">{title}</h2>
                            {subtitle && <p className="mt-1 text-xs text-muted-foreground">{subtitle}</p>}
                        </div>
                        <button type="button" onClick={onClose} aria-label="Close"><X className="h-4 w-4" /></button>
                    </div>
                )}
                {children}
            </div>
        </div>,
        document.body,
    );
}
