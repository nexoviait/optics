<?php

namespace Tests\Feature;

use App\Mail\OrderUpdate;
use App\Models\Order;
use App\Models\Product;
use App\Models\ShippingZone;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Seeders\TestSeeder;
use Tests\TestCase;

class OrderFlowTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    protected string $seeder = TestSeeder::class;

    private const ADDRESS = [
        'ship_name' => 'Ada Client', 'ship_phone' => '+41 22 310 4400', 'ship_line1' => 'Quai du Mont-Blanc 7',
        'ship_city' => 'Genève', 'ship_postal' => '1204', 'ship_country' => 'CH', 'customer_note' => 'Leave with concierge',
    ];

    private function customer(): User
    {
        return User::query()->where('role', User::ROLE_CUSTOMER)->firstOrFail();
    }

    private function admin(): User
    {
        return User::query()->where('role', User::ROLE_ADMIN)->firstOrFail();
    }

    private function placeOrder(User $customer, array $overrides = []): Order
    {
        $this->actingAs($customer);
        $this->postJson('/cart', ['product_id' => Product::query()->where('slug', 'apex-01')->value('id')])->assertOk();
        $this->post('/cart/checkout', ['payment_method' => 'wallet'] + $overrides + self::ADDRESS)->assertSessionHasNoErrors();

        return Order::query()->latest('id')->firstOrFail();
    }

    public function test_checkout_stores_address_estimate_history_and_emails_confirmation(): void
    {
        Mail::fake();
        $customer = $this->customer();

        $order = $this->placeOrder($customer);

        $this->assertSame('Quai du Mont-Blanc 7', $order->ship_line1);
        $this->assertSame('CH', $order->ship_country);
        $this->assertSame('Leave with concierge', $order->customer_note);
        $this->assertNotNull($order->delivery_from);
        $this->assertTrue($order->delivery_to->gte($order->delivery_from));
        $this->assertSame(['New'], $order->events()->pluck('status')->all());
        Mail::assertSent(OrderUpdate::class, fn (OrderUpdate $mail) => $mail->hasTo($customer->email) && $mail->order->is($order));

        $this->get(route('cart.confirmed', $order))->assertOk()
            ->assertSee('Your order is confirmed')
            ->assertSee('Quai du Mont-Blanc 7')
            ->assertSee($order->delivery_from->format('D j M'));
    }

    public function test_checkout_requires_a_valid_deliverable_address(): void
    {
        $this->actingAs($this->customer());
        $this->postJson('/cart', ['product_id' => Product::query()->value('id')]);

        $this->post('/cart/checkout', ['payment_method' => 'wallet'])
            ->assertSessionHasErrors(['ship_name', 'ship_phone', 'ship_line1', 'ship_city', 'ship_postal', 'ship_country']);
        $this->post('/cart/checkout', ['payment_method' => 'wallet', 'ship_postal' => 'ABC'] + self::ADDRESS)
            ->assertSessionHasErrors(['ship_postal' => 'Enter a valid postal code for Switzerland.']);

        ShippingZone::query()->update(['enabled' => false]);
        $this->post('/cart/checkout', ['payment_method' => 'wallet'] + self::ADDRESS)->assertSessionHasErrors('ship_country');
        $this->assertSame(0, Order::query()->where('ship_line1', 'Quai du Mont-Blanc 7')->count());
    }

    public function test_rendered_email_contains_order_and_signed_tracking_link(): void
    {
        $order = $this->placeOrder($this->customer());

        $html = (new OrderUpdate($order->fresh()))->render();

        $this->assertStringContainsString($order->order_number, $html);
        $this->assertStringContainsString('Quai du Mont-Blanc 7', $html);
        $this->assertStringContainsString('/track/'.$order->order_number.'?expires=', $html);
    }

    public function test_customer_sees_order_detail_but_not_other_customers_orders(): void
    {
        $customer = $this->customer();
        $order = $this->placeOrder($customer);

        $this->get(route('account'))->assertSee(route('orders.show', $order));
        $this->get(route('orders.show', $order))->assertOk()->assertSee('Order placed')->assertSee('Cancel this order');

        $other = User::factory()->create();
        $this->actingAs($other)->get(route('orders.show', $order))->assertNotFound();
        $this->actingAs($other)->post(route('orders.cancel', $order))->assertNotFound();
    }

    public function test_customer_can_cancel_new_order_which_restores_stock_and_refunds(): void
    {
        Mail::fake();
        $customer = $this->customer();
        $apex = Product::query()->where('slug', 'apex-01')->firstOrFail();
        $order = $this->placeOrder($customer);
        $this->assertSame($apex->stock - 1, $apex->fresh()->stock);

        $this->post(route('orders.cancel', $order))->assertRedirect(route('orders.show', $order));

        $order->refresh();
        $this->assertSame('Cancelled', $order->status);
        $this->assertNotNull($order->cancelled_at);
        $this->assertSame('Refunded', $order->payment->status);
        $this->assertSame($apex->stock, $apex->fresh()->stock);
        Mail::assertSent(OrderUpdate::class, 2);

        // A second attempt is refused.
        $this->post(route('orders.cancel', $order))->assertSessionHasErrors('status');
    }

    public function test_customer_cannot_cancel_once_in_the_lab(): void
    {
        $customer = $this->customer();
        $order = $this->placeOrder($customer);
        $this->actingAs($this->admin())->patch(route('admin.orders.status', $order), ['status' => 'Processing']);

        $this->actingAs($customer)->post(route('orders.cancel', $order))->assertSessionHasErrors('status');
        $this->assertSame('Processing', $order->fresh()->status);
    }

    public function test_shipping_requires_tracking_and_shows_courier_link(): void
    {
        Mail::fake();
        $customer = $this->customer();
        $order = $this->placeOrder($customer);
        $this->actingAs($this->admin());

        $this->patch(route('admin.orders.status', $order), ['status' => 'Shipped'])->assertSessionHasErrors('tracking_number');
        $this->patch(route('admin.orders.status', $order), ['status' => 'Shipped', 'carrier' => 'DHL Express', 'tracking_number' => 'JD014600001'])->assertSessionHasNoErrors();

        $order->refresh();
        $this->assertSame('Shipped', $order->status);
        $this->assertNotNull($order->shipped_at);
        $this->assertStringContainsString('JD014600001', $order->trackingUrl());
        Mail::assertSent(OrderUpdate::class, fn (OrderUpdate $mail) => $mail->order->status === 'Shipped');

        $this->actingAs($customer)->get(route('orders.show', $order))->assertSee('JD014600001')->assertSee('Track with DHL Express');
        $this->actingAs($this->admin())->get(route('admin.orders.show', $order))->assertOk()->assertSee('Quai du Mont-Blanc 7')->assertSee('Leave with concierge');
    }

    public function test_backward_admin_corrections_do_not_email_customer(): void
    {
        $order = $this->placeOrder($this->customer());
        $this->actingAs($this->admin())->patch(route('admin.orders.status', $order), ['status' => 'Processing']);

        Mail::fake();
        $this->patch(route('admin.orders.status', $order), ['status' => 'New', 'note' => 'Rx needs re-check']);

        Mail::assertNothingSent();
        $this->assertSame('Rx needs re-check', $order->events()->reorder()->latest('id')->value('note'));
    }

    public function test_public_tracking_lookup_and_signed_link(): void
    {
        $customer = $this->customer();
        $order = $this->placeOrder($customer);
        auth()->logout();

        $this->get(route('orders.track'))->assertOk();

        // Wrong email gets the same generic message as an unknown order.
        $this->post(route('orders.track.lookup'), ['order_number' => $order->order_number, 'contact' => 'nobody@example.com'])
            ->assertSessionHasErrors('order_number');
        $this->post(route('orders.track.lookup'), ['order_number' => 'RF-0', 'contact' => $customer->email])
            ->assertSessionHasErrors('order_number');

        $response = $this->post(route('orders.track.lookup'), ['order_number' => strtolower($order->order_number), 'contact' => strtoupper($customer->email)]);
        $response->assertRedirect();
        $this->get($response->headers->get('Location'))->assertOk()
            ->assertSee($order->order_number)
            ->assertSee('Genève')
            ->assertDontSee('Quai du Mont-Blanc 7')
            ->assertDontSee('Cancel this order');

        // Unsigned or tampered links are rejected.
        $this->get('/track/'.$order->order_number)->assertForbidden();
    }

    public function test_guest_returns_to_bag_after_signing_in(): void
    {
        $customer = $this->customer();
        $this->postJson('/cart', ['product_id' => Product::query()->value('id')]);
        $this->get('/cart')->assertOk()->assertSee('check out as a guest');

        $this->post('/login', ['email' => $customer->email, 'password' => 'password'])->assertRedirect(route('cart.index'));
    }

    public function test_checkout_prefills_last_delivery_address(): void
    {
        $customer = $this->customer();
        $this->placeOrder($customer);
        $this->postJson('/cart', ['product_id' => Product::query()->value('id')]);

        $this->get('/cart')->assertOk()->assertSee('value="Quai du Mont-Blanc 7"', false);
    }
}
