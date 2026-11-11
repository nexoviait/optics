import './bootstrap';
import Alpine from 'alpinejs';
import collapse from '@alpinejs/collapse';
import { Chart, registerables } from 'chart.js';
import { drawFrameModel, MAX_PITCH, MODEL_VIEW } from './lib/frameModel';

Chart.register(...registerables);

/* -------------------------------------------------------------------------- */
/* HTTP + toasts                                                              */
/* -------------------------------------------------------------------------- */

const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content;
// Server-generated so the app works under any base path (never hard-code "/cart").
const cartUrl = () => document.querySelector('meta[name="cart-url"]')?.content ?? '/cart';

/** POST JSON or FormData; resolves with the JSON body or rejects with the first validation message. */
async function send(url, data, method = 'POST') {
    const isForm = data instanceof FormData;
    const response = await fetch(url, {
        method,
        headers: {
            Accept: 'application/json',
            'X-CSRF-TOKEN': csrf(),
            'X-Requested-With': 'XMLHttpRequest',
            ...(isForm ? {} : { 'Content-Type': 'application/json' }),
        },
        body: isForm ? data : JSON.stringify(data ?? {}),
    });
    const body = await response.json().catch(() => ({}));
    if (!response.ok) {
        const first = body.errors ? Object.values(body.errors)[0]?.[0] : null;
        throw new Error(first || body.message || 'Something went wrong. Please try again.');
    }
    return body;
}

function toast(message, type = 'success') {
    Alpine.store('toasts').push(message, type);
}

window.send = send;
window.toast = toast;

/* -------------------------------------------------------------------------- */
/* Inline form validation                                                     */
/* -------------------------------------------------------------------------- */
/*
 * Replaces the browser's validation bubbles with messages under each field on
 * every form. Fields re-check as the user types and clear once fixed. Server
 * errors render into the same [data-error] slot (x-admin.field), so a field
 * never shows two messages.
 */

function fieldLabel(el) {
    const label = el.closest('label');
    const text = label?.querySelector('.field-label')?.textContent
        || [...(label?.childNodes ?? [])].filter((n) => n.nodeType === Node.TEXT_NODE).map((n) => n.textContent).join(' ').trim()
        || el.getAttribute('aria-label')
        || el.placeholder
        || '';
    return text.replace(/\(.*?\)/g, '').trim();
}

function validationMessage(el) {
    const v = el.validity;
    const label = fieldLabel(el);
    if (v.valueMissing) {
        if (el.type === 'checkbox' || el.type === 'radio') return 'Please select an option.';
        return label ? `${label} is required.` : 'This field is required.';
    }
    if (v.typeMismatch && el.type === 'email') return 'Enter a valid email address, e.g. name@example.com.';
    if (v.typeMismatch && el.type === 'url') return 'Enter a full web address starting with https://';
    if (v.patternMismatch) return el.dataset.patternMessage || `Enter a valid ${label.toLowerCase() || 'value'}.`;
    if (v.tooShort) return `Use at least ${el.minLength} characters (you have ${el.value.length}).`;
    if (v.tooLong) return `Use no more than ${el.maxLength} characters.`;
    if (v.rangeUnderflow) return `Enter ${el.min} or more.`;
    if (v.rangeOverflow) return `Enter ${el.max} or less.`;
    return el.validationMessage;
}

function errorSlot(el, create) {
    const host = el.closest('label') ?? el.parentElement;
    let slot = host.querySelector('[data-error]');
    if (!slot && create) {
        slot = document.createElement('span');
        slot.dataset.error = '';
        slot.setAttribute('role', 'alert');
        slot.className = 'mt-1 block text-[0.7rem] text-destructive';
        host.appendChild(slot);
    }
    return slot;
}

function showFieldError(el, message) {
    const slot = errorSlot(el, Boolean(message));
    if (slot) {
        slot.textContent = message ?? '';
        slot.classList.toggle('hidden', !message);
    }
    if (message) el.setAttribute('aria-invalid', 'true');
    else el.removeAttribute('aria-invalid');
    if (!message) delete el.dataset.clientInvalid;
}

const isField = (el) => el instanceof HTMLElement && el.matches('input, select, textarea') && el.willValidate;
let focusedInvalid = false;

