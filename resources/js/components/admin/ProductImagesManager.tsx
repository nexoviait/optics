import { useCallback, useEffect, useRef, useState, type DragEvent } from 'react';
import { ArrowDown, ArrowUp, Box, GripVertical, Loader2, RotateCw, Star, Trash2, UploadCloud } from 'lucide-react';
import type { ImageViewType, ProductImage } from '@/types/product';

export interface ManagedImage extends ProductImage {
    isLifestyle: boolean;
    sortOrder: number;
    processed: boolean;
    updateUrl: string;
    deleteUrl: string;
}

interface Payload {
    images: ManagedImage[];
    spinSet: { frameCount: number; frames: string[] } | null;
    model3d: string | null;
    /** 'upload' (admin), 'folder' (public/models/{sku}.glb) or null. */
    model3dSource: 'upload' | 'folder' | null;
    sku: string | null;
    message?: string;
}

export interface ProductImagesManagerProps {
    indexUrl: string;
    uploadUrl: string;
    reorderUrl: string;
    spinSetUrl: string;
    /** POST a .glb here to set the 3D model, DELETE to remove it. */
    modelUrl: string;
    viewTypes: Record<ImageViewType, string>;
}

interface Upload {
    id: string;
    name: string;
    progress: number;
    error?: string;
}

const csrf = () => document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';

/** XHR (not fetch) so uploads can report progress. Resolves with the JSON body, rejects with the first validation message. */
function request<T>(method: string, url: string, body?: FormData | object, onProgress?: (percent: number) => void): Promise<T> {
    return new Promise((resolve, reject) => {
        const xhr = new XMLHttpRequest();
        xhr.open(method, url);
        xhr.setRequestHeader('Accept', 'application/json');
        xhr.setRequestHeader('X-CSRF-TOKEN', csrf());
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        if (body && !(body instanceof FormData)) xhr.setRequestHeader('Content-Type', 'application/json');
        if (onProgress) xhr.upload.onprogress = (e) => e.lengthComputable && onProgress(Math.round((e.loaded / e.total) * 100));
        xhr.onload = () => {
            const json = (() => { try { return JSON.parse(xhr.responseText) as Record<string, unknown>; } catch { return {}; } })();
            if (xhr.status >= 200 && xhr.status < 300) return resolve(json as T);
            const errors = json.errors as Record<string, string[]> | undefined;
            reject(new Error(Object.values(errors ?? {})[0]?.[0] ?? (json.message as string | undefined) ?? 'Request failed.'));
        };
        xhr.onerror = () => reject(new Error('Network error — please try again.'));
        xhr.send(body instanceof FormData ? body : body ? JSON.stringify(body) : null);
    });
}

/**
 * Admin gallery manager: drag-and-drop multi-upload with progress, drag (or
 * arrow buttons) to reorder, inline alt text / view type / placement, primary
 * photo, delete, a ZIP upload for the 360° spin set and a 3D model (.glb) upload.
 */
