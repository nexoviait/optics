<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\Wishlist;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WishlistController extends Controller
{
    public function __construct(private Wishlist $wishlist) {}

    public function index(): View
    {
        return view('shop.wishlist', ['products' => $this->wishlist->products()]);
    }

    public function toggle(Request $request, Product $product): JsonResponse|RedirectResponse
    {
        abort_unless($product->status === 'active', 404);

        $saved = $this->wishlist->toggle($product);
        $message = $saved ? "{$product->name} saved to your wishlist" : "{$product->name} removed from your wishlist";

        if ($request->expectsJson()) {
            return response()->json(['saved' => $saved, 'count' => $this->wishlist->count(), 'message' => $message]);
        }

        return back()->with('success', $message);
    }
}
