<?php

use App\Http\Controllers\Admin\AuditController as AdminAuditController;
use App\Http\Controllers\Admin\AuthController as AdminAuthController;
use App\Http\Controllers\Admin\CategoryController as AdminCategoryController;
use App\Http\Controllers\Admin\CustomerController as AdminCustomerController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\InventoryController as AdminInventoryController;
use App\Http\Controllers\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Admin\PasswordResetController as AdminPasswordResetController;
use App\Http\Controllers\Admin\ProductController as AdminProductController;
use App\Http\Controllers\Admin\ProductImageController as AdminProductImageController;
use App\Http\Controllers\Admin\ProductVariantController as AdminProductVariantController;
use App\Http\Controllers\Admin\RefundController as AdminRefundController;
use App\Http\Controllers\Admin\SettingsController as AdminSettingsController;
use App\Http\Controllers\Admin\ThemeController as AdminThemeController;
use App\Http\Controllers\Storefront\AccountController;
use App\Http\Controllers\Storefront\AuthController as StorefrontAuthController;
use App\Http\Controllers\Storefront\CartController;
use App\Http\Controllers\Storefront\CatalogController;
use App\Http\Controllers\Storefront\CheckoutController;
use App\Http\Controllers\Storefront\GuestOrderLookupController;
use App\Http\Controllers\Storefront\PasswordResetController as StorefrontPasswordResetController;
use App\Http\Controllers\Storefront\PaymentMethodController;
use App\Http\Controllers\Storefront\VerificationController;
use App\Http\Controllers\Webhooks\PaymentWebhookController;
use App\Http\Middleware\AuthenticateStorefrontSession;
use App\Models\TenantSettings;
use App\Http\Middleware\EnforceConfiguredVerification;
use App\Http\Middleware\VerifyStorefrontCsrf;
use App\Tenancy\TenantManager;
use Illuminate\Support\Facades\Route;

Route::middleware(['tenant.resolve', 'tenant.host:admin'])
    ->get('/admin/tenant', function (TenantManager $tenantManager) {
        $context = $tenantManager->current();
        $tenant = $context->tenant;

        return response()->json([
            'id' => $tenant->id,
            'name' => $tenant->name,
            'status' => $tenant->status,
            'default_locale' => $tenant->default_locale,
            'default_currency' => $tenant->default_currency,
            'host_type' => $context->hostType,
        ])->header('Cache-Control', 'no-store');
    })
    ->middleware('throttle:60,1');

Route::middleware([
    'tenant.resolve',
    'tenant.host:admin',
    'tenant.active',
    'throttle:10,1',
])->prefix('admin/auth')->group(function (): void {
    Route::post('/login', [AdminAuthController::class, 'login'])->name('admin.auth.login');
    Route::post('/refresh', [AdminAuthController::class, 'refresh'])->name('admin.auth.refresh');
    Route::post('/logout', [AdminAuthController::class, 'logout'])->name('admin.auth.logout');
    Route::post('/password-reset/request', [AdminPasswordResetController::class, 'requestCode'])
        ->middleware('throttle:5,1')
        ->name('admin.auth.password-reset.request');
    Route::post('/password-reset', [AdminPasswordResetController::class, 'reset'])
        ->middleware('throttle:10,1')
        ->name('admin.auth.password-reset');
});

