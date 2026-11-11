/**
 * Bridges to the Alpine storefront (resources/js/app.js) that wraps every
 * Inertia page: its JSON helper (CSRF + error handling), toasts and the header
 * cart / wishlist badges. Falls back gracefully when it isn't loaded (tests).
 */

export function money(value: number): string {
    return `$${Math.round(value).toLocaleString('en-US')}`;
}

export function toast(message: string, type: 'success' | 'error' = 'success'): void {
    window.toast?.(message, type);
}

export async function send<T = Record<string, unknown>>(url: string, data?: FormData | Record<string, unknown>): Promise<T> {
    if (!window.send) throw new Error('Storefront scripts are not loaded.');
    return window.send<T>(url, data);
}

export function setBadge(store: 'cart' | 'wishlist', count: number): void {
    const target = window.Alpine?.store(store);
    if (target) target.count = count;
}
