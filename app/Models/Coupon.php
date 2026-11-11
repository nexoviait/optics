<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Coupon extends Model
{
    protected $fillable = ['code', 'type', 'value', 'is_active', 'expires_at'];

    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
            'is_active' => 'boolean',
            'expires_at' => 'datetime',
        ];
    }

    protected function setCodeAttribute(string $value): void
    {
        $this->attributes['code'] = strtoupper(trim($value));
    }

    /** Find a currently redeemable coupon by its (case-insensitive) code. */
    public static function findValid(?string $code): ?self
    {
        if (! $code) {
            return null;
        }

        return static::query()
            ->where('code', strtoupper(trim($code)))
            ->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->first();
    }

    public function discountFor(float $amount): float
    {
        $discount = $this->type === 'fixed' ? (float) $this->value : round($amount * (float) $this->value / 100);

        return min($amount, $discount);
    }

    public function label(): string
    {
        return $this->type === 'fixed' ? '$'.number_format((float) $this->value, 2).' off' : rtrim(rtrim((string) $this->value, '0'), '.').'% off';
    }
}