// Fires for form submits, checkValidity() and reportValidity(); suppress the bubble, show inline instead.
document.addEventListener('invalid', (e) => {
    e.preventDefault();
    e.target.dataset.clientInvalid = '';
    showFieldError(e.target, validationMessage(e.target));
    if (!focusedInvalid) {
        focusedInvalid = true;
        e.target.focus({ preventScroll: true });
        e.target.scrollIntoView({ block: 'center', behavior: 'smooth' });
        setTimeout(() => (focusedInvalid = false));
    }
}, true);

const recheck = (el) => showFieldError(el, el.validity.valid ? null : validationMessage(el));

// Once a field is flagged, re-check it live so the message disappears as soon as it is fixed.
['input', 'change'].forEach((type) => document.addEventListener(type, (e) => {
    const el = e.target;
    if (isField(el) && el.getAttribute('aria-invalid') === 'true') recheck(el);

    // Toggles can change which fields are required (e.g. "Create an account"): once Alpine has
    // updated the form, re-check fields flagged by the browser (server errors are left alone).
    if (el.type === 'checkbox' || el.type === 'radio') {
        setTimeout(() => el.form?.querySelectorAll('[aria-invalid="true"][data-client-invalid]').forEach(recheck));
    }
}, true));

// Check filled-in fields when leaving them; empty ones wait for submit so we don't nag.
document.addEventListener('focusout', (e) => {
    const el = e.target;
    if (isField(el) && el.value !== '' && el.type !== 'checkbox' && el.type !== 'radio') el.checkValidity();
}, true);

// Fields that came back with server errors get the invalid styling too.
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-error]:not(.hidden)').forEach((slot) => {
        slot.closest('label')?.querySelector('input, select, textarea')?.setAttribute('aria-invalid', 'true');
    });
});

document.addEventListener('alpine:init', () => {
    Alpine.store('toasts', {
        items: [],
        push(message, type = 'success') {
            const id = Date.now() + Math.random();
            this.items.push({ id, message, type });
            setTimeout(() => this.dismiss(id), 4200);
        },
        dismiss(id) {
            this.items = this.items.filter((t) => t.id !== id);
        },
    });

    Alpine.store('cart', { count: Number(document.body.dataset.cartCount || 0) });
    Alpine.store('wishlist', { count: Number(document.body.dataset.wishlistCount || 0) });
});

/** Add a frame (no lenses) to the bag from any product card; optionally in a frame size. */
window.addToBag = async (productId, finish = null, size = null) => {
    try {
        const res = await send(cartUrl(), { product_id: productId, finish, size });
        Alpine.store('cart').count = res.count;
        toast(res.message);
    } catch (e) {
        toast(e.message, 'error');
    }
};

/** Heart button: toggles a product in the wishlist and keeps the header count in sync. */
function wishlistButton(url, saved = false) {
    return {
        saved,
        busy: false,
        async toggle() {
            if (this.busy) return;
            this.busy = true;
            try {
                const res = await send(url, {});
                this.saved = res.saved;
                Alpine.store('wishlist').count = res.count;
                toast(res.message);
            } catch (e) {
                toast(e.message, 'error');
            } finally {
                this.busy = false;
            }
        },
    };
}

/** Header search with debounced product suggestions and keyboard navigation. */
function siteSearch(suggestUrl, initial = '') {
    return {
        q: initial,
        results: [],
        allUrl: null,
        open: false,
        active: -1,
        timer: null,
        input() {
            clearTimeout(this.timer);
            if (this.q.trim().length < 2) {
                this.results = [];
                this.open = false;
                return;
            }
            this.timer = setTimeout(() => this.fetch(), 200);
        },
        async fetch() {
            const term = this.q.trim();
            try {
                const res = await fetch(`${suggestUrl}?q=${encodeURIComponent(term)}`, { headers: { Accept: 'application/json' } });
                const body = await res.json();
                if (term !== this.q.trim()) return; // a newer keystroke superseded this request
                this.results = body.products ?? [];
                this.allUrl = body.all_url;
                this.active = -1;
                this.open = true;
            } catch {
                this.results = [];
            }
        },
        move(step) {
            if (!this.results.length) return;
            this.active = (this.active + step + this.results.length) % this.results.length;
        },
        choose(event) {
            if (this.active >= 0 && this.results[this.active]) {
                event.preventDefault();
                window.location = this.results[this.active].url;
            }
        },
    };
}

