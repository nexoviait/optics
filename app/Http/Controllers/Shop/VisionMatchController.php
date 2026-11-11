<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\FrameSize;
use App\Models\Product;
use App\Models\VisionSession;
use App\Models\VisionStep;
use App\Services\VisionMatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Find My Frames (VisionMatch): the face is measured in the browser; only the estimated
 * face shape, suggested size and the shopper's answers reach the server.
 */
class VisionMatchController extends Controller
{
    public function show(VisionMatcher $matcher): View
    {
        $products = Product::query()->active()->get();

        // The photo step is always first; the remaining steps are the optional curation questions.
        $steps = VisionStep::query()->orderBy('sort_order')->get()
            ->reject(fn (VisionStep $s) => $s->key === 'scan')
            ->map(function (VisionStep $step) use ($matcher, $products) {
                $key = $step->key ?: 'step'.$step->id;
                $options = collect($step->optionList())
                    ->filter(fn (string $label) => $matcher->optionAvailable($key, $label, $products))
                    ->map(fn (string $label) => [
                        'label' => $label,
                        'families' => $key === 'shape' ? VisionMatcher::shapeFamilies($label) : [],
                        'color' => $key === 'finish' ? VisionMatcher::finishColor($label) : null,
                    ])->values()->all();

                return ['key' => $key, 'title' => $step->title, 'required' => $step->is_required, 'options' => $options];
            })
            ->filter(fn (array $step) => $step['options'] !== [])
            ->values();

        return view('shop.visionmatch', [
            'steps' => $steps->all(),
            'finishColors' => VisionMatcher::FINISH_COLORS,
            'faceShapes' => collect(VisionMatcher::FACE_SHAPES)->map(fn (string $label, string $key) => [
                'key' => $key,
                'label' => $label,
                'suits' => VisionMatcher::SUITS[$key][0],
                'why' => VisionMatcher::SUITS[$key][1],
            ])->values()->all(),
            'sizes' => FrameSize::ordered()->map(fn (FrameSize $s) => ['code' => $s->code, 'width' => $s->width, 'lens' => $s->lens_width, 'bridge' => $s->bridge, 'temple' => $s->temple])->values()->all(),
        ]);
    }

    public function matches(Request $request, VisionMatcher $matcher): JsonResponse
    {
        $data = $request->validate([
            'preferences' => ['nullable', 'array', 'max:12'],
            'preferences.*' => ['nullable', 'string', 'max:80'],
            'face' => ['nullable', 'array'],
            'face.shape' => ['nullable', Rule::in(array_keys(VisionMatcher::FACE_SHAPES))],
            'face.size' => ['nullable', Rule::in(FrameSize::ordered()->pluck('code')->all())],
        ]);
        $prefs = collect($data['preferences'] ?? [])->filter()->all();
        $faceShape = $data['face']['shape'] ?? null;

        $ranked = $matcher->rank($prefs, $faceShape);
        $top = $ranked->first();

        $session = VisionSession::query()->create([
            'user_id' => $request->user()?->id,
            'preferences' => $prefs,
            'face_shape' => $faceShape,
            'size' => $data['face']['size'] ?? null,
            'top_product_id' => $top['product']->id ?? null,
            'top_score' => $top['score'] ?? null,
        ]);
        $request->session()->put('visionmatch.session', $session->id);

        return response()->json([
            'unique' => $prefs === [],
            'matches' => $ranked->map(fn (array $r) => $r['product']->toClientArray() + [
                'score' => $r['score'],
                'reasons' => $r['reasons'],
                'live' => $r['product']->liveTryOnSettings(),
            ])->all(),
        ]);
    }
}
