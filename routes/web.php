<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Shop;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Storefront
|--------------------------------------------------------------------------
*/

Route::get('/', [Shop\HomeController::class, 'index'])->name('home');
Route::get('/eyeglasses', [Shop\CatalogController::class, 'eyeglasses'])->name('eyeglasses');
Route::get('/sunglasses', [Shop\CatalogController::class, 'sunglasses'])->name('sunglasses');
Route::get('/search', [Shop\CatalogController::class, 'search'])->name('search');
Route::get('/search/suggest', [Shop\CatalogController::class, 'suggest'])->middleware('throttle:60,1')->name('search.suggest');
Route::get('/science', [Shop\PageController::class, 'science'])->name('science');
Route::get('/pages/{page}', [Shop\PageController::class, 'show'])->name('pages.show');
Route::get('/wishlist', [Shop\WishlistController::class, 'index'])->name('wishlist.index');
Route::post('/wishlist/{product}', [Shop\WishlistController::class, 'toggle'])->middleware('throttle:60,1')->name('wishlist.toggle');
Route::get('/product/{product}', [Shop\ProductController::class, 'show'])->name('products.show');
Route::post('/product/{product}/reviews', [Shop\ProductController::class, 'review'])->middleware(['auth', 'throttle:10,1'])->name('products.reviews.store');
Route::post('/delivery-estimate', [Shop\ProductController::class, 'delivery'])->middleware('throttle:30,1')->name('delivery.estimate');

Route::get('/visionmatch', [Shop\VisionMatchController::class, 'show'])->name('visionmatch');
Route::post('/visionmatch/matches', [Shop\VisionMatchController::class, 'matches'])->middleware('throttle:20,1')->name('visionmatch.matches');

Route::get('/fit-advisor', [Shop\FitAdvisorController::class, 'show'])->name('fit-advisor');
Route::post('/fit-advisor', [Shop\FitAdvisorController::class, 'recommend'])->middleware('throttle:6,1')->name('fit-advisor.recommend');

Route::controller(Shop\CartController::class)->prefix('cart')->name('cart.')->group(function () {
    Route::get('/', 'index')->name('index');
    // Limited because adding a lens can upload a prescription file.
    Route::post('/', 'store')->middleware('throttle:30,1')->name('store');
    Route::patch('/{key}', 'update')->name('update');
    Route::delete('/{key}', 'destroy')->name('destroy');
    Route::post('/coupon', 'coupon')->middleware('throttle:10,1')->name('coupon');
    Route::post('/checkout', 'checkout')->middleware('throttle:10,1')->name('checkout');
    Route::get('/confirmed/{order}', 'confirmed')->name('confirmed');
});

Route::get('/track', [Shop\TrackingController::class, 'form'])->name('orders.track');
Route::post('/track', [Shop\TrackingController::class, 'lookup'])->middleware('throttle:10,1')->name('orders.track.lookup');
Route::get('/track/{order}', [Shop\TrackingController::class, 'show'])->middleware('signed')->name('orders.track.show');

Route::post('/newsletter', [Shop\EngagementController::class, 'newsletter'])->middleware('throttle:10,1')->name('newsletter.subscribe');
Route::post('/requests/consultation', [Shop\EngagementController::class, 'consultation'])->middleware('throttle:10,1')->name('requests.consultation');
Route::post('/requests/home-trial', [Shop\EngagementController::class, 'homeTrial'])->middleware('throttle:10,1')->name('requests.home-trial');

/*
|--------------------------------------------------------------------------
| Authentication & account
|--------------------------------------------------------------------------
*/

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:10,1');
    Route::get('/admin/login', [AuthController::class, 'showAdminLogin'])->name('admin.login');
    Route::post('/admin/login', [AuthController::class, 'adminLogin'])->middleware('throttle:10,1');
    Route::get('/forgot-password', [PasswordResetController::class, 'create'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'send'])->middleware('throttle:6,1')->name('password.email');
    Route::get('/reset-password/{token}', [PasswordResetController::class, 'edit'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'update'])->middleware('throttle:6,1')->name('password.store');
});

Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');
Route::put('/password', [AuthController::class, 'updatePassword'])->middleware(['auth', 'throttle:6,1'])->name('password.update');

Route::middleware('auth')->group(function () {
    Route::get('/account', [Shop\AccountController::class, 'show'])->name('account');
    Route::put('/account', [Shop\AccountController::class, 'update'])->name('account.update');
    Route::get('/account/orders/{order}', [Shop\OrderController::class, 'show'])->name('orders.show');
    Route::post('/account/orders/{order}/cancel', [Shop\OrderController::class, 'cancel'])->middleware('throttle:10,1')->name('orders.cancel');
});