/* -------------------------------------------------------------------------- */
/* 360° spin viewer                                                           */
/* -------------------------------------------------------------------------- */

const SPIN_SECONDS = 40; // default seconds per full revolution while auto-rotating
const RESUME_AFTER_MS = 2500; // auto-rotation resumes this long after the user lets go

/**
 * 360° viewer with two sources:
 *  - photos: a turntable sequence, shown frame by frame (no blending, so no ghosting);
 *  - model:  a 3D wireframe of the frame drawn live on a canvas every animation
 *            frame, giving perfectly smooth rotation at any speed.
 * Auto-rotates, drag (mouse/touch) to spin, arrow keys to step, Space to pause,
 * only animates while on screen, and respects prefers-reduced-motion.
 */
function spinViewer(frames = [], model = null, options = {}) {
    return {
        frames,
        model,
        angle: options.startAngle ?? 30, // degrees, 0 = facing the viewer
        pitch: MODEL_VIEW.pitch, // degrees of tilt, changed by dragging up/down
        playing: options.autoplay ?? true,
        hoverOnly: options.hoverOnly ?? false,
        seconds: options.seconds ?? SPIN_SECONDS,
        hovering: false,
        dragging: false,
        resumeAt: 0,
        loaded: 0,
        preloading: false,
        visible: false,
        dirty: true,
        raf: null,
        last: 0,
        drag: null,
        suppressClick: false,
        observer: null,
        resizer: null,
        reducedMotion: window.matchMedia('(prefers-reduced-motion: reduce)').matches,

        get hasFrames() {
            return this.frames.length > 1;
        },
        get ready() {
            return !this.hasFrames || this.loaded >= this.frames.length;
        },
        get index() {
            const n = this.frames.length;
            return n ? ((Math.round((this.angle / 360) * n) % n) + n) % n : 0;
        },
        get src() {
            return this.frames[this.index];
        },
        get degrees() {
            return ((Math.round(this.angle) % 360) + 360) % 360;
        },

        init() {
            if (this.reducedMotion) this.playing = false;
            this.observer = new IntersectionObserver(([entry]) => {
                this.visible = entry.isIntersecting;
                this.dirty = true;
                if (this.visible && this.hasFrames && !this.hoverOnly) this.preload();
            }, { rootMargin: '200px' });
            this.observer.observe(this.$el);
            if (!this.hasFrames && this.$refs.canvas) {
                this.resizer = new ResizeObserver(() => (this.dirty = true));
                this.resizer.observe(this.$refs.canvas);
            }
            this.raf = requestAnimationFrame((t) => this.tick(t));
        },
        destroy() {
            cancelAnimationFrame(this.raf);
            this.observer?.disconnect();
            this.resizer?.disconnect();
        },
        preload() {
            if (this.preloading) return;
            this.preloading = true;
            this.frames.forEach((url) => {
                const img = new Image();
                img.onload = img.onerror = () => this.loaded++;
                img.src = url;
            });
        },
        active() {
            if (!this.visible || !this.playing || this.dragging || performance.now() < this.resumeAt) return false;
            return this.hoverOnly ? this.hovering : true;
        },
        tick(now) {
            const dt = this.last ? Math.min(now - this.last, 100) / 1000 : 0;
            this.last = now;
            if (this.active() && this.ready) {
                this.angle = (this.angle + (dt * 360) / this.seconds) % 360;
                this.dirty = true;
            }
            if (this.dirty && this.visible && !this.hasFrames) this.draw();
            this.raf = requestAnimationFrame((t) => this.tick(t));
        },
        enter() {
            this.hovering = true;
            if (this.hasFrames) this.preload();
        },
        leave() {
            this.hovering = false;
        },
        toggle() {
            this.playing = !this.playing;
            this.resumeAt = 0;
        },
        step(direction) {
            this.pause();
            this.angle += direction * (this.hasFrames ? 360 / this.frames.length : 10);
            this.dirty = true;
        },
        pause() {
            this.resumeAt = performance.now() + RESUME_AFTER_MS;
        },
        pointerDown(e) {
            if (e.button !== undefined && e.button !== 0) return;
            this.dragging = true;
            if (this.hasFrames) this.preload();
            this.drag = { x: e.clientX, y: e.clientY, angle: this.angle, pitch: this.pitch, moved: false };
            // Dragging across the full width turns the frame once around; dragging
            // the full height tilts it (3D model only — photos have a single row).
            const { width = 400, height = 300 } = e.currentTarget.getBoundingClientRect();
            const move = (ev) => {
                const dx = ev.clientX - this.drag.x;
                const dy = ev.clientY - this.drag.y;
                if (Math.abs(dx) > 5 || Math.abs(dy) > 5) this.drag.moved = true;
                this.angle = this.drag.angle + (dx / width) * 360;
                if (!this.hasFrames) {
                    this.pitch = Math.max(-MAX_PITCH, Math.min(MAX_PITCH, this.drag.pitch + (dy / height) * 180));
                }
                this.dirty = true;
            };
            const up = () => {
                if (this.drag?.moved) this.suppressClick = true;
                this.dragging = false;
                this.drag = null;
                this.pause();
                window.removeEventListener('pointermove', move);
                window.removeEventListener('pointerup', up);
                window.removeEventListener('pointercancel', up);
            };
            window.addEventListener('pointermove', move);
            window.addEventListener('pointerup', up);
            window.addEventListener('pointercancel', up);
        },
        clickCapture(e) {
            if (this.suppressClick) {
                e.preventDefault();
                e.stopPropagation();
                this.suppressClick = false;
            }
        },

        /** Draw the wireframe model at the current angle. */
        draw() {
            const canvas = this.$refs.canvas;
            if (!canvas || !this.model) return;
            this.dirty = false;
            drawFrameModel(canvas, this.model, this.angle, this.pitch);
        },
    };
}

