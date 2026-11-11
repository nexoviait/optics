<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    protected $fillable = [
        'order_id', 'product_id', 'name', 'finish', 'frame_size', 'unit_price', 'qty',
        'power_type', 'lens', 'rx_mode', 'prescription_id',
    ];

    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:2',
            'qty' => 'integer',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function prescription(): BelongsTo
    {
        return $this->belongsTo(Prescription::class);
    }
}
