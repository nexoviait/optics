<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShippingZone extends Model
{
    protected $fillable = ['name', 'courier', 'countries', 'transit_min', 'transit_max', 'rate_label', 'holidays', 'enabled', 'sort_order'];

    protected function casts(): array
    {
        return [
            'countries' => 'array',
            'holidays' => 'array',
            'enabled' => 'boolean',
            'transit_min' => 'integer',
            'transit_max' => 'integer',
        ];
    }
}