/* -------------------------------------------------------------------------- */
/* Checkout: Bag → Delivery → Payment                                         */
/* -------------------------------------------------------------------------- */

function checkout(config) {
    return {
        step: config.step,
        pay: config.payment,
        country: config.country,
        postal: config.postal,
        estimate: null,
        busy: false,
        init() {
            if (this.country && this.postal) this.checkEstimate();
        },
        go(step) {
            if (step > this.step && this.step === 2 && !this.deliveryValid()) return;
            this.step = step;
            window.scrollTo({ top: 0, behavior: 'smooth' });
        },
        deliveryValid() {
            return this.sectionValid(this.$refs.delivery);
        },
        /** Checks every field in a section; each invalid one shows its message inline. */
        sectionValid(section) {
            const results = [...section.querySelectorAll('input, select, textarea')].map((el) => el.checkValidity());
            return results.every(Boolean);
        },
        async checkEstimate() {
            if (!this.country || this.postal.trim().length < 3) {
                this.estimate = null;
                return;
            }
            try {
                this.estimate = await send(config.estimateUrl, { region: this.country, postal: this.postal });
            } catch (e) {
                this.estimate = { status: 'invalid', message: e.message };
            }
            // A postal code in the wrong format for the chosen country is flagged on the field itself.
            const field = this.$refs.postal;
            if (!field) return;
            const message = this.estimate.status === 'invalid' ? this.estimate.message : '';
            field.setCustomValidity(message);
            showFieldError(field, message || null);
        },
        submit(event) {
            if (this.busy) return event.preventDefault();
            if (!this.deliveryValid()) {
                event.preventDefault();
                return this.go(2);
            }
            if (!this.sectionValid(this.$refs.payment)) return event.preventDefault();
            this.busy = true;
        },
    };
}

/* -------------------------------------------------------------------------- */
/* VisionMatch                                                                */
/* -------------------------------------------------------------------------- */

