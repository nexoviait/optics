import { useEffect, useRef, type RefObject } from 'react';

const FOCUSABLE = 'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';

interface FocusTrapOptions {
    /** Element to focus when the trap opens (defaults to the first control). */
    initialFocus?: RefObject<HTMLElement | null>;
    /** Where focus goes on close, read at close time (defaults to whatever was focused before opening). */
    returnFocus?: () => HTMLElement | null;
}

/** While active, keeps Tab / Shift+Tab inside `ref` and restores focus when it closes. */
export function useFocusTrap(ref: RefObject<HTMLElement | null>, active: boolean, options: FocusTrapOptions = {}): void {
    const latest = useRef(options);
    latest.current = options;

    useEffect(() => {
        if (!active) return;
        const container = ref.current;
        if (!container) return;
        const previouslyFocused = document.activeElement as HTMLElement | null;

        (latest.current.initialFocus?.current ?? container.querySelector<HTMLElement>(FOCUSABLE) ?? container).focus();

        const onKeyDown = (event: KeyboardEvent) => {
            if (event.key !== 'Tab') return;
            const items = Array.from(container.querySelectorAll<HTMLElement>(FOCUSABLE));
            const first = items[0];
            const last = items[items.length - 1];
            if (!first || !last) return;
            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault();
                last.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first.focus();
            }
        };
        container.addEventListener('keydown', onKeyDown);

        return () => {
            container.removeEventListener('keydown', onKeyDown);
            (latest.current.returnFocus?.() ?? previouslyFocused)?.focus?.();
        };
    }, [active, ref]);
}
