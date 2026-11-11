<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\PageSection;
use App\Models\Product;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function index(): View
    {
        return view('shop.home', [
            'sections' => PageSection::query()->forPage('home')->where('is_enabled', true)->get(),
            'products' => Product::query()->active()->ordered()->get(),
        ]);
    }
}
