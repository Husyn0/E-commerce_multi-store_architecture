<?php

namespace Tests\Unit;

use App\Models\Address;
use App\Models\AuditLog;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\CheckoutIdempotencyRecord;
use App\Models\HumanVerification;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PaymentMethod;
use App\Models\PaymentWebhookEvent;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\Refund;
use App\Models\Tenant;
use App\Models\TenantSettings;
use App\Models\User;
use App\Models\UserSession;
use App\Models\VerificationCode;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ModelMappingTest extends TestCase
{
    #[DataProvider('tenantOwnedModels')]
    public function test_tenant_models_map_to_schema_tables_and_use_string_ids(string $class, string $table): void
    {
        $model = new $class;

        $this->assertSame($table, $model->getTable());
        $this->assertSame('string', $model->getKeyType());
        $this->assertFalse($model->getIncrementing());
        $this->assertNotContains('tenant_id', $model->getFillable());
    }

    public static function tenantOwnedModels(): array
    {
        return [
            [TenantSettings::class, 'tenant_settings'],
            [User::class, 'users'],
            [UserSession::class, 'user_sessions'],
            [VerificationCode::class, 'verification_codes'],
            [HumanVerification::class, 'human_verifications'],
            [PaymentMethod::class, 'payment_methods'],
            [PaymentWebhookEvent::class, 'payment_webhook_events'],
            [Category::class, 'categories'],
            [Product::class, 'products'],
            [ProductImage::class, 'product_images'],
            [ProductVariant::class, 'product_variants'],
            [Cart::class, 'carts'],
            [CartItem::class, 'cart_items'],
            [Address::class, 'addresses'],
            [Order::class, 'orders'],
            [OrderItem::class, 'order_items'],
            [Refund::class, 'refunds'],
            [CheckoutIdempotencyRecord::class, 'checkout_idempotency_records'],
            [AuditLog::class, 'audit_logs'],
        ];
    }

    public function test_tenant_model_maps_to_tenants_table(): void
    {
        $this->assertSame('tenants', (new Tenant)->getTable());
    }

    public function test_generated_cart_variant_key_is_not_mass_assignable(): void
    {
        $this->assertNotContains('variant_key', (new CartItem)->getFillable());
        $this->assertContains('variant_key', (new CartItem)->getGuarded());
    }

    public function test_password_and_token_hashes_are_hidden_from_serialization(): void
    {
        $this->assertContains('password_hash', (new User)->getHidden());
        $this->assertContains('token_hash', (new UserSession)->getHidden());
        $this->assertContains('code_hash', (new VerificationCode)->getHidden());
        $this->assertContains('session_token_hash', (new Cart)->getHidden());
        $this->assertSame('password_hash', (new User)->getAuthPasswordName());
    }
}
