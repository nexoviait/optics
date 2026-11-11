<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\VisionSession;
use App\Models\VisionStep;
use App\Services\VisionMatcher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class VisionMatchController extends Controller
{
    public function index(): View
    {
        $since = now()->subDays(30);
        $sessions = VisionSession::query()->where('created_at', '>=', $since);
        $total = (clone $sessions)->count();
        $converted = (clone $sessions)->where('converted', true)->count();

        $topProduct = VisionSession::query()->with('topProduct')
            ->where('created_at', '>=', $since)->whereNotNull('top_product_id')
            ->select('top_product_id', DB::raw('COUNT(*) as c'))->groupBy('top_product_id')
            ->orderByDesc('c')->first();

        return view('admin.visionmatch', [
            'steps' => VisionStep::query()->orderBy('sort_order')->get(),
            'kpis' => [
                'sessions' => $total,
                'conversion' => $total ? round($converted / $total * 100, 1) : 0,
                'avg_score' => round((float) (clone $sessions)->avg('top_score'), 1),
                'top_match' => $topProduct?->topProduct?->name ?? '—',
            ],
            'recent' => VisionSession::query()->with('topProduct')->latest()->take(8)->get(),
            // Estimated face shapes of scanned shoppers (no measurements or photos are kept).
            'faceShapes' => collect(VisionMatcher::FACE_SHAPES)->map(fn (string $label, string $key) => [
                'label' => $label,
                'count' => (clone $sessions)->where('face_shape', $key)->count(),
            ])->values(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'steps' => ['required', 'array', 'min:1', 'max:12'],
            'steps.*.id' => ['nullable', 'integer'],
            'steps.*.key' => ['nullable', 'alpha_dash', 'max:30'],
            'steps.*.title' => ['required', 'string', 'max:120'],
            'steps.*.options' => ['required', 'string', 'max:255'],
            'steps.*.is_required' => ['boolean'],
        ]);

        DB::transaction(function () use ($data) {
            $keep = [];
            foreach (array_values($data['steps']) as $i => $step) {
                $model = VisionStep::query()->updateOrCreate(
                    ['id' => $step['id'] ?? null],
                    [
                        'key' => $step['key'] ?? null,
                        'title' => $step['title'],
                        'options' => $step['options'],
                        'is_required' => (bool) ($step['is_required'] ?? false),
                        'sort_order' => $i,
                    ],
                );
                $keep[] = $model->id;
            }
            VisionStep::query()->whereNotIn('id', $keep)->delete();
        });

        return back()->with('success', 'VisionMatch pathway published.');
    }
}
