# Product gallery, fullscreen viewer and 360° view

The product page (`/product/{slug}`) is a React + TypeScript page served through
Inertia. It renders inside the normal storefront layout, so the header, footer,
cart badge, search and toasts are still the Blade + Alpine versions shared with
every other page.

## Adding product photos

Admin → Products → edit a product → **Gallery & Style Stories**.

1. Drop photos on the upload area (or click to choose). JPG, PNG or WebP, at
   least 400 × 400 px; **square 1080 × 1080 on a white background** works best.
   Pick the **view type** first. It's applied to that upload batch and can be
   changed per photo later.
2. Each photo is stored untouched, then a queued job (`ProcessProductImage`)
   creates WebP + JPEG copies at 1080, 720 and 480 px (never upscaled) plus a
   200 px thumbnail, and records the pixel size. Until it runs, the page shows
   the original.
3. Drag cards (or use the ↑ ↓ buttons) to reorder. Recommended order:
   **front → angled → side → on model → top → case**.
4. **Make primary** chooses the photo that leads the gallery (and is preloaded
   for fast first paint). Set alt text for each photo; it is read by screen
   readers and search engines.
5. Set **Placement → Style Stories** for lifestyle shots; they appear in the
   carousel further down the page instead of the gallery.

File names are SEO-friendly: `{brand}-{model}-{colour}-{view}-{n}-{random}.jpg`.

### Colour variants

Each colourway is its own product, linked by **Variant group**. Give every
colourway its own photos. Picking a colour on the product page swaps the
gallery without a full page reload (an Inertia visit), and the gallery resets
to the first photo.

## Adding a 360° view

Every product page shows a **360°** button (bottom-left of the main photo, next
to **Try On**). It shows the best source the product has:

1. **3D model (.glb)**: a real 3D view the shopper can turn any way and zoom.
2. **360° photos (ZIP)**: a full turn made of turntable photos.
3. **Neither**: a solid 3D frame built in the browser from the product's
   catalogue data (`app/Support/FrameModel.php` → `frameGlb.ts`), exported as a
   `.glb` in memory and shown in the same `<model-viewer>` stage as uploaded
   models (white background, soft shadow, drag, zoom, auto-rotate).
4. **If WebGL is unavailable**: the main product photo turned in 3D, swinging
   up to 28° left and right.

### How the generated frame reads the product

The generated model looks like the product only as far as its catalogue fields
describe it, so keep these accurate:

| Field | What it controls | Examples it understands |
|---|---|---|
| Shape | Lens outline and size (lens width, bridge) | soft / slim rectangle, square, bold / oversized, round, oval, panto, keyhole, cat-eye, aviator, navigator, shield, hexagon, geometric, browline |
| Frame type | Rim | full rim, half rim (browline), rimless |
| Material | Acetate or metal front, finish, gloss | "Crystal Gradient Acetate", "Havana Tortoise Acetate", "Translucent TR90", "Brushed Gold Titanium", "Matte Black Stainless Steel" |
| Colour name / colour | Finish words and, after "with", the sunglass lenses | "Rosé to Crystal Fade", "… with Green Gradient Lenses", "… with Blue Mirror Lenses" |
| Temple material | Temples, tips | "Stainless Steel" (silver, with acetate tips on acetate fronts), "Burgundy Acetate", "Rose Gold Metal & Acetate", "Silicone-Tipped TR90" (tips in the 2nd finish colour) |
| Finishes (1st colour) | Frame colour | |

It is still a model of the *kind* of frame, not a scan of the real one: shapes
are approximated and patterns (tortoise) are generated. For an exact match,
upload turntable photos (a phone and a turntable are enough) or a `.glb`; either
replaces the generated model automatically.

### 3D model (best)

In the same panel, **Upload 3D model**:

* Binary glTF (`.glb`): one file with its textures. Max 30 MB; keep it under
  10 MB so it loads quickly on phones (compress textures, e.g. with
  `gltf-transform optimize`).
* Real-world scale in metres, centred, front of the frame facing +Z.
* Get one from the frame supplier, a 3D-scanning / photogrammetry service, or a
  3D artist. Test it at <https://modelviewer.dev/editor/> before uploading.

It's stored as `storage/app/public/products/{id}/model/{slug}-{random}.glb`
(served as `model/gltf-binary`, cached for a year) and replaces any previous
model; **Remove** falls back to photos.

