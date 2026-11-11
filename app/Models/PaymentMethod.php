<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class PaymentMethod extends Model
{
    protected $fillable = ['code', 'name', 'type', 'description', 'instructions', 'account_number', 'logo_path', 'initial_status', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /** @param Builder<self> $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true)->orderBy('sort_order')->orderBy('id');
    }

    public function logoUrl(): ?string
    {
        return $this->logo_path ? Storage::disk('public')->url($this->logo_path) : null;
    }

    public function typeLabel(): string
    {
        return config("store.payment_method_types.{$this->type}", ucfirst($this->type));
    }

    /** Checkout methods live in the table; concierge-only methods stay in config. */
    public static function labelFor(string $code): string
    {
        return static::lookup($code)?->name ?? config("store.payment_methods.{$code}.label", ucfirst($code));
    }

    public static function statusFor(string $code): string
    {
        return static::lookup($code)?->initial_status ?? config("store.payment_methods.{$code}.status", 'Pending');
    }

    private static function lookup(string $code): ?self
    {
        return once(fn () => static::query()->get()->keyBy('code'))->get($code);
    }
}
