<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\FitAdvisor;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FitAdvisorController extends Controller
{
    public function show(): View
    {
        return view('shop.fit-advisor', [
            'featured' => Product::query()->active()->ordered()->take(4)->get(),
            'result' => null,
        ]);
    }

    public function recommend(Request $request, FitAdvisor $advisor): View
    {
        $data = $request->validate([
            'preferences' => ['required', 'string', 'min:10', 'max:1500'],
        ], ['preferences.min' => 'Please describe your fit in at least 10 characters.']);

        return view('shop.fit-advisor', [
            'featured' => collect(),
            'result' => $advisor->recommend($data['preferences']),
        ]);
    }
}
