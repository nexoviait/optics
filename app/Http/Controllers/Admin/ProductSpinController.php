<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class ProductSpinController extends Controller
{
    /**
     * Replace the product's 360° sequence. Frames are ordered by their original
     * file names (natural sort), matching how turntable cameras number shots.
     */
    public function store(Request $request, Product $product): RedirectResponse
    {
        $request->validate([
            'frames' => ['required', 'array', 'min:8', 'max:120'],
            'frames.*' => ['image', 'max:2048'],
        ], [
            'frames.min' => 'Upload at least 8 frames for a smooth 360° spin (24–72 recommended).',
        ]);

        $files = collect($request->file('frames'))
            ->sort(fn (UploadedFile $a, UploadedFile $b) => strnatcasecmp($a->getClientOriginalName(), $b->getClientOriginalName()))
            ->values();

        DB::transaction(function () use ($product, $files) {
            $product->spinFrames()->get()->each->delete();

            foreach ($files as $position => $file) {
                $product->spinFrames()->create([
                    'path' => $file->store("products/spin/{$product->id}", 'public'),
                    'position' => $position,
                ]);
            }
        });

        ActivityLog::record("360° view updated ({$files->count()} frames)", $product);

        return back()->with('success', "360° view saved with {$files->count()} frames.");
    }

    public function destroy(Product $product): RedirectResponse
    {
        $product->spinFrames()->get()->each->delete();
        ActivityLog::record('360° view removed', $product);

        return back()->with('success', '360° frames removed.');
    }
}
