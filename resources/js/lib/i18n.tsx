import { createContext, useContext, type ReactNode } from 'react';
import type { Translations } from '@/types/shared';

type Replacements = Record<string, string | number>;
export type Translate = (key: string, replacements?: Replacements) => string;

/**
 * Build a translator over Laravel's lang arrays (shared as `translations`).
 * Keys use dot paths ("gallery.view_types.front"); ":name" placeholders are
 * replaced like Laravel's __(). Missing keys fall back to the key itself.
 */
export function createTranslator(translations: Translations): Translate {
    return (key, replacements = {}) => {
        let value: unknown = translations;
        for (const part of key.split('.')) {
            value = value && typeof value === 'object' ? (value as Record<string, unknown>)[part] : undefined;
        }
        if (typeof value !== 'string') return key;

        return Object.entries(replacements).reduce((text, [name, replacement]) => text.replaceAll(`:${name}`, String(replacement)), value);
    };
}

const I18nContext = createContext<Translate>((key) => key);

export function I18nProvider({ translations, children }: { translations: Translations; children: ReactNode }) {
    return <I18nContext.Provider value={createTranslator(translations)}>{children}</I18nContext.Provider>;
}

export function useTranslate(): Translate {
    return useContext(I18nContext);
}
