<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    protected $fillable = ['order_id', 'reference', 'method', 'amount', 'fee', 'status', 'payer_account', 'payer_reference', 'refunded_at'];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'fee' => 'decimal:2',
            'refunded_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Payment $payment) {
            $payment->reference = $payment->reference ?: 'TX-'.strtoupper(bin2hex(random_bytes(4)));
        });
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function netPayout(): float
    {
        return (float) $this->amount - (float) $this->fee;
    }

    public function methodLabel(): string
    {
        return PaymentMethod::labelFor($this->method);
    }

    public static function feeFor(float $amount): float
    {
        $fee = config('store.processing_fee');

        return round($amount * $fee['percent'] / 100 + $fee['fixed'], 2);
    }
}