**Many models at once:** copy them into `public/models/` named by SKU, e.g.
`public/models/RF-SN-MAH.glb`. They show up straight away (no upload needed);
an admin upload takes priority. SKUs with characters other than letters,
digits, `.`, `_` and `-` can't use the folder. Browsers cache folder files for a
week, so if you replace one, shoppers may see the old model for a while; an
admin upload gets a new file name and shows instantly.

**AR ("View in your space"):** on phones and tablets that support it, a button
places the frame in the room: Android (Scene Viewer / WebXR) and iPhone/iPad
(Quick Look). iPhones convert the `.glb` themselves; for best results add an
Apple `.usdz` of the same model as `public/models/{sku}.usdz`. AR needs the site
on a public HTTPS address (it won't work on `localhost` from a phone).

It's rendered with Google's [`<model-viewer>`](https://modelviewer.dev)
(`ModelViewer3D.tsx`), whose ~1 MB script is only downloaded when the shopper
opens the 360° view (never from a third-party CDN). It auto-rotates at 30°/s
until the shopper drags (resuming 1 s after they let go), supports pinch /
scroll zoom, and has a pause button. If the model or WebGL fails, the photo
view takes over.

### 360° photos

In the same panel, **Upload 360° ZIP**:

* 24–36 photos taken at equal angles on a turntable (8 minimum, 72 maximum).
* Transparent PNG or WebP looks best; JPG works too.
* Name them in order (`001.png … 036.png`). Frames are sorted by file name
  (natural order, so `frame-10` comes after `frame-2`). Folders and
  `__MACOSX` files are ignored.
* Max 50 MB per ZIP.

The `ExtractSpinSet` job converts frames to WebP (max 1080 px) under
`storage/app/public/products/{id}/spin/…` and replaces any previous set. A set
needs 8+ frames (the older per-frame upload in the "360° view" panel still
works too).

The photo views auto-rotate (12 s per turn / 9 s per swing), pause while the shopper drags
or steps and resume 2.5 s later, glide with inertia after a drag, and have a
small play/pause button. Keys: ← → step, Space pauses. Auto rotation is off for
`prefers-reduced-motion`.

(The Blade product cards still use the drawn 3D frame model from
`app/Support/FrameModel.php` for their hover spin.)

## The queue

Image processing and ZIP extraction are queued (`QUEUE_CONNECTION=database`).
Keep a worker running:

```bash
php artisan queue:work
```

Photos already in the database (or added outside the admin) can be processed
directly:

```bash
php artisan products:process-images          # only photos without renditions
php artisan products:process-images --all    # regenerate everything
php artisan products:process-images --queue  # hand the work to the queue
```

## Demo data

`GallerySampleSeeder` adds 5 drawn placeholder photos (front, angle, side, on
model, case) and a 24-frame 360° set to a product. It is **not** part of
`DatabaseSeeder`, because it would put placeholders on a real product.

```bash
GALLERY_SAMPLE_PRODUCT=havana-keyhole php artisan db:seed --class=GallerySampleSeeder
```

## Configuration

| Setting | Where | Default |
|---|---|---|
| Desktop gallery layout: `grid` (first photo full width, rest in 2 columns) or `thumbnails` (main photo + strip) | `GALLERY_LAYOUT` in `.env` | `grid` |
| CDN base for product media | `MEDIA_URL` in `.env` | serve from this app |
| Rendition widths / thumbnail size | `config/store.php` → `image_widths`, `image_thumb` | 1080, 720, 480 / 200 |
| Trust badges (exchange / warranty days) | Admin → Settings | 14 / 365 |
| Gallery strings | `lang/en/gallery.php` | |

Renditions, 360° frames and build assets are served with
`Cache-Control: max-age=31536000, immutable` (their names are unique); other
images get one week (`public/.htaccess`).

## How it fits together

| Piece | File |
|---|---|
| Page data, JSON-LD, LCP preload | `app/Http/Controllers/Shop/ProductController.php` → `resources/views/inertia.blade.php` |
| Image API shape | `app/Http/Resources/ProductImageResource.php` |
| Upload / reorder / primary / ZIP | `app/Services/ProductImageService.php`, `app/Http/Controllers/Admin/ProductImageController.php` |
| Renditions | `app/Jobs/ProcessProductImage.php` |
| 360° extraction | `app/Jobs/ExtractSpinSet.php`, `app/Models/ProductSpinSet.php` |
| Gallery, viewer, zoom, 360° | `resources/js/components/product-gallery/` |
| Try-on — model photo | `resources/js/components/try-on/` |
| Try-on — live camera | `resources/js/components/try-on/live/` |
| Admin manager | `resources/js/components/admin/ProductImagesManager.tsx` |

### Viewer modes

The fullscreen viewer has a **Photos** mode plus pluggable `ViewerMode`s
(`ImageViewerModal.tsx`): `{ id, label, icon, render() }`. 360° and Try On are
modes; a future WebGL/GLB viewer (e.g. `<model-viewer>`) can be added the same
way without touching the gallery. Each mode gets its own URL hash
(`#view-3`, `#view-360`, `#view-try-on`), so the back button closes the viewer
and views can be linked to.

## Try On

The **Try On** viewer has two tabs, both showing the product's 3D frame (the
same model as the 360° view, so a rosé-to-crystal gradient, steel temples or
tortoise look the same everywhere). Everything runs in the browser; photos and
video are never uploaded.

### Model photo

A portrait (`public/images/face.jpg`) with the frame fitted to the eyes:

1. MediaPipe finds the pupils, the irises and the head pose in the photo.
2. The frame is centred between the pupils (sitting 2.5 mm low, as opticians
   fit it), turned with the head, and true to size: the selected frame size's
   width × px/mm from the iris ruler. The **Size** buttons under the photo
   switch between sizes so the difference shows on the face.
3. A perspective camera from a typical selfie distance makes the temples recede
   into the head; they fade out towards the ears. Lenses and crystal acetate are
   kept see-through on skin, and a soft shadow grounds the frame.
4. Drag or pinch the frame (or use the arrow and +/− keys) to adjust; buttons,
   keys, size changes and reset glide into place.

It falls back, in order: an uploaded **try-on photo** (Admin → product →
"Try-on image", a transparent PNG of the real frame) is shown flat instead of
the 3D frame; without WebGL the drawn 2D frame is used; if no face is found the
frame waits where eyes usually are, to be dragged into place.

The page, its scripts, MediaPipe's files and the portrait must come from the
same address the shopper opened (browsers block cross-origin model files and
photo pixels). Don't set `ASSET_URL` to a fixed host: Laravel then builds asset
URLs from the current request, so `php artisan serve` (`127.0.0.1:8000`) and
XAMPP (`localhost/optics/public`) both work.

### Live camera

Shoppers can try the frame on their own face in real time, in 3D.

1. **MediaPipe Face Landmarker** (self-hosted WASM, no CDN) tracks 478 face
   landmarks and the full 3D head-pose matrix at every video frame.
2. **Iris ruler**: the visible iris diameter is 11.7 mm in almost every adult,
   so the system divides the detected iris size in pixels by 11.7 to get
   _pixels per millimetre_ on the face.
3. **True-to-size positioning**: the frame's real width (from the selected size)
   × px/mm gives the correct pixel scale. The admin can override scale and
   height offset per product (Admin → Products → edit → "Live camera try-on").
4. **Glasses**: if the product has a `.glb` model, that is loaded with
   `GLTFLoader`; otherwise the generated solid frame (`buildTryOnGlasses`, from
   the 360° model) is used. `flip: true` turns a model that faces backwards.
5. **Head occluder**: an invisible sphere writes depth, hiding the far temple
   when the head turns.
6. **One Euro filter** smooths position (x, y, scale) and orientation
   (quaternion) — no jitter at rest, no lag while turning.
7. **Face-width measurement**: 30 frontal frames are collected while the shopper
   looks at the camera; the median determines the recommended size (S / M / L /
   XL), and a "Use size X" button selects it on the page.
8. **Compare Looks**: up to 4 try-on snapshots (product + colour + date) stored
   in `sessionStorage` — never uploaded, gone when the tab closes.
9. **Save photo**: a mirrored JPEG is downloaded locally.

### Admin settings (per product)

| Field | Range | Default | Effect |
|---|---|---|---|
| `try_on_flip` | bool | false | Rotate the GLB model 180° around Y |
| `try_on_scale` | 0.7 – 1.3 | 1.0 | Multiply the computed frame width |
| `try_on_offset_y` | −15 – 15 mm | 0 | Move the frame up (+) or down (−) |

Tune these by putting the product in live try-on and adjusting the sliders,
then saving the values into the product form. Both tabs use them.

### Features not included

- **Selfie Segmentation / background blur** — adds a ~6 MB model and 20 ms/frame
  of GPU work for no fitting benefit; a plain mirror background reads better.
- **Remove Glasses (AI inpainting)** — needs a diffusion model (server-side or
  ~500 MB in-browser). Quality is unreliable; the "before" is just the plain
  camera feed with no glasses, which every shopper already has.

### Key files

| File | Purpose |
|---|---|
| `components/try-on/live/faceTracker.ts` | MediaPipe singleton, GPU → CPU fallback |
| `components/try-on/live/measure.ts` | Iris ruler, face width, size recommendation |
| `components/try-on/live/oneEuro.ts` | One Euro filter for position and quaternion |
| `face-fit.ts` | Photo face fitting: pupils, landmarks, head pose |
| `components/try-on/stage3d.ts` | WebGL layer shared by both tabs; photo placement maths |
| `components/try-on/PhotoTryOn3D.tsx` | Model-photo try-on (lazy-loaded) |
| `components/try-on/TryOnStage.tsx` | Drawn 2D / uploaded-photo fallback |
| `components/try-on/live/glasses3d.ts` | Try-on glasses from the 360° model, or the GLB; head occluder |
| `components/try-on/live/CompareLooks.tsx` | sessionStorage snapshot grid |
| `components/try-on/live/LiveTryOn.tsx` | Live camera try-on (lazy-loaded) |

## Find My Frames (VisionMatch)

`/visionmatch` takes the shopper from a photo to matched frames in three steps.

1. **Scan.** A camera capture, uploaded photo or the sample portrait is
   measured in the browser with MediaPipe (`visionmatch-scan.ts`,
   `lib/faceAnalysis.ts`). It estimates face width and PD (the iris is the ruler),
   a face shape (oval, round, square, heart, oblong or diamond, from facial
   proportions) and a suggested frame size. The measured lines are drawn on the
   photo. It warns when the head is turned, the face is small in the photo or the
   irises aren't clear. If no face is found, the shopper is asked for another
   photo; without face scanning, they can continue and choose a shape.
2. **Curate.** The shopper confirms or changes the face shape and size, then
   answers the optional questions from Admin → VisionMatch. Shape options that
   flatter the face shape are marked "Suits your face".
3. **Matches.** Frames are ranked server-side (`VisionMatcher`) and tried on in 3D
   on the shopper's own photo, at the chosen size; Add to Bag and Customize
   Lenses carry that size.

**Scoring** (out of 99, the same frame always scores the same):

| Signal | Points |
|---|---|
| Base | 60 |
| Frame shape suits the face shape (optician guidance, e.g. angular frames for a round face) | +24 · neutral +8 · shapes that repeat the face's lines −6 |
| Chosen frame shape | +10 |
| Chosen material | +5 |
| Chosen finish (same colour family) | +4 |
| Fit profile (frame made for that gender, or unisex) | +3 |

Equal scores go to the more viewed frame. Each match lists the reasons that
applied.

**Questions are read by meaning.** An option naming "square", "aviator" or
"rimless" matches those frame-shape families; "titanium", "acetate" or "alloy"
the material; a finish matches by colour family (black, grey, brown, gold, pink…).
Options that no active frame matches are hidden, so answers never lead to
nothing.

**Privacy.** The photo never leaves the browser. A session stores the answers,
the estimated face shape and the suggested size — no measurements or images. The
admin page shows the face shapes of the last 30 days.

## Tests

```bash
php artisan test         # backend: uploads, renditions, reorder, primary, variants, ZIP, JSON-LD, try-on settings, frame model,
                         #          Find My Frames matching
npm test                 # Vitest: viewer open/close, keyboard, focus, variant reset, 360° drag, generated 3D frame,
                         #         One Euro filter, iris ruler, size recommendation, CompareLooks,
                         #         try-on placement, try-on glasses, WebGL fallback, face analysis
npm run typecheck        # strict TypeScript
```
