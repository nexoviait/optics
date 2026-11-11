/** Props shared with every Inertia page by App\Http\Middleware\HandleInertiaRequests. */
export interface Translations {
    gallery: Record<string, string | Record<string, string>>;
}

export interface SharedProps {
    auth: { user: { name: string } | null };
    store: {
        name: string;
        exchangeDays: number;
        warrantyDays: number;
        rxSubmissionDays: number;
        supportPhone: string | null;
    };
    urls: {
        home: string;
        cart: string;
        login: string;
        deliveryEstimate: string;
        visionMatch: string;
        sizeGuidePage: string;
        mediapipe: string;
        tryOnPortrait: string;
    };
    translations: Translations;
    flash: { success: string | null };
    errors: Record<string, string>;
    [key: string]: unknown;
}
