<?php

namespace Tests\Feature;

use App\Mail\OrderUpdate;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\Seeders\TestSeeder;
use Tests\TestCase;

class GuestCheckoutTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    protected string $seeder = TestSeeder::class;

    private const DETAILS = [
        'payment_method' => 'wallet', 'email' => 'Guest.Buyer@Example.com',
        'ship_name' => 'Grace Guest', 'ship_phone' => '+41 79 555 0101', 'ship_line1' => 'Quai du Mont-Blanc 7',
        'ship_city' => 'Genève', 'ship_postal' => '1201', 'ship_country' => 'CH',
    ];

    private function addToBag(): Product
    {
        $product = Product::query()->where('slug', 'apex-01')->firstOrFail();
        $this->postJson('/cart', ['product_id' => $product->id])->assertOk();

        return $product;
    }

    public function test_guest_can_order_without_an_account(): void
    {
        Mail::fake();
        $product = $this->addToBag();
        $stock = $product->stock;

        $response = $this->post('/cart/checkout', self::DETAILS);

        $order = Order::query()->latest('id')->firstOrFail();
        $response->assertRedirect(route('cart.confirmed', $order));
        $this->assertGuest();
        $this->assertNull($order->user_id);
        $this->assertSame('guest.buyer@example.com', $order->email);
        $this->assertSame('Grace Guest', $order->customerName());
        $this->assertSame($stock - 1, $product->fresh()->stock);
        Mail::assertSent(OrderUpdate::class, fn (OrderUpdate $mail) => $mail->hasTo('guest.buyer@example.com'));
        $this->assertSame(0, User::query()->where('email', 'guest.buyer@example.com')->count());

        $this->get(route('cart.confirmed', $order))->assertOk()
            ->assertSee('Thank you, Grace')
            ->assertSee('guest.buyer@example.com')
            ->assertSee('/track/'.$order->order_number, false);
    }

    public function test_guest_confirmation_is_private_to_the_ordering_session(): void
    {
        $this->addToBag();
        $this->post('/cart/checkout', self::DETAILS);
        $order = Order::query()->latest('id')->firstOrFail();

        // A different visitor (fresh session) cannot open it.
        $this->flushSession();
        $this->get(route('cart.confirmed', $order))->assertNotFound();
        $this->actingAs(User::factory()->create())->get(route('cart.confirmed', $order))->assertNotFound();
    }

    public function test_guest_can_track_with_order_number_and_email(): void
    {
        $this->addToBag();
        $this->post('/cart/checkout', self::DETAILS);
        $order = Order::query()->latest('id')->firstOrFail();

        $response = $this->post(route('orders.track.lookup'), ['order_number' => $order->order_number, 'contact' => 'GUEST.buyer@example.com']);

        $this->get($response->headers->get('Location'))->assertOk()->assertSee($order->order_number)->assertDontSee('Quai du Mont-Blanc 7');
    }

    public function test_guest_can_create_an_account_while_checking_out(): void
    {
        $this->addToBag();

        $this->post('/cart/checkout', self::DETAILS + ['create_account' => '1', 'password' => 'lens-pass-42'])->assertSessionHasNoErrors();

        $user = User::query()->where('email', 'guest.buyer@example.com')->firstOrFail();
        $order = Order::query()->latest('id')->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertSame(User::ROLE_CUSTOMER, $user->fresh()->role);
        $this->assertSame('Grace Guest', $user->name);
        $this->assertSame('+41 79 555 0101', $user->phone);
        $this->assertSame('Genève', $user->city);
        $this->assertTrue(Hash::check('lens-pass-42', $user->password));
        $this->assertSame($user->id, $order->user_id);
        $this->get(route('orders.show', $order))->assertOk();
    }

    public function test_account_creation_needs_a_password_and_a_free_email(): void
    {
        $this->addToBag();
        $existing = User::query()->where('role', User::ROLE_CUSTOMER)->firstOrFail();
        $orders = Order::query()->count();

        $this->post('/cart/checkout', self::DETAILS + ['create_account' => '1'])->assertSessionHasErrors('password');
        $this->post('/cart/checkout', ['email' => $existing->email, 'create_account' => '1', 'password' => 'lens-pass-42'] + self::DETAILS)
            ->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->assertSame($orders, Order::query()->count());
    }

    public function test_failed_order_does_not_leave_a_half_created_account(): void
    {
        $product = $this->addToBag();
        $product->update(['stock' => 0]);

        $this->post('/cart/checkout', self::DETAILS + ['create_account' => '1', 'password' => 'lens-pass-42'])->assertSessionHasErrors('items');

        $this->assertGuest();
        $this->assertSame(0, User::query()->where('email', 'guest.buyer@example.com')->count());
    }

    public function test_guest_order_is_not_attached_to_an_existing_account_with_the_same_email(): void
    {
        $existing = User::query()->where('role', User::ROLE_CUSTOMER)->firstOrFail();
        $this->addToBag();

        $this->post('/cart/checkout', ['email' => $existing->email] + self::DETAILS)->assertSessionHasNoErrors();

        $order = Order::query()->latest('id')->firstOrFail();
        $this->assertNull($order->user_id);
        $this->actingAs($existing)->get(route('account'))->assertDontSee($order->order_number);
    }

    public function test_signed_in_checkout_uses_account_email_and_saves_missing_contact_details(): void
    {
        $user = User::factory()->create(['phone' => null, 'city' => 'Lausanne']);
        $this->actingAs($user);
        $this->addToBag();

        // A different email in the request is ignored for members.
        $this->post('/cart/checkout', ['email' => 'someone@else.test'] + self::DETAILS)->assertSessionHasNoErrors();

        $order = Order::query()->latest('id')->firstOrFail();
        $this->assertSame($user->id, $order->user_id);
        $this->assertSame($user->email, $order->email);
        $this->assertSame('+41 79 555 0101', $user->fresh()->phone);
        $this->assertSame('Lausanne', $user->fresh()->city, 'Existing profile values are kept.');

        // Next checkout is prefilled from the saved address.
        $this->addToBag();
        $this->get('/cart')->assertSee('value="Quai du Mont-Blanc 7"', false)->assertSee('Signed in as');
    }

    public function test_members_cannot_use_the_create_account_option(): void
    {
        $this->actingAs(User::factory()->create());
        $this->addToBag();

        $this->post('/cart/checkout', self::DETAILS + ['create_account' => '1', 'password' => 'lens-pass-42'])->assertSessionHasErrors('create_account');
    }

    public function test_guest_can_order_without_an_email(): void
    {
        Mail::fake();
        $this->addToBag();

        $this->post('/cart/checkout', ['email' => ''] + self::DETAILS)->assertSessionHasNoErrors();

        $order = Order::query()->latest('id')->firstOrFail();
        $this->assertNull($order->user_id);
        $this->assertNull($order->email);
        Mail::assertNothingSent();

        $this->get(route('cart.confirmed', $order))->assertOk()
            ->assertSee('Save your order number')
            ->assertDontSee('A confirmation has been sent');
    }

    public function test_order_can_be_tracked_by_phone_number(): void
    {
        $this->addToBag();
        $this->post('/cart/checkout', ['email' => ''] + self::DETAILS);
        $order = Order::query()->latest('id')->firstOrFail();

        // Formatting differences don't matter; a wrong or too-short number does.
        $this->post(route('orders.track.lookup'), ['order_number' => $order->order_number, 'contact' => '+41 79 555 0199'])->assertSessionHasErrors('order_number');
        $this->post(route('orders.track.lookup'), ['order_number' => $order->order_number, 'contact' => '0101'])->assertSessionHasErrors('order_number');
        $this->post(route('orders.track.lookup'), ['order_number' => $order->order_number, 'contact' => 'anyone@example.com'])->assertSessionHasErrors('order_number');

        $response = $this->post(route('orders.track.lookup'), ['order_number' => $order->order_number, 'contact' => '+41-79-555-01-01']);
        $this->get($response->headers->get('Location'))->assertOk()->assertSee($order->order_number);
    }

    public function test_creating_an_account_requires_an_email(): void
    {
        $this->addToBag();

        $this->post('/cart/checkout', ['email' => '', 'create_account' => '1', 'password' => 'lens-pass-42'] + self::DETAILS)
            ->assertSessionHasErrors(['email' => 'Enter your email to create an account.']);
        $this->assertGuest();
    }
}