function visionMatch(config) {
    // Kept outside Alpine's reactive state: a proxied MediaStream can't be
    // assigned to video.srcObject and its methods throw "Illegal invocation".
    let mediaStream = null;
    let objectUrl = null;
    let tryOn = null; // the mounted 3D try-on (React)
    let scanRun = 0;

    return {
        step: 1,
        steps: config.steps,
        faceShapes: config.faceShapes,
        sizes: config.sizes,
        finishColors: config.finishColors,
        stream: false, // true while the live camera is running
        starting: false,
        img: null,
        scanning: false,
        scanText: '',
        scanError: null, // 'no-face' | 'unavailable'
        face: null, // measurements from the scan
        points: null, // measured landmarks for the overlay, in image pixels
        dims: { width: 1, height: 1 },
        faceShape: null,
        size: null,
        prefs: {},
        matches: [],
        unique: false,
        selected: null,
        finish: null,
        loading: false,
        get live() {
            return this.stream || !!this.img;
        },
        get title() {
            return ['Face Scan', 'Your Fit & Style', 'Your Matched Frames'][this.step - 1];
        },
        /** The frame size closest to the measured face width (null when the measurement looks wrong). */
        get suggested() {
            const width = this.face?.width;
            if (!width || width < 110 || width > 170 || !this.sizes.length) return null;
            return this.sizes.reduce((best, s) => (Math.abs(s.width - width) < Math.abs(best.width - width) ? s : best));
        },
        init() {
            // Release the camera when the visitor leaves the page.
            window.addEventListener('pagehide', () => this.stopCam());
        },
        destroy() {
            this.stopCam();
            this.setImage(null);
            tryOn?.unmount();
            tryOn = null;
        },
        async startCam() {
            if (this.starting || this.scanning) return;
            if (!navigator.mediaDevices?.getUserMedia) {
                return toast(window.isSecureContext
                    ? 'This browser does not support camera access — upload a portrait instead.'
                    : 'The camera needs a secure (HTTPS) connection — upload a portrait instead.', 'error');
            }
            this.starting = true;
            try {
                this.stopCam();
                mediaStream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user', width: { ideal: 1280 }, height: { ideal: 960 } }, audio: false });
                this.setImage(null);
                this.stream = true;
                const video = this.$refs.video;
                video.srcObject = mediaStream;
                await video.play().catch(() => {});
            } catch (e) {
                this.stopCam();
                toast(e?.name === 'NotAllowedError'
                    ? 'Camera permission was blocked — allow it in your browser, or upload a portrait instead.'
                    : 'Camera unavailable — upload a portrait or use the sample instead.', 'error');
            } finally {
                this.starting = false;
            }
        },
        stopCam() {
            mediaStream?.getTracks().forEach((t) => t.stop());
            mediaStream = null;
            if (this.$refs.video) this.$refs.video.srcObject = null;
            this.stream = false;
        },
        /** Swap the portrait (dropping any earlier scan), freeing the previous upload's memory. */
        setImage(src, isObjectUrl = false) {
            if (objectUrl && objectUrl !== src) URL.revokeObjectURL(objectUrl);
            objectUrl = isObjectUrl ? src : null;
            this.img = src;
            this.resetScan(false);
        },
        /** Forget the scan; with `clearImage`, the photo too. */
        resetScan(clearImage = true) {
            scanRun++;
            this.face = null;
            this.points = null;
            this.scanError = null;
            this.scanning = false;
            if (clearImage && this.img) this.setImage(null);
        },
        upload(event) {
            const input = event.target;
            const file = input.files?.[0];
            input.value = ''; // so picking the same file again still fires change
            if (!file || this.scanning) return;
            if (!file.type.startsWith('image/')) return toast('Please choose an image file (JPG, PNG or HEIC).', 'error');
            if (file.size > 15 * 1024 * 1024) return toast('That photo is over 15 MB — please choose a smaller one.', 'error');
            this.stopCam();
            this.setImage(URL.createObjectURL(file), true);
        },
        sample() {
            if (this.scanning) return;
            this.stopCam();
            this.setImage(config.sample);
        },
        async capture() {
            if (this.scanning) return;
            const video = this.$refs.video;
            if (this.stream) {
                if (!video?.videoWidth) return toast('The camera is still starting — try again in a moment.', 'error');
                // Freeze the frame mirrored, exactly as the preview shows it.
                const canvas = document.createElement('canvas');
                canvas.width = video.videoWidth;
                canvas.height = video.videoHeight;
                const ctx = canvas.getContext('2d');
                ctx.translate(canvas.width, 0);
                ctx.scale(-1, 1);
                ctx.drawImage(video, 0, 0);
                this.stopCam();
                this.setImage(canvas.toDataURL('image/jpeg', 0.9));
            }
            if (!this.img) return toast('Start the camera or upload a photo first.', 'error');
            await this.scan();
        },
        /** Measure the face in the browser: shape, width, PD and a suggested size. */
        async scan() {
            const run = ++scanRun;
            this.scanning = true;
            this.scanError = null;
            this.scanText = 'Loading the face model (first time only)…';
            try {
                await this.$nextTick();
                const photo = this.$refs.photo;
                await photo.decode();
                const { scanPortrait } = await import('./visionmatch-scan.ts');
                this.scanText = 'Measuring your face…';
                const result = await scanPortrait(photo, config.mediapipe);
                if (run !== scanRun) return; // the photo changed meanwhile
                if (!result.ok) {
                    this.scanError = result.reason;
                    return;
                }
                this.face = result.face;
                this.points = result.points;
                this.dims = { width: result.width, height: result.height };
                this.faceShape = result.face.shape;
                this.size = this.suggested?.code ?? this.size;
            } catch (e) {
                console.warn('Face scan failed:', e);
                if (run === scanRun) this.scanError = 'unavailable';
            } finally {
                if (run === scanRun) this.scanning = false;
            }
        },
        scanWarning() {
            if (!this.face) return '';
            if (this.face.turned) return 'Your head looks turned, so widths may read short — for the best estimate, scan a photo facing the camera.';
            if (this.face.small) return 'Your face is small in this photo — move closer or use a larger photo for a more precise estimate.';
            return 'Your irises weren’t clear, so sizes are estimated from the eye corners — a sharper, well-lit photo helps.';
        },
        shapeLabel(key) {
            return this.faceShapes.find((s) => s.key === key)?.label ?? '';
        },
        shapeWhy(key) {
            const shape = this.faceShapes.find((s) => s.key === key);
            return shape ? `${shape.label} faces: ${shape.why.charAt(0).toLowerCase()}${shape.why.slice(1)}.` : '';
        },
        /** A shape option that flatters the chosen face shape. */
        suitsFace(option) {
            const suits = this.faceShapes.find((s) => s.key === this.faceShape)?.suits ?? [];
            return (option.families ?? []).some((f) => suits.includes(f));
        },
        next() {
            this.size ??= this.suggested?.code ?? this.sizes.find((s) => s.code === 'M')?.code ?? this.sizes[0]?.code ?? null;
            this.step = 2;
            window.scrollTo({ top: 0, behavior: 'smooth' });
        },
        toggle(key, value) {
            this.prefs[key] = this.prefs[key] === value ? null : value;
        },
        canReveal() {
            return this.steps.every((s) => !s.required || this.prefs[s.key]);
        },
        async reveal(skip = false) {
            if (skip) this.prefs = {};
            if (!skip && !this.canReveal()) return toast('Please answer the required questions.', 'error');
            this.loading = true;
            try {
                const res = await send(config.matchesUrl, { preferences: this.prefs, face: { shape: this.faceShape, size: this.size } });
                this.matches = res.matches;
                this.unique = res.unique;
                this.step = 3;
                window.scrollTo({ top: 0, behavior: 'smooth' });
                if (this.matches[0]) this.select(this.matches[0], false);
                else this.selected = null;
            } catch (e) {
                toast(e.message, 'error');
            } finally {
                this.loading = false;
            }
        },
        restart() {
            tryOn?.unmount();
            tryOn = null;
            this.step = 1;
            this.prefs = {};
            this.faceShape = null;
            this.size = null;
            this.selected = null;
            this.matches = [];
            this.setImage(null);
        },
        /** Try a match on: switch to its own finish (or the one asked for) and bring the preview into view. */
        select(p, reveal = true) {
            this.selected = p;
            const wanted = this.prefs.finish ? this.finishColors[this.prefs.finish] : null;
            this.finish = wanted && p.finishes.includes(wanted) ? wanted : (p.finishes[0] ?? '#0F1113');
            if (!reveal) return;
            this.$nextTick(() => {
                const preview = document.getElementById('vm-try-on');
                const rect = preview?.getBoundingClientRect();
                if (rect && (rect.top < 64 || rect.bottom > window.innerHeight)) preview.scrollIntoView({ behavior: 'smooth', block: 'start' });
            });
        },
        /** Render the 3D try-on for the selected frame on the shopper's photo (x-effect re-runs it when any input changes). */
        renderTryOn() {
            // Read everything first, synchronously, so x-effect tracks it.
            const el = this.$refs.tryon;
            if (this.step !== 3 || !this.selected || !el) return;
            const props = JSON.parse(JSON.stringify({
                portrait: this.img || config.sample,
                glasses: this.selected.live,
                tryOn: this.selected.try_on,
                color: this.finish,
                frameWidthMm: this.sizes.find((s) => s.code === this.size)?.width ?? 138,
                mediapipeBase: config.mediapipe,
            }));
            import('./visionmatch-try-on.tsx')
                .then(({ mountTryOn }) => {
                    tryOn ??= mountTryOn(el);
                    tryOn.render(props);
                })
                .catch((e) => console.warn('3D try-on unavailable:', e));
        },
        finishName(color) {
            return Object.keys(this.finishColors).find((name) => this.finishColors[name].toLowerCase() === color.toLowerCase()) ?? color;
        },
    };
}

