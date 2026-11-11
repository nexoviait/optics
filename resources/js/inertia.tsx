import { createInertiaApp } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';
import type { ComponentType } from 'react';

const pages = import.meta.glob<{ default: ComponentType }>('./Pages/**/*.tsx');

void createInertiaApp({
    resolve: async (name) => {
        const page = pages[`./Pages/${name}.tsx`];
        if (!page) throw new Error(`Inertia page not found: ${name}`);
        return (await page()).default;
    },
    setup({ el, App, props }) {
        createRoot(el).render(<App {...props} />);
    },
    // The storefront has its own top progress styling; keep Inertia's bar on-brand.
    progress: { color: '#C9A887', delay: 150 },
});