/*
|--------------------------------------------------------------------------
| Admin console
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [Admin\DashboardController::class, 'index'])->name('dashboard');
    Route::get('profile', [Admin\ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('profile', [Admin\ProfileController::class, 'update'])->name('profile.update');

    Route::resource('products', Admin\ProductController::class)->except('show');
    Route::delete('reviews/{review}', [Admin\ProductController::class, 'destroyReview'])->name('reviews.destroy');
    Route::post('products/{product}/spin', [Admin\ProductSpinController::class, 'store'])->name('products.spin.store');
    Route::delete('products/{product}/spin', [Admin\ProductSpinController::class, 'destroy'])->name('products.spin.destroy');
    Route::get('products/{product}/images', [Admin\ProductImageController::class, 'index'])->name('products.images.index');
    Route::post('products/{product}/images', [Admin\ProductImageController::class, 'store'])->name('products.images.store');
    Route::patch('products/{product}/images/reorder', [Admin\ProductImageController::class, 'reorder'])->name('products.images.reorder');
    Route::post('products/{product}/spin-sets', [Admin\ProductImageController::class, 'storeSpinSet'])->name('products.spin-sets.store');
    Route::post('products/{product}/model', [Admin\ProductImageController::class, 'storeModel'])->name('products.model.store');
    Route::delete('products/{product}/model', [Admin\ProductImageController::class, 'destroyModel'])->name('products.model.destroy');
    Route::patch('product-images/{image}', [Admin\ProductImageController::class, 'update'])->name('product-images.update');
    Route::delete('product-images/{image}', [Admin\ProductImageController::class, 'destroy'])->name('product-images.destroy');
    Route::resource('pages', Admin\PageController::class)->except('show');

    Route::controller(Admin\LensController::class)->prefix('lenses')->name('lenses.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/skus', 'storeSku')->name('skus.store');
        Route::put('/skus/{sku}', 'updateSku')->name('skus.update');
        Route::delete('/skus/{sku}', 'destroySku')->name('skus.destroy');
        Route::put('/pricing', 'updatePricing')->name('pricing.update');
    });

    Route::get('orders/create', [Admin\OrderController::class, 'create'])->name('orders.create');
    Route::post('orders', [Admin\OrderController::class, 'store'])->name('orders.store');
    Route::get('orders', [Admin\OrderController::class, 'index'])->name('orders.index');
    Route::get('orders/{order}', [Admin\OrderController::class, 'show'])->name('orders.show');
    Route::patch('orders/{order}/status', [Admin\OrderController::class, 'updateStatus'])->name('orders.status');
    Route::get('prescriptions/{prescription}/file', [Admin\PrescriptionController::class, 'file'])->name('prescriptions.file');

    Route::get('customers', [Admin\CustomerController::class, 'index'])->name('customers.index');
    Route::post('customers', [Admin\CustomerController::class, 'store'])->name('customers.store');
    Route::get('customers/{customer}', [Admin\CustomerController::class, 'show'])->name('customers.show');
    Route::patch('customers/{customer}', [Admin\CustomerController::class, 'update'])->name('customers.update');

    Route::get('payments', [Admin\PaymentController::class, 'index'])->name('payments.index');
    Route::post('payments/{payment}/confirm', [Admin\PaymentController::class, 'confirm'])->name('payments.confirm');
    Route::post('payments/{payment}/refund', [Admin\PaymentController::class, 'refund'])->name('payments.refund');
    Route::prefix('payments')->name('payments.')->group(function () {
        Route::patch('methods/{paymentMethod}/toggle', [Admin\PaymentMethodController::class, 'toggle'])->name('methods.toggle');
        Route::resource('methods', Admin\PaymentMethodController::class)->except('show')->parameters(['methods' => 'paymentMethod']);
    });

    Route::get('visionmatch', [Admin\VisionMatchController::class, 'index'])->name('visionmatch.index');
    Route::put('visionmatch', [Admin\VisionMatchController::class, 'update'])->name('visionmatch.update');

    Route::get('content', [Admin\ContentController::class, 'index'])->name('content.index');
    Route::put('content/order', [Admin\ContentController::class, 'reorder'])->name('content.reorder');
    Route::get('content/{section}/edit', [Admin\ContentController::class, 'edit'])->name('content.edit');
    Route::put('content/{section}', [Admin\ContentController::class, 'update'])->name('content.update');

    Route::resource('email', Admin\EmailTemplateController::class)->except('show')->parameters(['email' => 'template']);

    Route::get('analytics', [Admin\AnalyticsController::class, 'index'])->name('analytics.index');
    Route::get('analytics/export/{report}', [Admin\AnalyticsController::class, 'export'])->name('analytics.export');

    Route::get('requests', [Admin\RequestController::class, 'index'])->name('requests.index');
    Route::patch('requests/{serviceRequest}', [Admin\RequestController::class, 'update'])->name('requests.update');

    Route::controller(Admin\SettingsController::class)->prefix('settings')->name('settings.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::put('/', 'update')->name('update');
        Route::post('/branding', 'updateBranding')->name('branding.update');
        Route::delete('/branding/{asset}', 'destroyBranding')->whereIn('asset', ['logo', 'favicon'])->name('branding.destroy');
        Route::post('/zones', 'storeZone')->name('zones.store');
        Route::put('/zones/{zone}', 'updateZone')->name('zones.update');
        Route::delete('/zones/{zone}', 'destroyZone')->name('zones.destroy');
        Route::post('/coupons', 'storeCoupon')->name('coupons.store');
        Route::put('/coupons/{coupon}', 'updateCoupon')->name('coupons.update');
        Route::delete('/coupons/{coupon}', 'destroyCoupon')->name('coupons.destroy');
        Route::put('/sizes', 'updateSizes')->name('sizes.update');
    });
});