export function ProductImagesManager({ indexUrl, uploadUrl, reorderUrl, spinSetUrl, modelUrl, viewTypes }: ProductImagesManagerProps) {
    const [data, setData] = useState<Payload>({ images: [], spinSet: null, model3d: null, model3dSource: null, sku: null });
    const [uploads, setUploads] = useState<Upload[]>([]);
    const [dragOver, setDragOver] = useState(false);
    const [viewType, setViewType] = useState<ImageViewType>('front');
    const [lifestyle, setLifestyle] = useState(false);
    const [notice, setNotice] = useState<{ text: string; error?: boolean } | null>(null);
    const [spinProgress, setSpinProgress] = useState<number | null>(null);
    const [modelProgress, setModelProgress] = useState<number | null>(null);
    const dragging = useRef<number | null>(null);

    const load = useCallback(async () => setData(await request<Payload>('GET', indexUrl)), [indexUrl]);
    useEffect(() => void load(), [load]);

    // Processing runs in the queue; poll briefly while any photo is still being processed.
    const pending = data.images.some((i) => !i.processed && !i.isLifestyle);
    useEffect(() => {
        if (!pending) return;
        const timer = setTimeout(() => void load(), 4000);
        return () => clearTimeout(timer);
    }, [pending, data, load]);

    const apply = (payload: Payload) => {
        setData(payload);
        if (payload.message) setNotice({ text: payload.message });
    };
    const fail = (e: unknown) => setNotice({ text: (e as Error).message, error: true });

    const uploadFiles = async (files: File[]) => {
        for (const file of files.filter((f) => f.type.startsWith('image/'))) {
            const id = `${file.name}-${Math.random().toString(36).slice(2)}`;
            setUploads((u) => [...u, { id, name: file.name, progress: 0 }]);
            const form = new FormData();
            form.append('images[]', file);
            form.append('view_type', viewType);
            form.append('is_lifestyle', lifestyle ? '1' : '0');
            try {
                apply(await request<Payload>('POST', uploadUrl, form, (progress) => setUploads((u) => u.map((x) => (x.id === id ? { ...x, progress } : x)))));
                setUploads((u) => u.filter((x) => x.id !== id));
            } catch (e) {
                setUploads((u) => u.map((x) => (x.id === id ? { ...x, error: (e as Error).message } : x)));
            }
        }
    };

    const update = async (image: ManagedImage, changes: Record<string, unknown>) => {
        try {
            apply(await request<Payload>('PATCH', image.updateUrl, changes));
        } catch (e) {
            fail(e);
        }
    };

    const remove = async (image: ManagedImage) => {
        if (!window.confirm('Remove this image?')) return;
        try {
            apply(await request<Payload>('DELETE', image.deleteUrl));
        } catch (e) {
            fail(e);
        }
    };

    const reorder = async (ids: number[]) => {
        // Optimistic: show the new order immediately, then save.
        setData((d) => ({ ...d, images: ids.map((id) => d.images.find((i) => i.id === id)).filter((i): i is ManagedImage => !!i) }));
        try {
            apply(await request<Payload>('PATCH', reorderUrl, { ids }));
        } catch (e) {
            fail(e);
            void load();
        }
    };

    const move = (from: number, to: number) => {
        const ids = data.images.map((i) => i.id).filter((id): id is number => id !== null);
        if (to < 0 || to >= ids.length) return;
        const [moved] = ids.splice(from, 1);
        if (moved === undefined) return;
        ids.splice(to, 0, moved);
        void reorder(ids);
    };

    const uploadSpin = async (file: File) => {
        const form = new FormData();
        form.append('archive', file);
        setSpinProgress(0);
        try {
            apply(await request<Payload>('POST', spinSetUrl, form, setSpinProgress));
            setTimeout(() => void load(), 3000); // frames are extracted by a queued job
        } catch (e) {
            fail(e);
        } finally {
            setSpinProgress(null);
        }
    };

    const uploadModel = async (file: File) => {
        const form = new FormData();
        form.append('model', file);
        setModelProgress(0);
        try {
            apply(await request<Payload>('POST', modelUrl, form, setModelProgress));
        } catch (e) {
            fail(e);
        } finally {
            setModelProgress(null);
        }
    };

    const removeModel = async () => {
        if (!window.confirm('Remove the 3D model? The 360° view will fall back to photos.')) return;
        try {
            apply(await request<Payload>('DELETE', modelUrl));
        } catch (e) {
            fail(e);
        }
    };

    const onDrop = (e: DragEvent) => {
        e.preventDefault();
        setDragOver(false);
        void uploadFiles(Array.from(e.dataTransfer.files));
    };

    return (
        <div className="space-y-5">
            <div className="grid gap-4 lg:grid-cols-[1fr_auto]">
                <label
                    onDragOver={(e) => { e.preventDefault(); setDragOver(true); }}
                    onDragLeave={() => setDragOver(false)}
                    onDrop={onDrop}
                    className={`flex cursor-pointer flex-col items-center justify-center rounded-xl border-2 border-dashed p-6 text-center text-sm transition ${dragOver ? 'border-primary bg-secondary' : 'border-border text-muted-foreground hover:border-bronze'}`}
                >
                    <UploadCloud className="mb-2 h-6 w-6" />
                    Drop product photos here or click to choose
                    <span className="mt-1 text-xs">JPG, PNG or WebP · square 1080 × 1080 recommended · max 8 MB each</span>
                    <input type="file" accept="image/jpeg,image/png,image/webp" multiple className="sr-only" onChange={(e) => { void uploadFiles(Array.from(e.target.files ?? [])); e.target.value = ''; }} />
                </label>
                <div className="flex flex-col gap-2 text-xs lg:w-56">
                    <label className="space-y-1"><span className="text-muted-foreground">View type for new uploads</span>
                        <select value={viewType} onChange={(e) => setViewType(e.target.value as ImageViewType)} className="input py-1.5 text-xs">
                            {Object.entries(viewTypes).map(([value, label]) => <option key={value} value={value}>{label}</option>)}
                        </select>
                    </label>
                    <label className="flex items-center gap-2"><input type="checkbox" checked={lifestyle} onChange={(e) => setLifestyle(e.target.checked)} /> Lifestyle (Style Stories)</label>
                </div>
            </div>

            {uploads.length > 0 && (
                <ul className="space-y-1 text-xs" aria-live="polite">
                    {uploads.map((u) => (
                        <li key={u.id} className="flex items-center gap-3">
                            <span className="w-40 truncate">{u.name}</span>
                            {u.error ? <span className="text-destructive">{u.error}</span> : (
                                <progress value={u.progress} max={100} className="rating-bar h-1.5 flex-1" aria-label={`Uploading ${u.name}`} />
                            )}
                        </li>
                    ))}
                </ul>
            )}

            {notice && <p role="status" className={`text-xs ${notice.error ? 'text-destructive' : 'text-muted-foreground'}`}>{notice.text}</p>}

            <ol className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                {data.images.map((image, i) => (
                    <li
                        key={image.id}
                        draggable
                        onDragStart={() => { dragging.current = i; }}
                        onDragOver={(e) => e.preventDefault()}
                        onDrop={(e) => { e.preventDefault(); if (dragging.current !== null && dragging.current !== i) move(dragging.current, i); dragging.current = null; }}
                        className="rounded-lg border border-border bg-card p-2 text-xs"
                    >
                        <div className="relative">
                            <img src={image.thumb} alt={image.alt ?? ''} className="aspect-square w-full rounded bg-white object-contain" />
                            <span className="absolute left-1 top-1 flex items-center gap-1 rounded bg-card/90 px-1.5 py-0.5"><GripVertical className="h-3 w-3" />{i + 1}</span>
                            {image.isPrimary && <span className="absolute right-1 top-1 rounded bg-primary px-1.5 py-0.5 text-primary-foreground">Primary</span>}
                            {!image.processed && !image.isLifestyle && <span className="absolute bottom-1 left-1 flex items-center gap-1 rounded bg-card/90 px-1.5 py-0.5"><Loader2 className="h-3 w-3 animate-spin" />Processing</span>}
                        </div>
                        <input defaultValue={image.alt ?? ''} onBlur={(e) => e.target.value !== (image.alt ?? '') && void update(image, { alt: e.target.value })} placeholder="Alt text" aria-label="Alt text" className="input mt-2 py-1 text-xs" />
                        <div className="mt-2 grid grid-cols-2 gap-2">
                            <select value={image.viewType} onChange={(e) => void update(image, { view_type: e.target.value })} aria-label="View type" className="input py-1 text-xs">
                                {Object.entries(viewTypes).map(([value, label]) => <option key={value} value={value}>{label}</option>)}
                            </select>
                            <select value={image.isLifestyle ? '1' : '0'} onChange={(e) => void update(image, { is_lifestyle: e.target.value === '1' })} aria-label="Placement" className="input py-1 text-xs">
                                <option value="0">Gallery</option><option value="1">Style Stories</option>
                            </select>
                        </div>
                        <div className="mt-2 flex items-center gap-1">
                            <button type="button" onClick={() => move(i, i - 1)} disabled={i === 0} aria-label="Move earlier" className="rounded p-1 hover:bg-secondary disabled:opacity-40"><ArrowUp className="h-3.5 w-3.5" /></button>
                            <button type="button" onClick={() => move(i, i + 1)} disabled={i === data.images.length - 1} aria-label="Move later" className="rounded p-1 hover:bg-secondary disabled:opacity-40"><ArrowDown className="h-3.5 w-3.5" /></button>
                            {!image.isPrimary && !image.isLifestyle && (
                                <button type="button" onClick={() => void update(image, { is_primary: true })} className="flex items-center gap-1 rounded px-1.5 py-1 hover:bg-secondary"><Star className="h-3.5 w-3.5" />Make primary</button>
                            )}
                            <button type="button" onClick={() => void remove(image)} aria-label="Remove image" className="ml-auto rounded p-1 text-destructive hover:bg-secondary"><Trash2 className="h-3.5 w-3.5" /></button>
                        </div>
                    </li>
                ))}
                {data.images.length === 0 && <li className="text-sm text-muted-foreground">No gallery photos yet.</li>}
            </ol>

            <div className="rounded-xl border border-border p-4 text-sm">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p className="flex items-center gap-2 font-medium"><RotateCw className="h-4 w-4" />360° spin set (ZIP)</p>
                        <p className="mt-1 text-xs text-muted-foreground">
                            {data.spinSet ? `${data.spinSet.frameCount} frames live.` : 'None yet.'} Upload a ZIP of 24–36 turntable photos (transparent PNG/WebP, named 001…036). Max 50 MB.
                        </p>
                    </div>
                    <label className="btn-ghost cursor-pointer">
                        {spinProgress !== null ? `Uploading… ${spinProgress}%` : data.spinSet ? 'Replace 360° set' : 'Upload 360° ZIP'}
                        <input type="file" accept=".zip,application/zip" className="sr-only" disabled={spinProgress !== null} onChange={(e) => { const f = e.target.files?.[0]; if (f) void uploadSpin(f); e.target.value = ''; }} />
                    </label>
                </div>
                {data.spinSet && (
                    <div className="mt-3 flex gap-1 overflow-x-auto">
                        {data.spinSet.frames.filter((_, i) => i % 4 === 0).map((src) => <img key={src} src={src} alt="" loading="lazy" className="h-14 w-14 shrink-0 rounded border border-border bg-white object-contain" />)}
                    </div>
                )}
            </div>

            <div className="rounded-xl border border-border p-4 text-sm">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p className="flex items-center gap-2 font-medium"><Box className="h-4 w-4" />3D model (.glb)</p>
                        <p className="mt-1 text-xs text-muted-foreground">
                            {data.model3dSource === 'upload' && <>Live — the 360° view shows this model. <a href={data.model3d ?? undefined} className="underline" download>Download</a> </>}
                            {data.model3dSource === 'folder' && <>Live from <code>public/models/{data.sku}.glb</code>. An upload here takes priority; delete that file to remove it. </>}
                            {!data.model3dSource && <>None yet. Upload one, or copy it to <code>public/models/{data.sku ?? '{sku}'}.glb</code>. </>}
                            A binary glTF of the frame, real-world scale in metres, under 10 MB for fast loading (max 30 MB). Takes priority over the 360° photos.
                        </p>
                    </div>
                    <div className="flex items-center gap-2">
                        {data.model3dSource === 'upload' && (
                            <button type="button" onClick={() => void removeModel()} aria-label="Remove 3D model" className="rounded p-2 text-destructive hover:bg-secondary"><Trash2 className="h-4 w-4" /></button>
                        )}
                        <label className="btn-ghost cursor-pointer">
                            {modelProgress !== null ? `Uploading… ${modelProgress}%` : data.model3dSource === 'upload' ? 'Replace 3D model' : 'Upload 3D model'}
                            <input type="file" accept=".glb,model/gltf-binary" className="sr-only" disabled={modelProgress !== null} onChange={(e) => { const f = e.target.files?.[0]; if (f) void uploadModel(f); e.target.value = ''; }} />
                        </label>
                    </div>
                </div>
            </div>
        </div>
    );
}
