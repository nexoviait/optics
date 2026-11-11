<?php

namespace Tests\Feature;

use App\Models\LensOption;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\Prescription;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Tests\Seeders\TestSeeder;
use Tests\TestCase;

/** Guards the things that must hold on the live store. */
class LaunchReadinessTest extends TestCase
{
    use RefreshDatabase;

    private const ADDRESS = [
        'ship_name' => 'Ada Client', 'ship_phone' => '+41 22 310 4400', 'ship_line1' => 'Rue du Rhône 42',
        'ship_city' => 'Genève', 'ship_postal' => '1204', 'ship_country' => 'CH',
    ];

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->forceFill(['role' => User::ROLE_ADMIN])->save();

        return $admin;
    }

    public function test_only_methods_that_really_work_are_offered_out_of_the_box(): void
    {
        // As first installed: everything on, cards "paid" instantly. Only Nagad has a real merchant number.
        PaymentMethod::query()->update(['is_active' => true]);
        PaymentMethod::query()->whereIn('code', ['card', 'wallet'])->update(['initial_status' => 'Succeeded']);
        PaymentMethod::query()->where('code', 'bkash')->update(['account_number' => '01XXXXXXXXX']);
        PaymentMethod::query()->where('code', 'nagad')->update(['account_number' => '01712345678']);

        (require database_path('migrations/2026_11_12_000100_disable_unprocessed_payment_methods.php'))->up();

        $this->assertSame(['nagad', 'cod'], PaymentMethod::query()->active()->pluck('code')->all());
        $this->assertSame(['Pending', 'Pending'], PaymentMethod::query()->whereIn('code', ['card', 'wallet'])->pluck('initial_status')->all());
    }

    public function test_production_seed_creates_no_sample_customers_or_orders(): void
    {
        $this->app['env'] = 'production';
        config(['store.admin.email' => 'owner@example.com', 'store.admin.password' => 'a-long-launch-password']);
        Order::query()->delete();
        User::query()->delete();

        $this->artisan('db:seed', ['--class' => DatabaseSeeder::class, '--force' => true])->assertSuccessful();

        $this->assertSame(1, User::query()->count());
        $this->assertSame(0, Order::query()->count());
        $this->assertGreaterThan(0, Product::query()->count());
    }

    public function test_staff_can_open_an_uploaded_prescription_and_customers_cannot(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('prescriptions/rx.pdf', '%PDF-1.4 test');
        $rx = Prescription::query()->create(['source' => 'upload', 'file_path' => 'prescriptions/rx.pdf', 'file_name' => 'my-rx.pdf']);

        $this->get(route('admin.prescriptions.file', $rx))->assertRedirect(route('admin.login'));
        $this->actingAs(User::factory()->create())->get(route('admin.prescriptions.file', $rx))->assertForbidden();

        $response = $this->actingAs($this->admin())->get(route('admin.prescriptions.file', $rx))->assertOk();
        $this->assertStringContainsString('my-rx.pdf', (string) $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $this->assertDatabaseHas('activity_logs', ['action' => 'Prescription file opened', 'subject_id' => $rx->id]);
    }

    public function test_changed_password_ends_sessions_on_other_devices(): void
    {
        $user = User::factory()->create();
        $otherDevice = Auth::guard('web')->hashPasswordForCookie($user->password);

        $user->forceFill(['password' => 'a-brand-new-Passw0rd'])->save();

        $this->actingAs($user)->withSession(['password_hash_web' => $otherDevice])
            ->get('/account')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_session_with_the_current_password_stays_signed_in(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->withSession(['password_hash_web' => Auth::guard('web')->hashPasswordForCookie($user->password)])
            ->get('/account')->assertOk();
    }

    public function test_progressive_prescription_keeps_add_and_pd_through_checkout(): void
    {
        $this->seed(TestSeeder::class);
        $product = Product::query()->active()->where('stock', '>', 0)->firstOrFail();
        $line = ['product_id' => $product->id, 'lens' => [
            'power_type' => 'progressive', 'lens' => LensOption::query()->active()->value('code'), 'rx_mode' => 'manual',
            'rx' => ['r_sph' => '-1.25', 'l_sph' => '-1.00', 'name' => 'Ada Client', 'phone' => '+41223104400'],
        ]];

        $this->post('/cart', $line)->assertSessionHasErrors('lens.rx.add_power');

        $line['lens']['rx'] += ['add_power' => '+1.75', 'pd' => '63'];
        $this->post('/cart', $line)->assertSessionHasNoErrors();
        $this->post('/cart/checkout', ['payment_method' => 'cod'] + self::ADDRESS)->assertSessionHasNoErrors();

        $this->assertDatabaseHas('prescriptions', ['add_power' => '+1.75', 'pd' => '63', 'r_sph' => '-1.25']);
    }

    public function test_pupil_distance_is_range_checked(): void
    {
        $this->seed(TestSeeder::class);

        $this->post('/cart', ['product_id' => Product::query()->value('id'), 'lens' => [
            'power_type' => 'single', 'lens' => LensOption::query()->active()->value('code'), 'rx_mode' => 'manual',
            'rx' => ['pd' => '200', 'name' => 'Ada Client', 'phone' => '+41223104400'],
        ]])->assertSessionHasErrors('lens.rx.pd');
    }

    public function test_guest_orders_can_be_found_in_the_admin(): void
    {
        $guest = Order::query()->create(['status' => 'New', 'subtotal' => 10, 'total' => 10, 'payment_method' => 'cod', 'channel' => 'web', 'email' => 'walk.in@example.com', 'ship_name' => 'Rahima Begum', 'ship_phone' => '01712345678']);
        $admin = $this->admin();

        foreach (['walk.in@example.com', 'Rahima', '01712345678'] as $term) {
            $this->actingAs($admin)->get('/admin/orders?q='.urlencode($term))->assertOk()->assertSee($guest->order_number);
        }
    }

    public function test_order_export_neutralises_spreadsheet_formulas(): void
    {
        Order::query()->create(['status' => 'New', 'subtotal' => 10, 'total' => 10, 'payment_method' => 'cod', 'channel' => 'web', 'ship_name' => '=HYPERLINK("http://evil.test")']);

        $csv = $this->actingAs($this->admin())->get('/admin/analytics/export/orders')->assertOk()->streamedContent();

        $this->assertStringContainsString("'=HYPERLINK", $csv);
    }

    public function test_cart_uploads_are_rate_limited(): void
    {
        $this->seed(TestSeeder::class);
        $id = Product::query()->value('id');

        foreach (range(1, 30) as $_) {
            $this->post('/cart', ['product_id' => $id]);
        }

        $this->post('/cart', ['product_id' => $id, 'prescription_file' => UploadedFile::fake()->create('rx.pdf', 10, 'application/pdf')])->assertTooManyRequests();
    }

    public function test_error_pages_are_branded_and_work_without_the_database(): void
    {
        $this->get('/no-such-page')->assertNotFound()->assertSee('We couldn’t find that page', false);

        foreach ([403, 419, 429, 500, 503] as $code) {
            $this->assertStringContainsString("Error {$code}", view("errors.{$code}")->render());
        }
    }
}
