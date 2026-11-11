<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\PageSection;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\User;
use App\Services\Pricing;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Seeders\TestSeeder;
use Tests\TestCase;

class StorefrontTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    protected string $seeder = TestSeeder::class;

    private const ADDRESS = [
        'ship_name' => 'Ada Client', 'ship_phone' => '+41 22 310 4400', 'ship_line1' => 'Rue du Rhône 42',
        'ship_city' => 'Genève', 'ship_postal' => '1204', 'ship_country' => 'CH',
    ];

    private function customer(): User
    {
        return User::query()->where('role', User::ROLE_CUSTOMER)->firstOrFail();
    }

    public function test_public_pages_render(): void
    {
        $product = Product::query()->firstOrFail();

        foreach (['/', '/eyeglasses', '/eyeglasses?category=sunglasses&q=titanium', '/sunglasses', '/science', '/visionmatch', '/fit-advisor', '/cart', '/login', '/register', route('products.show', $product)] as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_homepage_respects_cms_visibility(): void
    {
        $this->get('/')->assertSee('Bending Light');

        PageSection::query()->where('key', 'hero')->update(['is_enabled' => false]);

        $this->get('/')->assertDontSee('Atelier Refraction 2025 Edition');
    }

    public function test_draft_products_are_hidden(): void
    {
        $product = Product::query()->where('slug', 'kinetics')->firstOrFail();
        $product->update(['status' => 'draft']);

        $this->get(route('products.show', $product))->assertNotFound();
        $this->get('/eyeglasses')->assertDontSee($product->name);
    }

    public function test_frame_can_be_added_to_bag(): void
    {
        $product = Product::query()->firstOrFail();

        $this->postJson('/cart', ['product_id' => $product->id])
            ->assertOk()
            ->assertJson(['count' => 1]);

        $this->get('/cart')->assertSee($product->name)->assertSee(number_format((float) $product->price, 2));
    }

    public function test_lens_configuration_is_validated_and_priced_server_side(): void
    {
        $product = Product::query()->where('slug', 'apex-01')->firstOrFail();

        // Unknown lens is rejected.
        $this->postJson('/cart', ['product_id' => $product->id, 'lens' => ['power_type' => 'single', 'lens' => 'bogus']])->assertStatus(422);

        // Manual Rx needs patient details.
        $this->postJson('/cart', ['product_id' => $product->id, 'lens' => ['power_type' => 'single', 'lens' => 'highindex', 'rx_mode' => 'manual']])->assertStatus(422);

        $this->postJson('/cart', [
            'product_id' => $product->id,
            'size' => 'M',
            'lens' => ['power_type' => 'progressive', 'lens' => 'highindex', 'rx_mode' => 'manual', 'rx' => ['name' => 'Ada Lovelace', 'phone' => '+41 22 123 4567', 'r_sph' => '-2.75', 'l_sph' => '-3.00', 'add_power' => '+1.50']],
        ])->assertOk()->assertJsonPath('redirect', route('cart.index'));

        // 540 frame + 120 progressive + 150 high-index.
        $this->get('/cart')->assertSee('810.00')->assertSee('-2.75 / -3.00');
    }

    public function test_prescription_upload_is_stored_privately(): void
    {
        Storage::fake('local');
        $product = Product::query()->firstOrFail();

        $this->post('/cart', [
            'product_id' => $product->id,
            'lens' => ['power_type' => 'single', 'lens' => 'antiglare', 'rx_mode' => 'upload'],
            'prescription_file' => UploadedFile::fake()->create('rx.pdf', 100, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertOk();

        $this->assertCount(1, Storage::disk('local')->files('prescriptions'));
    }

    public function test_guest_checkout_validates_email_only_when_given(): void
    {
        $this->postJson('/cart', ['product_id' => Product::query()->value('id')]);

        $this->post('/cart/checkout', ['payment_method' => 'wallet', 'email' => 'not-an-email'] + self::ADDRESS)->assertSessionHasErrors('email');
        $this->assertSame(0, Order::query()->whereNull('user_id')->count());
    }

    public function test_checkout_creates_repriced_order_payment_and_reserves_stock(): void
    {
        $customer = $this->customer();
        $apex = Product::query()->where('slug', 'apex-01')->firstOrFail();
        $aura = Product::query()->where('slug', 'aura-prism')->firstOrFail();
        $stock = $apex->stock;

        $this->actingAs($customer);
        $this->postJson('/cart', ['product_id' => $apex->id])->assertOk();
        $this->postJson('/cart', ['product_id' => $aura->id])->assertOk();
        $this->post('/cart/coupon', ['code' => 'atelier10'])->assertSessionHasNoErrors();

        $response = $this->post('/cart/checkout', ['payment_method' => 'wallet'] + self::ADDRESS);

        $order = Order::query()->latest('id')->firstOrFail();
        $response->assertRedirect(route('cart.confirmed', $order));

        // 540 + 620 = 1160; 1+1 privilege removes 540; 10% of 620 = 62.
        $this->assertSame('1160.00', $order->subtotal);
        $this->assertSame('540.00', $order->member_discount);
        $this->assertSame('62.00', $order->gift_discount);
        $this->assertSame('558.00', $order->total);
        $this->assertSame('ATELIER10', $order->coupon_code);
        $this->assertSame('RF-'.(48300 + $order->id), $order->order_number);
        $this->assertSame('Succeeded', $order->payment->status);
        $this->assertSame($stock - 1, $apex->fresh()->stock);

        $this->get(route('cart.confirmed', $order))->assertOk()->assertSee($order->order_number);
        $this->get('/cart')->assertSee('Your bag is empty');
    }

    public function test_card_checkout_validates_card_details(): void
    {
        $this->actingAs($this->customer());
        $this->postJson('/cart', ['product_id' => Product::query()->value('id')]);

        $this->post('/cart/checkout', ['payment_method' => 'card'] + self::ADDRESS)->assertSessionHasErrors(['card_name', 'card_number', 'card_expiry', 'card_cvc']);

        // A failed checkout must not keep card details in the session (sessions are stored server-side).
        $this->post('/cart/checkout', ['payment_method' => 'card', 'card_name' => 'A', 'card_number' => '4242 4242 4242 4242', 'card_expiry' => '12 / 30', 'card_cvc' => '123'] + self::ADDRESS)
            ->assertSessionHasErrors('card_name');
        $this->assertSame('A', session()->getOldInput('card_name'));
        foreach (['card_number', 'card_expiry', 'card_cvc'] as $field) {
            $this->assertNull(session()->getOldInput($field), "{$field} was flashed into the session.");
        }
    }

    public function test_mobile_banking_checkout_keeps_payer_details_for_verification(): void
    {
        $this->actingAs($this->customer());
        $this->postJson('/cart', ['product_id' => Product::query()->value('id')]);
        $this->get('/cart')->assertSee('bKash')->assertSee('Nagad')->assertSee('Cash on Delivery');

        $this->post('/cart/checkout', ['payment_method' => 'bkash'] + self::ADDRESS)->assertSessionHasErrors(['payer_account', 'payer_reference']);
        $this->post('/cart/checkout', ['payment_method' => 'bkash', 'payer_account' => '12345', 'payer_reference' => 'x!'] + self::ADDRESS)->assertSessionHasErrors(['payer_account', 'payer_reference']);

        $this->post('/cart/checkout', ['payment_method' => 'bkash', 'payer_account' => '+8801712345678', 'payer_reference' => '9bg7xk2lq1'] + self::ADDRESS)->assertSessionHasNoErrors();
        $payment = Payment::query()->latest('id')->firstOrFail();
        $this->assertSame(['bkash', 'Pending', '01712345678', '9BG7XK2LQ1'], [$payment->method, $payment->status, $payment->payer_account, $payment->payer_reference]);
        $this->assertSame('bKash', $payment->order->paymentLabel());
    }

    public function test_inactive_payment_methods_are_hidden_and_rejected(): void
    {
        PaymentMethod::query()->where('code', 'cod')->update(['is_active' => false]);
        $this->actingAs($this->customer());
        $this->postJson('/cart', ['product_id' => Product::query()->value('id')]);

        $this->get('/cart')->assertDontSee('Cash on Delivery');
        $this->post('/cart/checkout', ['payment_method' => 'cod'] + self::ADDRESS)->assertSessionHasErrors('payment_method');
    }

    public function test_out_of_stock_frames_cannot_be_ordered(): void
    {
        $product = Product::query()->firstOrFail();
        $this->actingAs($this->customer());
        $this->postJson('/cart', ['product_id' => $product->id, 'qty' => 2]);
        $product->update(['stock' => 1]);

        $this->post('/cart/checkout', ['payment_method' => 'wallet'] + self::ADDRESS)->assertSessionHasErrors('items');
        $this->assertSame(1, $product->fresh()->stock);
    }

    public function test_member_discount_can_be_disabled(): void
    {
        Settings::put(['member_bogo_enabled' => false]);

        $bill = app(Pricing::class)->computeBill([['price' => 500, 'qty' => 1], ['price' => 300, 'qty' => 1]]);

        $this->assertSame(0.0, $bill['member_discount']);
        $this->assertSame(800.0, $bill['total']);
    }

    public function test_delivery_estimate_states(): void
    {
        $this->postJson('/delivery-estimate', ['region' => 'CH', 'postal' => '1204'])->assertOk()->assertJson(['status' => 'available', 'courier' => 'Swiss Post Express']);
        $this->postJson('/delivery-estimate', ['region' => 'CH', 'postal' => 'ABC'])->assertJson(['status' => 'invalid']);
        $this->postJson('/delivery-estimate', ['region' => 'OTHER', 'postal' => '12345'])->assertJson(['status' => 'unavailable']);
    }

    public function test_reviews_derive_verified_status_and_are_unique(): void
    {
        $customer = $this->customer();
        $product = Product::query()->where('slug', 'solstice')->firstOrFail();
        $delivered = $customer->orders()->where('status', 'Delivered')->with('items')->get()->flatMap->items->pluck('product_id')->contains($product->id);

        $this->actingAs($customer)
            ->post(route('products.reviews.store', $product), ['reviewer_name' => 'Christian', 'rating' => 5, 'body' => 'Superb clarity and a perfect fit.'])
            ->assertRedirect();

        $review = $product->reviews()->where('user_id', $customer->id)->firstOrFail();
        $this->assertSame($delivered, $review->verified_buyer);

        $this->post(route('products.reviews.store', $product), ['reviewer_name' => 'Christian', 'rating' => 4, 'body' => 'Second attempt should fail.'])
            ->assertSessionHasErrors('review');
    }

    public function test_visionmatch_ranks_preferred_shape_first(): void
    {
        $this->postJson('/visionmatch/matches', ['preferences' => ['shape' => 'Aviator Prism']])
            ->assertOk()
            ->assertJsonPath('matches.0.slug', 'helios-02')
            ->assertJsonPath('unique', false);

        $this->assertDatabaseHas('vision_sessions', ['top_product_id' => Product::query()->where('slug', 'helios-02')->value('id')]);
    }

    public function test_fit_advisor_falls_back_to_rules_without_api_key(): void
    {
        config(['services.anthropic.key' => null]);

        $this->post('/fit-advisor', ['preferences' => 'Lightweight titanium eyeglasses under $600, narrow face, 130 mm frame width'])
            ->assertOk()
            ->assertSee('Atelier-matched')
            ->assertSee('Apex 01 Titanium')
            ->assertDontSee('Kinetics Rimless');

        $this->post('/fit-advisor', ['preferences' => 'short'])->assertSessionHasErrors('preferences');
    }

    public function test_engagement_requests_are_stored(): void
    {
        $this->postJson('/newsletter', ['email' => 'Guest@Example.com'])->assertOk();
        $this->assertDatabaseHas('newsletter_subscribers', ['email' => 'guest@example.com']);

        $this->postJson('/requests/consultation', ['preferred_date' => now()->addDay()->toDateString(), 'name' => 'Guest', 'email' => 'g@example.com'])->assertOk();
        $this->postJson('/requests/home-trial', ['product_ids' => Product::query()->take(2)->pluck('id'), 'name' => 'Guest', 'email' => 'g@example.com'])->assertOk();

        $this->assertDatabaseCount('service_requests', 2);
    }

    public function test_registration_and_account(): void
    {
        $this->post('/register', ['name' => 'New Client', 'email' => 'new@example.com', 'password' => 'secret-pass-42', 'password_confirmation' => 'secret-pass-42'])
            ->assertRedirect(route('account'));

        $user = User::query()->where('email', 'new@example.com')->firstOrFail();
        $this->assertSame(User::ROLE_CUSTOMER, $user->role);

        $this->get('/account')->assertOk()->assertSee('No orders yet');
        $this->put('/account', ['name' => 'New Client', 'city' => 'Zürich'])->assertSessionHasNoErrors();
        $this->assertSame('Zürich', $user->fresh()->city);
    }
}
