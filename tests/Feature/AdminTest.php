<?php

namespace Tests\Feature;

use App\Models\Coupon;
use App\Models\EmailTemplate;
use App\Models\LensOption;
use App\Models\LensSku;
use App\Models\Order;
use App\Models\PageSection;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Seeders\TestSeeder;
use Tests\TestCase;

class AdminTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    protected string $seeder = TestSeeder::class;

    private function admin(): User
    {
        return User::query()->where('role', User::ROLE_ADMIN)->firstOrFail();
    }

    public function test_guests_are_sent_to_admin_login_and_customers_are_forbidden(): void
    {
        $this->get('/admin')->assertRedirect(route('admin.login'));

        $customer = User::query()->where('role', User::ROLE_CUSTOMER)->firstOrFail();
        $this->actingAs($customer)->get('/admin')->assertForbidden();
    }

    public function test_admin_login_rejects_non_admins(): void
    {
        $customer = User::query()->where('role', User::ROLE_CUSTOMER)->firstOrFail();

        $this->post('/admin/login', ['email' => $customer->email, 'password' => 'password'])->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->post('/admin/login', ['email' => 'admin@refra.com', 'password' => 'password'])->assertRedirect(route('admin.dashboard'));
    }

    public function test_every_admin_screen_renders(): void
    {
        $this->actingAs($this->admin());
        $order = Order::query()->firstOrFail();
        $customer = User::query()->where('role', User::ROLE_CUSTOMER)->firstOrFail();

        $urls = [
            '/admin', '/admin/products', '/admin/products/create', route('admin.products.edit', Product::query()->first()),
            '/admin/lenses', '/admin/orders', '/admin/orders?status=Delivered', '/admin/orders/create', route('admin.orders.show', $order),
            '/admin/customers', route('admin.customers.show', $customer), '/admin/payments', '/admin/payments?status=Succeeded',
            '/admin/requests', '/admin/visionmatch', '/admin/content', route('admin.content.edit', PageSection::query()->first()),
            '/admin/email', '/admin/email/create', route('admin.email.edit', EmailTemplate::query()->first()),
            '/admin/analytics', '/admin/analytics?view=weekly',
        ];
        foreach (array_keys(['general' => 1, 'branding' => 1, 'region' => 1, 'shipping' => 1, 'sizes' => 1, 'pricing' => 1, 'coupons' => 1, 'checkout' => 1, 'legal' => 1]) as $tab) {
            $urls[] = '/admin/settings?tab='.$tab;
        }

        foreach ($urls as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_product_crud(): void
    {
        $this->actingAs($this->admin());

        $this->post('/admin/products', [
            'name' => 'Nova Clip', 'sku' => 'RF-NOVA', 'category' => 'eyeglasses', 'status' => 'active', 'price' => 410, 'cost' => 100,
            'stock' => 12, 'low_stock_threshold' => 5, 'finishes' => ['#0F1113'], 'shape' => 'Round',
        ])->assertRedirect();

        $product = Product::query()->where('slug', 'nova-clip')->firstOrFail();
        $this->get('/eyeglasses')->assertSee('Nova Clip');

        $this->put(route('admin.products.update', $product), $product->only(['name', 'sku', 'category', 'status', 'cost', 'stock', 'low_stock_threshold']) + ['price' => 450, 'finishes' => ['#0F1113']])
            ->assertSessionHasNoErrors();
        $this->assertSame('450.00', $product->fresh()->price);
        $this->assertDatabaseHas('activity_logs', ['subject_id' => $product->id, 'action' => 'Product updated (price $410 → $450)']);

        $this->delete(route('admin.products.destroy', $product))->assertRedirect(route('admin.products.index'));
        $this->assertModelMissing($product);
    }

    public function test_order_status_pipeline_and_cancellation_restocks(): void
    {
        $this->actingAs($this->admin());
        $order = Order::query()->where('status', 'New')->with('items.product')->firstOrFail();
        $item = $order->items->first();
        $stock = $item->product->stock;

        $this->patch(route('admin.orders.status', $order), ['status' => 'Processing'])->assertSessionHasNoErrors();
        $this->assertSame('Processing', $order->fresh()->status);

        $this->patch(route('admin.orders.status', $order), ['status' => 'Cancelled']);
        $this->assertSame($stock + $item->qty, $item->product->fresh()->stock);

        // Repeat cancels never restore stock twice, and cancelled orders stay closed.
        $this->patch(route('admin.orders.status', $order), ['status' => 'Cancelled']);
        $this->patch(route('admin.orders.status', $order), ['status' => 'Processing'])->assertSessionHasErrors('status');
        $this->assertSame($stock + $item->qty, $item->product->fresh()->stock);
        $this->assertSame('Cancelled', $order->fresh()->status);
    }

    public function test_manual_concierge_order(): void
    {
        $this->actingAs($this->admin());
        $customer = User::query()->where('role', User::ROLE_CUSTOMER)->firstOrFail();
        $product = Product::query()->where('slug', 'apex-01')->firstOrFail();

        $this->post('/admin/orders', [
            'customer_id' => $customer->id,
            'payment_method' => 'cod',
            'items' => [['product_id' => $product->id, 'lens' => 'photochromic', 'qty' => 1]],
        ])->assertRedirect();

        $order = Order::query()->latest('id')->firstOrFail();
        $this->assertSame('concierge', $order->channel);
        $this->assertSame('720.00', $order->total); // 540 + 180 photochromic
        $this->assertSame('Pending', $order->payment->status);
    }

    public function test_refunds_and_confirms_payments(): void
    {
        $this->actingAs($this->admin());
        $payment = Payment::query()->where('status', 'Succeeded')->firstOrFail();

        $this->post(route('admin.payments.refund', $payment))->assertSessionHasNoErrors();
        $this->assertSame('Refunded', $payment->fresh()->status);
        $this->post(route('admin.payments.refund', $payment))->assertSessionHasErrors('payment');

        $pending = Payment::query()->where('status', 'Pending')->firstOrFail();
        $this->post(route('admin.payments.confirm', $pending))->assertSessionHasNoErrors();
        $this->assertSame('Succeeded', $pending->fresh()->status);
        $this->post(route('admin.payments.confirm', $pending))->assertSessionHasErrors('payment');
    }

    public function test_payment_methods_crud_with_logo_and_toggle(): void
    {
        Storage::fake('public');
        $this->actingAs($this->admin());
        $this->get(route('admin.payments.methods.index'))->assertOk()->assertSee('bKash');
        $this->get(route('admin.payments.methods.create'))->assertOk();

        $this->post(route('admin.payments.methods.store'), [
            'name' => 'Rocket', 'type' => 'mobile', 'initial_status' => 'Pending', 'is_active' => 1,
            'account_number' => '01700000000', 'logo' => UploadedFile::fake()->image('rocket.png', 120, 60),
        ])->assertSessionHasNoErrors();
        $method = PaymentMethod::query()->where('code', 'rocket')->firstOrFail();
        Storage::disk('public')->assertExists($method->logo_path);
        $this->get(route('admin.payments.methods.edit', $method))->assertOk();

        // The code is fixed once created; the logo can be replaced or removed.
        $old = $method->logo_path;
        $this->put(route('admin.payments.methods.update', $method), [
            'name' => 'Rocket Pay', 'code' => 'renamed', 'type' => 'mobile', 'initial_status' => 'Pending', 'is_active' => 1,
            'logo' => UploadedFile::fake()->image('new.webp', 80, 40),
        ])->assertSessionHasNoErrors();
        $method->refresh();
        $this->assertSame(['Rocket Pay', 'rocket'], [$method->name, $method->code]);
        Storage::disk('public')->assertMissing($old);
        $this->put(route('admin.payments.methods.update', $method), ['name' => 'Rocket Pay', 'type' => 'mobile', 'initial_status' => 'Pending', 'is_active' => 1, 'remove_logo' => 1]);
        $this->assertNull($method->fresh()->logo_path);

        $this->post(route('admin.payments.methods.store'), ['name' => 'Rocket', 'type' => 'mobile', 'initial_status' => 'Pending'])->assertSessionHasErrors('code');
        $this->post(route('admin.payments.methods.store'), ['name' => 'Bad', 'type' => 'bogus', 'initial_status' => 'Pending'])->assertSessionHasErrors('type');
        $this->post(route('admin.payments.methods.store'), ['name' => 'Svg', 'type' => 'offline', 'initial_status' => 'Pending', 'logo' => UploadedFile::fake()->create('x.svg', 1, 'image/svg+xml')])->assertSessionHasErrors('logo');

        $this->patch(route('admin.payments.methods.toggle', $method));
        $this->assertFalse($method->fresh()->is_active);
        $this->patch(route('admin.payments.methods.toggle', $method));
        $this->assertTrue($method->fresh()->is_active);

        $this->delete(route('admin.payments.methods.destroy', $method))->assertSessionHasNoErrors();
        $this->assertModelMissing($method);

        // Methods with payment history can only be deactivated.
        $used = PaymentMethod::query()->where('code', Payment::query()->value('method'))->firstOrFail();
        $this->delete(route('admin.payments.methods.destroy', $used))->assertSessionHasErrors('methods');
        $this->assertModelExists($used);

        // At least one method must stay active.
        PaymentMethod::query()->whereKeyNot($used->id)->update(['is_active' => false]);
        $used->update(['is_active' => true]);
        $this->patch(route('admin.payments.methods.toggle', $used))->assertSessionHasErrors('methods');
        $this->assertTrue($used->fresh()->is_active);
    }

    public function test_lens_skus_and_pricing(): void
    {
        $this->actingAs($this->admin());

        $this->post('/admin/lenses/skus', ['name' => 'Trivex Sport', 'substrate' => 'Polycarbonate', 'refractive_index' => '1.59', 'abbe' => 43, 'geometry' => 'Aspheric', 'coatings' => ['Hard coat'], 'labs' => ['Paris'], 'stock' => 100, 'low_stock_threshold' => 20])
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('lens_skus', ['sku' => 'LN-POLYCARBONATE-159']);

        $option = LensOption::query()->where('code', 'highindex')->firstOrFail();
        $this->put('/admin/lenses/pricing', ['lens_options' => [$option->id => ['label' => 'High-Index 1.74', 'description' => 'Thin', 'price' => 175, 'is_active' => 1]]]);
        $this->assertSame('175.00', $option->fresh()->price);
    }

    public function test_settings_content_email_and_visionmatch_are_editable(): void
    {
        $this->actingAs($this->admin());

        $this->put('/admin/settings', ['tab' => 'general', 'store_name' => 'REFRA Atelier Optics', 'store_tagline' => 'Clarity, engineered', 'store_identifier' => 'refra', 'concierge_email' => 'hi@refra.com'])
            ->assertRedirect();
        $this->get('/')->assertSee('Clarity, engineered');

        $hero = PageSection::query()->where('key', 'hero')->firstOrFail();
        $this->put(route('admin.content.update', $hero), ['label' => 'Hero', 'fields' => ['headline' => 'See Further. Wear Lighter.'], 'lists' => ['stats' => '[{"value":"1","label":"One"}]']])
            ->assertRedirect();
        $this->get('/')->assertSee('See Further.');
        $this->put(route('admin.content.update', $hero), ['label' => 'Hero', 'lists' => ['stats' => '{not json']])->assertSessionHasErrors('lists.stats');

        $this->post('/admin/email', ['name' => 'Welcome', 'hook' => 'user.registered', 'category' => 'Transactional', 'locale' => 'en-GB', 'subject' => 'Welcome', 'status' => 'Live', 'blocks' => json_encode([['id' => '1', 'type' => 'Heading', 'text' => 'Hi'], ['id' => '2', 'type' => 'Evil', 'text' => 'x']])])
            ->assertRedirect();
        $this->assertCount(1, EmailTemplate::query()->where('name', 'Welcome')->firstOrFail()->blocks);

        $this->put('/admin/visionmatch', ['steps' => [['title' => 'Frame shapes', 'key' => 'shape', 'options' => 'Round · Square', 'is_required' => 1]]])->assertRedirect();
        $this->assertDatabaseCount('vision_steps', 1);

        $this->post('/admin/settings/coupons', ['code' => 'vip20', 'type' => 'percent', 'value' => 20, 'is_active' => 1])->assertRedirect();
        $this->assertNotNull(Coupon::findValid('VIP20'));
    }

    public function test_csv_exports(): void
    {
        $this->actingAs($this->admin());

        foreach (['sales', 'orders', 'payments'] as $report) {
            $response = $this->get(route('admin.analytics.export', $report));
            $response->assertOk();
            $this->assertStringContainsString(',', $response->streamedContent());
        }

        $this->get(route('admin.analytics.export', 'secrets'))->assertNotFound();
    }

    public function test_low_stock_feeds_alerts(): void
    {
        LensSku::query()->first()->update(['stock' => 1]);

        $this->actingAs($this->admin())->get('/admin')->assertSee('Optical blank depletion');
    }
}
