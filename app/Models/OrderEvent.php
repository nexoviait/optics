<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One entry in an order's status history. */
class OrderEvent extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['status', 'note', 'user_id'];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