Route::middleware([
    'tenant.resolve',
    'tenant.host:admin',
    'tenant.active',
    'auth:tenant-jwt',
    'tenant.admin',
])->prefix('admin')->group(function (): void {
    Route::get('/dashboard', AdminDashboardController::class)->name('admin.dashboard');
    Route::apiResource('products', AdminProductController::class);
    Route::post('/products/{productId}/images', [AdminProductImageController::class, 'store'])
        ->name('admin.products.images.store');
    Route::patch('/products/{productId}/images/{imageId}', [AdminProductImageController::class, 'update'])
        ->name('admin.products.images.update');
    Route::delete('/products/{productId}/images/{imageId}', [AdminProductImageController::class, 'destroy'])
        ->name('admin.products.images.destroy');
    Route::post('/products/{productId}/variants', [AdminProductVariantController::class, 'store'])
        ->name('admin.products.variants.store');
    Route::patch('/products/{productId}/variants/{variantId}', [AdminProductVariantController::class, 'update'])
        ->name('admin.products.variants.update');
    Route::delete('/products/{productId}/variants/{variantId}', [AdminProductVariantController::class, 'destroy'])
        ->name('admin.products.variants.destroy');
    Route::apiResource('categories', AdminCategoryController::class);
    Route::get('/orders', [AdminOrderController::class, 'index'])->name('admin.orders.index');
    Route::get('/orders/{orderId}', [AdminOrderController::class, 'show'])->name('admin.orders.show');
    Route::post('/orders/{orderId}/refunds', [AdminRefundController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('admin.orders.refunds.store');
    Route::patch('/orders/{orderId}/status', [AdminOrderController::class, 'updateStatus'])
        ->name('admin.orders.status.update');
    Route::get('/customers', [AdminCustomerController::class, 'index'])->name('admin.customers.index');
    Route::get('/customers/{customerId}', [AdminCustomerController::class, 'show'])->name('admin.customers.show');
    Route::patch('/customers/{customerId}/status', [AdminCustomerController::class, 'updateStatus'])
        ->name('admin.customers.status.update');
    Route::get('/settings', [AdminSettingsController::class, 'show'])->name('admin.settings.show');
    Route::patch('/settings', [AdminSettingsController::class, 'update'])->name('admin.settings.update');
    Route::get('/theme', [AdminThemeController::class, 'show'])->name('admin.theme.show');
    Route::patch('/theme', [AdminThemeController::class, 'update'])->name('admin.theme.update');
    Route::get('/audit-logs', [AdminAuditController::class, 'index'])->name('admin.audit-logs.index');
    Route::patch('/products/{productId}/inventory', [AdminInventoryController::class, 'updateProduct'])
        ->name('admin.products.inventory.update');
    Route::patch('/products/{productId}/variants/{variantId}/inventory', [AdminInventoryController::class, 'updateVariant'])
        ->name('admin.product-variants.inventory.update');
});

Route::middleware([
    'tenant.resolve',
    'tenant.host:storefront',
    'tenant.active',
])->prefix('storefront/catalog')->group(function (): void {
    Route::get('/categories', [CatalogController::class, 'categories'])
        ->name('storefront.catalog.categories');
    Route::get('/products', [CatalogController::class, 'index'])
        ->name('storefront.catalog.index');
    Route::get('/products/{slug}', [CatalogController::class, 'show'])
        ->name('storefront.catalog.show');
});

Route::middleware([
    'tenant.resolve',
    'tenant.host:storefront',
    'tenant.active',
])->get('/storefront/tenant', function (TenantManager $tenantManager) {
    $context = $tenantManager->current();
    $tenant = $context->tenant;
    $settings = TenantSettings::query()->forTenant($tenant->id)->firstOrFail();

    return response()->json([
        'id' => $tenant->id,
        'name' => $tenant->name,
        'status' => $tenant->status,
        'default_locale' => $tenant->default_locale,
        'default_currency' => $tenant->default_currency,
        'theme_config' => $settings->theme_config ?? [],
        'registration' => [
            'require_email_verification' => (bool) $settings->require_email_verification,
            'require_phone_verification' => (bool) $settings->require_phone_verification,
            'human_check_driver' => $settings->human_check_driver,
        ],
        'host_type' => $context->hostType,
    ]);
});

Route::middleware([
    'tenant.resolve',
    'tenant.host:storefront',
    'tenant.active',
    AuthenticateStorefrontSession::class.':optional',
])->prefix('storefront')->group(function (): void {
    Route::get('/cart', [CartController::class, 'show'])->name('storefront.cart.show');
    Route::get('/payment-config', [PaymentMethodController::class, 'configuration'])
        ->name('storefront.payment.configuration');
    Route::post('/auth/register', [StorefrontAuthController::class, 'register'])
        ->middleware([VerifyStorefrontCsrf::class, 'throttle:5,1', EnforceConfiguredVerification::class.':register'])
        ->name('storefront.auth.register');
    Route::post('/auth/login', [StorefrontAuthController::class, 'login'])
        ->middleware([VerifyStorefrontCsrf::class, 'throttle:10,1'])
        ->name('storefront.auth.login');
    Route::post('/auth/password-reset/request', [StorefrontPasswordResetController::class, 'requestCode'])
        ->middleware([VerifyStorefrontCsrf::class, 'throttle:5,1'])
        ->name('storefront.auth.password-reset.request');
    Route::post('/auth/password-reset', [StorefrontPasswordResetController::class, 'reset'])
        ->middleware([VerifyStorefrontCsrf::class, 'throttle:10,1'])
        ->name('storefront.auth.password-reset');
    Route::post('/verification/email', [VerificationController::class, 'issueEmail'])
        ->middleware([VerifyStorefrontCsrf::class, 'throttle:5,1'])
        ->name('storefront.verification.email');
    Route::post('/verification/human', [VerificationController::class, 'verifyHuman'])
        ->middleware([VerifyStorefrontCsrf::class, 'throttle:10,1'])
        ->name('storefront.verification.human');
    Route::post('/orders/lookup/request', [GuestOrderLookupController::class, 'requestCode'])
        ->middleware([VerifyStorefrontCsrf::class, 'throttle:5,1'])
        ->name('storefront.orders.lookup.request');
    Route::post('/orders/lookup', [GuestOrderLookupController::class, 'lookup'])
        ->middleware([VerifyStorefrontCsrf::class, 'throttle:10,1'])
        ->name('storefront.orders.lookup');
    Route::post('/cart/items', [CartController::class, 'add'])
        ->middleware([VerifyStorefrontCsrf::class, 'throttle:30,1'])
        ->name('storefront.cart.items.store');
    Route::patch('/cart/items/{itemId}', [CartController::class, 'update'])
        ->middleware([VerifyStorefrontCsrf::class, 'throttle:30,1'])
        ->name('storefront.cart.items.update');
    Route::delete('/cart/items/{itemId}', [CartController::class, 'destroy'])
        ->middleware([VerifyStorefrontCsrf::class, 'throttle:30,1'])
        ->name('storefront.cart.items.destroy');
    Route::post('/checkout', [CheckoutController::class, 'store'])
        ->middleware([VerifyStorefrontCsrf::class, 'throttle:5,1', EnforceConfiguredVerification::class.':checkout'])
        ->name('storefront.checkout.store');
});

Route::middleware([
    'tenant.resolve',
    'tenant.host:storefront',
    'tenant.active',
    AuthenticateStorefrontSession::class,
])->prefix('storefront')->group(function (): void {
    Route::post('/auth/logout', [StorefrontAuthController::class, 'logout'])
        ->middleware(VerifyStorefrontCsrf::class)
        ->name('storefront.auth.logout');
    Route::post('/account/email-verification/request', [AccountController::class, 'requestEmailVerification'])
        ->middleware([VerifyStorefrontCsrf::class, 'throttle:5,1'])
        ->name('storefront.account.email-verification.request');
    Route::post('/account/email-verification', [AccountController::class, 'verifyEmail'])
        ->middleware([VerifyStorefrontCsrf::class, 'throttle:10,1'])
        ->name('storefront.account.email-verification');
    Route::get('/account', [AccountController::class, 'show'])->name('storefront.account.show');
    Route::patch('/account', [AccountController::class, 'update'])
        ->middleware(VerifyStorefrontCsrf::class)
        ->name('storefront.account.update');
    Route::get('/account/orders', [AccountController::class, 'orders'])->name('storefront.account.orders');
    Route::get('/account/orders/{orderId}', [AccountController::class, 'order'])
        ->name('storefront.account.orders.show');
    Route::get('/account/payment-methods', [PaymentMethodController::class, 'index'])
        ->name('storefront.payment-methods.index');
    Route::post('/account/payment-methods/setup-intent', [PaymentMethodController::class, 'setupIntent'])
        ->middleware([VerifyStorefrontCsrf::class, 'throttle:5,1'])
        ->name('storefront.payment-methods.setup-intent');
    Route::post('/account/payment-methods', [PaymentMethodController::class, 'store'])
        ->middleware([VerifyStorefrontCsrf::class, 'throttle:10,1'])
        ->name('storefront.payment-methods.store');
    Route::patch('/account/payment-methods/{paymentMethodId}/default', [PaymentMethodController::class, 'makeDefault'])
        ->middleware(VerifyStorefrontCsrf::class)
        ->name('storefront.payment-methods.default');
    Route::delete('/account/payment-methods/{paymentMethodId}', [PaymentMethodController::class, 'destroy'])
        ->middleware(VerifyStorefrontCsrf::class)
        ->name('storefront.payment-methods.destroy');
    Route::post('/account/addresses', [AccountController::class, 'storeAddress'])
        ->middleware(VerifyStorefrontCsrf::class)
        ->name('storefront.account.addresses.store');
    Route::patch('/account/addresses/{addressId}', [AccountController::class, 'updateAddress'])
        ->middleware(VerifyStorefrontCsrf::class)
        ->name('storefront.account.addresses.update');
    Route::delete('/account/addresses/{addressId}', [AccountController::class, 'destroyAddress'])
        ->middleware(VerifyStorefrontCsrf::class)
        ->name('storefront.account.addresses.destroy');
});

Route::post('/webhooks/payment', PaymentWebhookController::class)
    ->middleware('throttle:payment-webhook')
    ->name('webhooks.payment');
