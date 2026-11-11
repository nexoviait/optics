<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\LensSku;
use App\Models\Page;
use App\Models\PageSection;
use Illuminate\View\View;

class PageController extends Controller
{
    public function science(): View
    {
        return view('shop.science', [
            'materials' => LensSku::query()->orderBy('refractive_index')->get(),
            'newsletter' => PageSection::query()->forPage('home')->where('key', 'newsletter')->first(),
        ]);
    }

    public function show(Page $page): View
    {
        abort_unless($page->is_published, 404);

        return view('shop.page', ['page' => $page]);
    }
}
