<?php

namespace App\Services;

use App\Models\Product;
use App\Models\User;
use App\Models\WishlistItem;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Collection;

/**
 * Wishlist stored on the account for signed-in clients and in the session for
 * guests; the guest list is merged into the account on login.
 */
class Wishlist
{
    private const SESSION_KEY = 'wishlist.product_ids';

    /** @var list<int>|null */
    private ?array $ids = null;

    public function __construct(private Session $session, private Guard $auth) {}

    /** @return list<int> */
    public function ids(): array
    {
        if ($this->ids !== null) {
            return $this->ids;
        }

        $user = $this->auth->user();

        return $this->ids = $user
            ? $user->wishlistItems()->pluck('product_id')->map(fn ($id) => (int) $id)->all()
            : array_values(array_map('intval', $this->session->get(self::SESSION_KEY, [])));
    }

    public function has(int $productId): bool
    {
        return in_array($productId, $this->ids(), true);
    }

    public function count(): int
    {
        return count($this->ids());
    }

    /** Add or remove a product; returns true when it is now saved. */
    public function toggle(Product $product): bool
    {
        $saved = ! $this->has($product->id);
        $user = $this->auth->user();

        if ($user) {
            $saved
                ? $user->wishlistItems()->firstOrCreate(['product_id' => $product->id])
                : $user->wishlistItems()->where('product_id', $product->id)->delete();
        } else {
            $ids = collect($this->ids());
            $ids = $saved ? $ids->push($product->id)->unique() : $ids->reject(fn (int $id) => $id === $product->id);
            $this->session->put(self::SESSION_KEY, $ids->values()->all());
        }

        $this->ids = null;

        return $saved;
    }

    /** @return Collection<int, Product> */
    public function products(): Collection
    {
        return Product::query()->active()->whereIn('id', $this->ids())->ordered()->get();
    }

    public function mergeGuestItems(User $user): void
    {
        foreach ($this->session->pull(self::SESSION_KEY, []) as $productId) {
            if (Product::query()->whereKey($productId)->exists()) {
                WishlistItem::query()->firstOrCreate(['user_id' => $user->id, 'product_id' => (int) $productId]);
            }
        }

        $this->ids = null;
    }
}
