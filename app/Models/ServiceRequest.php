<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceRequest extends Model
{
    public const TYPES = [
        'consultation' => 'Optician consultation',
        'home_trial' => 'Home trial box',
    ];

    public const STATUSES = ['New', 'Scheduled', 'Completed', 'Cancelled'];

    protected $fillable = ['type', 'user_id', 'name', 'email', 'preferred_date', 'product_ids', 'status', 'notes'];

    protected function casts(): array
    {
        return [
            'preferred_date' => 'date',
            'product_ids' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }
}
