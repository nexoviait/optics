<?php

namespace App\Listeners;

use App\Models\User;
use App\Services\Wishlist;
use Illuminate\Auth\Events\Login;

class MergeGuestWishlist
{
    public function __construct(private Wishlist $wishlist) {}

    public function handle(Login $event): void
    {
        if ($event->user instanceof User) {
            $this->wishlist->mergeGuestItems($event->user);
        }
    }
}