/* -------------------------------------------------------------------------- */
/* Charts                                                                     */
/* -------------------------------------------------------------------------- */

const css = (name) => getComputedStyle(document.documentElement).getPropertyValue(name).trim();

/** Render a line/bar chart from a server-provided series. */
function salesChart(series, options = {}) {
    return {
        chart: null,
        metric: options.metric ?? 'sales',
        init() {
            this.draw();
            this.$watch('metric', () => this.draw());
        },
        draw() {
            this.chart?.destroy();
            const labels = series.map((d) => d.day);
            const datasets = options.combo
                ? [
                      { type: 'bar', label: 'Orders', data: series.map((d) => d.orders), backgroundColor: css('--sage-soft'), yAxisID: 'r' },
                      { type: 'line', label: 'Gross', data: series.map((d) => d.sales), borderColor: css('--obsidian'), tension: 0.3, pointRadius: 0, yAxisID: 'l' },
                      { type: 'line', label: 'Net', data: series.map((d) => d.net), borderColor: css('--bronze'), tension: 0.3, pointRadius: 0, yAxisID: 'l' },
                  ]
                : [
                      {
                          type: 'line',
                          label: this.metric.toUpperCase(),
                          data: series.map((d) => d[this.metric]),
                          borderColor: css('--obsidian'),
                          backgroundColor: 'color-mix(in oklch, var(--bronze) 30%, transparent)',
                          fill: true,
                          tension: 0.3,
                          pointRadius: 0,
                      },
                  ];
            this.chart = new Chart(this.$refs.canvas, {
                data: { labels, datasets },
                options: {
                    maintainAspectRatio: false,
                    interaction: { mode: 'index', intersect: false },
                    plugins: { legend: { display: !!options.combo, labels: { boxWidth: 10, font: { size: 10 } } } },
                    scales: {
                        x: { grid: { display: false }, ticks: { font: { size: 10 }, maxTicksLimit: 10 } },
                        ...(options.combo
                            ? {
                                  l: { position: 'left', ticks: { font: { size: 10 } }, grid: { color: css('--border') } },
                                  r: { position: 'right', ticks: { font: { size: 10 } }, grid: { display: false } },
                              }
                            : { y: { ticks: { font: { size: 10 } }, grid: { color: css('--border') } } }),
                    },
                },
            });
        },
    };
}

Alpine.plugin(collapse);
Alpine.data('spinViewer', spinViewer);
Alpine.data('wishlistButton', wishlistButton);
Alpine.data('siteSearch', siteSearch);
Alpine.data('checkout', checkout);
Alpine.data('visionMatch', visionMatch);
Alpine.data('salesChart', salesChart);

window.Alpine = Alpine;
Alpine.start();
