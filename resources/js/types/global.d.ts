/**
 * Globals provided by the Alpine storefront bundle (resources/js/app.js), which
 * also runs on Inertia pages: the JSON helper, toasts and the header badges.
 */
interface AlpineStores {
    cart: { count: number };
    wishlist: { count: number };
}

declare global {
    interface Window {
        send: <T = Record<string, unknown>>(url: string, data?: FormData | Record<string, unknown>, method?: string) => Promise<T>;
        toast: (message: string, type?: 'success' | 'error') => void;
        addToBag: (productId: number, finish?: string | null) => Promise<void>;
        Alpine?: { store: <K extends keyof AlpineStores>(name: K) => AlpineStores[K] };
    }
}

export {};
