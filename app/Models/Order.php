<?php

namespace App\Models;

use App\Support\Settings;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;

class Order extends Model
{
    /**
     * Status, tracking and fulfilment timestamps are deliberately not mass
     * assignable: they only change through App\Services\OrderFulfilment.
     */
    protected $fillable = [
        'user_id', 'email', 'status', 'subtotal', 'member_discount', 'coupon_code', 'gift_discount',
        'total', 'payment_method', 'channel',
        'ship_name', 'ship_phone', 'ship_line1', 'ship_line2', 'ship_city', 'ship_postal', 'ship_country',
        'customer_note', 'delivery_from', 'delivery_to',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'member_discount' => 'decimal:2',
            'gift_discount' => 'decimal:2',
            'total' => 'decimal:2',
            'delivery_from' => 'date',
            'delivery_to' => 'date',
            'shipped_at' => 'datetime',
            'delivered_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // Order numbers continue the historic RF-48300 sequence.
        static::created(function (Order $order) {
            $order->order_number = Settings::get('order_prefix', 'RF-').(48300 + $order->id);
            $order->saveQuietly();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'order_number';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class)->latestOfMany();
    }

    public function events(): HasMany
    {
        return $this->hasMany(OrderEvent::class)->orderBy('created_at')->orderBy('id');
    }

    public function isGuest(): bool
    {
        return $this->user_id === null;
    }

    public function customerName(): string
    {
        return $this->user?->name ?? $this->ship_name ?? 'Guest';
    }

    /** Where order emails go: the email given at checkout, else the account's. */
    public function contactEmail(): ?string
    {
        return $this->email ?? $this->user?->email;
    }

    public function isCancelled(): bool
    {
        return $this->status === 'Cancelled';
    }

    /** Customers may cancel until an optician starts work on the order. */
    public function isCancellableByCustomer(): bool
    {
        return $this->status === 'New';
    }

    public function isPaid(): bool
    {
        return in_array($this->payment?->status, ['Succeeded', 'Refunded'], true);
    }

    public function statusLabel(): string
    {
        return config("store.order_status_copy.{$this->status}.0", $this->status);
    }

    public function statusDescription(): string
    {
        return config("store.order_status_copy.{$this->status}.1", '');
    }

    /**
     * Fulfilment steps with the time each was reached. Orders placed before
     * status history existed fall back to their creation date.
     *
     * @return list<array{status: string, label: string, description: string, reached: bool, current: bool, at: ?Carbon}>
     */
    public function timeline(): array
    {
        $pipeline = config('store.order_pipeline');
        $times = $this->events->groupBy('status')->map(fn ($group) => $group->first()->created_at);
        $reachedIndex = $this->isCancelled()
            ? (collect($pipeline)->keys()->filter(fn (int $i) => $times->has($pipeline[$i]))->max() ?? 0)
            : array_search($this->status, $pipeline, true);

        return collect($pipeline)->map(fn (string $status, int $i) => [
            'status' => $status,
            'label' => config("store.order_status_copy.{$status}.0", $status),
            'description' => config("store.order_status_copy.{$status}.1", ''),
            'reached' => $reachedIndex !== false && $i <= $reachedIndex,
            'current' => ! $this->isCancelled() && $status === $this->status,
            'at' => $times[$status] ?? ($i === 0 ? $this->created_at : null),
        ])->all();
    }

    public function hasShippingAddress(): bool
    {
        return filled($this->ship_line1);
    }

    /** @return list<string> */
    public function shippingAddressLines(): array
    {
        return array_values(array_filter([
            $this->ship_name,
            $this->ship_line1,
            $this->ship_line2,
            trim("{$this->ship_postal} {$this->ship_city}"),
            config("store.delivery_regions.{$this->ship_country}.name", $this->ship_country),
        ]));
    }

    /** Courier tracking page, when the carrier publishes one. */
    public function trackingUrl(): ?string
    {
        $template = config('store.carriers')[$this->carrier] ?? null;

        return $template && $this->tracking_number
            ? str_replace('{number}', rawurlencode($this->tracking_number), $template)
            : null;
    }

    /** Signed link to this order's tracking page that works without signing in. */
    public function publicTrackingUrl(): string
    {
        return URL::temporarySignedRoute('orders.track.show', now()->addDays((int) config('store.tracking_link_days')), ['order' => $this]);
    }

    public function nextStatus(): ?string
    {
        $pipeline = config('store.order_pipeline');
        $index = array_search($this->status, $pipeline, true);

        return $index === false ? null : ($pipeline[$index + 1] ?? null);
    }

    public function paymentLabel(): string
    {
        return PaymentMethod::labelFor($this->payment_method);
    }
}
